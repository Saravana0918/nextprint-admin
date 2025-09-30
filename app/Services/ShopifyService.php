<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    private string $base;
    private string $token;
    private string $version;

    public function __construct()
    {
        // env keys: SHOPIFY_STORE (example: yogireddy.myshopify.com)
        //           SHOPIFY_ADMIN_API_TOKEN
        //           SHOPIFY_API_VERSION (optional)
        $this->base    = 'https://' . trim((string) env('SHOPIFY_STORE'));
        $this->token   = trim((string) env('SHOPIFY_ADMIN_API_TOKEN'));
        $this->version = env('SHOPIFY_API_VERSION', '2024-10');
    }

    /**
     * Low-level GraphQL caller
     */
    private function gql(string $query, array $variables = []): array
    {
        $url = "{$this->base}/admin/api/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->token,
                ])
                ->post($url, [
                    'query'     => $query,
                    'variables' => $variables,
                ])
                ->throw()
                ->json();

        if (isset($resp['errors'])) {
            throw new \RuntimeException(json_encode($resp['errors']));
        }

        return $resp['data'] ?? [];
    }

    /**
     * Fetch products via REST and sync to local DB
     *
     * Uses REST products.json with cursor (page_info) pagination and
     * a case-insensitive tag match. Returns number of processed products.
     */
    public function syncNextprintToLocal(): int
    {
        $collectionName = trim((string) env('NEXTPRINT_COLLECTION', 'Show in NextPrint'));
        if ($collectionName === '') {
            throw new \RuntimeException('NEXTPRINT_COLLECTION not set in .env');
        }

        // find collection id
        $findCollectionId = function(string $name) {
            // try custom_collections
            $url = "{$this->base}/admin/api/{$this->version}/custom_collections.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['title' => $name]);
            $resp->throw();
            $data = $resp->json();
            if (!empty($data['custom_collections'][0]['id'])) {
                return (int) $data['custom_collections'][0]['id'];
            }

            // try smart_collections
            $url = "{$this->base}/admin/api/{$this->version}/smart_collections.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['title' => $name]);
            $resp->throw();
            $data = $resp->json();
            if (!empty($data['smart_collections'][0]['id'])) {
                return (int) $data['smart_collections'][0]['id'];
            }

            return null;
        };

        $collectionId = $findCollectionId($collectionName);
        if (!$collectionId) {
            Log::info('SHOPIFY SERVICE - no collection id', ['collection' => $collectionName]);
            return 0;
        }

        // price parse helper - robust for many shapes
        $parsePrice = function($candidate) {
            if ($candidate === null) return null;

            // presentment_prices: [ ['price' => ['amount' => '123.45']], ... ]
            if (is_array($candidate)) {
                // array-of-presentments
                if (!empty($candidate[0]['price']['amount'])) {
                    return (float) $candidate[0]['price']['amount'];
                }
                // direct price => ['amount'=> '123.45']
                if (!empty($candidate['price']['amount'])) {
                    return (float) $candidate['price']['amount'];
                }
                // simple amount field (GraphQL priceRangeV2 like)
                if (!empty($candidate['amount'])) {
                    return (float) $candidate['amount'];
                }
                // nested GraphQL shape: priceRangeV2 => minVariantPrice => amount
                if (!empty($candidate['minVariantPrice']['amount'])) {
                    return (float) $candidate['minVariantPrice']['amount'];
                }
            }

            // string or numeric: remove non-numeric (currency symbols) and parse
            if (is_string($candidate) || is_numeric($candidate)) {
                $clean = preg_replace('/[^\d.\-]/', '', (string)$candidate);
                if ($clean === '') return null;
                return (float) $clean;
            }

            return null;
        };

        $processed = 0;
        $perPage = 250;
        $pageInfo = null;
        $baseUrl = "{$this->base}/admin/api/{$this->version}/collections/{$collectionId}/products.json";

        while (true) {
            $params = ['limit' => $perPage];
            if ($pageInfo) $params['page_info'] = $pageInfo;

            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
            $resp->throw();
            $data = $resp->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                try {
                    Log::info('SHOPIFY SYNC - product_raw', ['id' => $product['id'] ?? null, 'title' => $product['title'] ?? null]);

                    $variantPrices = [];

                    // If priceRangeV2 present (normalized GraphQL shape)
                    if (!empty($product['priceRangeV2']) && is_array($product['priceRangeV2'])) {
                        $minCandidate = $product['priceRangeV2']['minVariantPrice']['amount'] ?? null;
                        $maxCandidate = $product['priceRangeV2']['maxVariantPrice']['amount'] ?? null;
                        if ($minCandidate !== null) $variantPrices[] = (float) $minCandidate;
                        if ($maxCandidate !== null) $variantPrices[] = (float) $maxCandidate;
                    }

                    // Primary: iterate variants and parse many shapes
                    if (!empty($product['variants']) && is_array($product['variants'])) {
                        foreach ($product['variants'] as $v) {
                            // DEBUG: log variant shape (light)
                            Log::info('SHOPIFY SYNC - variant_debug', [
                                'product_id' => $product['id'] ?? null,
                                'variant_id' => $v['id'] ?? null,
                                'variant_keys' => array_keys($v),
                                // raw_price for quick glance
                                'raw_price_preview' => isset($v['price']) ? $v['price'] : (isset($v['presentment_prices']) ? '[presentment_prices]' : (isset($v['compare_at_price']) ? $v['compare_at_price'] : null))
                            ]);

                            // Also log the full variant payload for at least one sample variant per product
                            Log::info('SHOPIFY SYNC - variant_full_sample', [
                                'product_id' => $product['id'] ?? null,
                                'variant_id' => $v['id'] ?? null,
                                'variant_full' => $v
                            ]);

                            Log::info('SHOPIFY SYNC - variant_full_dump', [
                                'product_id' => $product['id'] ?? null,
                                'variant' => $v
                            ]);

                            // candidate locations to attempt parse
                            $candidates = [
                                $v['price'] ?? null,
                                $v['presentment_prices'] ?? null,
                                $v['compare_at_price'] ?? null,
                                $v['price_in_cents'] ?? null,
                                $v['price_cents'] ?? null,
                                // sometimes price is nested like price[0]['amount']
                                (is_array($v['price']) && isset($v['price'][0]['amount'])) ? $v['price'][0]['amount'] : null,
                            ];

                            foreach ($candidates as $cand) {
                                $p = $parsePrice($cand);
                                if ($p !== null && $p > 0) {
                                    $variantPrices[] = $p;
                                    break; // found price for this variant
                                }
                            }
                        }
                    }

                    // Fallbacks: top-level product-level candidates
                    if (empty($variantPrices)) {
                        $fallbackCandidates = [
                            $product['variants'][0]['price'] ?? null,
                            $product['price'] ?? null,
                            $product['raw']['priceRangeV2'] ?? null,
                            $product['raw']['variants'][0]['price'] ?? null,
                            $product['raw']['variants'][0]['presentment_prices'] ?? null,
                        ];
                        foreach ($fallbackCandidates as $fc) {
                            $p = $parsePrice($fc);
                            if ($p !== null && $p > 0) {
                                $variantPrices[] = $p;
                                break;
                            }
                        }
                    }

                    // compute min/max
                    $minPrice = null;
                    $maxPrice = null;
                    if (!empty($variantPrices)) {
                        $minPrice = round(min($variantPrices), 2);
                        $maxPrice = round(max($variantPrices), 2);
                    }

                    // choose primary price: minPrice if available, else first non-zero
                    $primaryPrice = $minPrice;
                    if ($primaryPrice === null && !empty($variantPrices)) {
                        $primaryPrice = round($variantPrices[0], 2);
                    }

                    Log::info('SHOPIFY SYNC - price_result', [
                        'product_id' => $product['id'] ?? null,
                        'title' => $product['title'] ?? null,
                        'variant_count' => count($product['variants'] ?? []),
                        'found_prices' => $variantPrices,
                        'min_price' => $minPrice,
                        'max_price' => $maxPrice,
                        'primary_price' => $primaryPrice,
                    ]);

                    // Upsert to local DB (adjust model if you use ShopifyProduct instead of Product)
                    \App\Models\Product::updateOrCreate(
                        ['shopify_product_id' => (string)($product['id'] ?? '')],
                        [
                            'name'      => $product['title'] ?? null,
                            'price'     => $primaryPrice !== null ? $primaryPrice : null,
                            'min_price' => $minPrice !== null ? $minPrice : null,
                            'max_price' => $maxPrice !== null ? $maxPrice : null,
                            'vendor'    => $product['vendor'] ?? null,
                            'status'    => $product['status'] ?? 'active',
                            'handle'    => $product['handle'] ?? null,
                            'image_url' => $product['image']['src'] ?? ($product['images'][0]['src'] ?? null),
                        ]
                    );

                    $processed++;
                } catch (\Throwable $e) {
                    Log::error('SHOPIFY SYNC - product_upsert_error', ['id' => $product['id'] ?? null, 'err' => $e->getMessage()]);
                    continue;
                }
            }

            // pagination next page_info
            $linkHeader = $resp->header('Link');
            $nextPageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $m)) {
                $nextPageInfo = $m[1];
            }

            if (!$nextPageInfo) break;
            $pageInfo = $nextPageInfo;
        }

        Log::info('SHOPIFY SYNC - complete', ['processed' => $processed, 'collection' => $collectionName]);
        return $processed;
    }

    /**
     * Return all products for a collection given its handle (REST).
     * Uses /collections -> find id by handle -> /collections/{id}/products.json
     * Returns array of REST product arrays.
     */
    public function productsByCollectionHandle(string $handle, int $perPage = 250): array
    {
        // Try GraphQL first (preferred) — this will return nodes in the GraphQL shape
        $gql = <<<'GQL'
query collectionProducts($handle: String!, $first: Int!) {
  collectionByHandle(handle: $handle) {
    id
    handle
    title
    products(first: $first) {
      edges {
        node {
          id
          title
          handle
          vendor
          tags
          images(first: 5) {
            edges { node { url } }
          }
          featuredImage { url }
          priceRangeV2 {
            minVariantPrice { amount currencyCode }
            maxVariantPrice { amount currencyCode }
          }
        }
      }
    }
  }
}
GQL;

        try {
            $data = $this->gql($gql, ['handle' => $handle, 'first' => $perPage]);

            if (!empty($data['collectionByHandle']['products']['edges'])) {
                $items = [];
                foreach ($data['collectionByHandle']['products']['edges'] as $edge) {
                    $node = $edge['node'] ?? [];

                    // Build images in a shape your sync expects: either images[] or images.edges[].node.url
                    $images = [];
                    if (!empty($node['images']['edges']) && is_array($node['images']['edges'])) {
                        foreach ($node['images']['edges'] as $ie) {
                            $url = $ie['node']['url'] ?? null;
                            if ($url) $images[] = ['src' => $url];
                        }
                    }
                    if (empty($images) && !empty($node['featuredImage']['url'])) {
                        $images[] = ['src' => $node['featuredImage']['url']];
                    }

                    // priceRangeV2 preserved as-is (sync expects priceRangeV2->minVariantPrice->amount)
                    $items[] = [
                        'id' => $node['id'] ?? null,
                        'title' => $node['title'] ?? null,
                        'handle' => $node['handle'] ?? null,
                        'vendor' => $node['vendor'] ?? null,
                        'tags' => $node['tags'] ?? [],
                        'images' => $images,                 // array of ['src'=>...]
                        'image' => ['src' => $images[0]['src'] ?? ($node['featuredImage']['url'] ?? null)],
                        'priceRangeV2' => $node['priceRangeV2'] ?? null,
                        'raw' => $node,
                    ];
                }

                Log::info('SHOPIFY SERVICE - graphql matched products', ['handle' => $handle, 'count' => count($items)]);
                return $items;
            }

            // GraphQL returned collection but no products (or collection not found)
            Log::info('SHOPIFY SERVICE - graphql returned no products for handle', ['handle' => $handle]);
        } catch (\Throwable $e) {
            Log::warning('SHOPIFY SERVICE - graphql error', ['handle' => $handle, 'error' => $e->getMessage()]);
        }

        // Fallback: REST path (try to find collection id by handle and fetch products)
        try {
            // get custom and smart collections, match by handle
            $colTypes = ['custom_collections', 'smart_collections'];
            $collectionId = null;
            foreach ($colTypes as $type) {
                $url = "{$this->base}/admin/api/{$this->version}/{$type}.json";
                $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['limit' => 250]);
                if ($resp->failed()) continue;
                $arr = $resp->json()[$type] ?? [];
                foreach ($arr as $c) {
                    if (!empty($c['handle']) && $c['handle'] === $handle) {
                        $collectionId = $c['id'];
                        break 2;
                    }
                }
            }

            if ($collectionId) {
                $products = [];
                $pageInfo = null;
                $baseUrl = "{$this->base}/admin/api/{$this->version}/collections/{$collectionId}/products.json";

                while (true) {
                    $params = ['limit' => $perPage];
                    if ($pageInfo) $params['page_info'] = $pageInfo;

                    $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
                    $resp->throw();
                    $data = $resp->json();
                    $batch = $data['products'] ?? [];
                    foreach ($batch as $p) {
                        // normalize REST product to similar shape as GraphQL node
                        $images = [];
                        if (!empty($p['images']) && is_array($p['images'])) {
                            foreach ($p['images'] as $im) {
                                if (!empty($im['src'])) $images[] = ['src' => $im['src']];
                                elseif (!empty($im['url'])) $images[] = ['src' => $im['url']];
                            }
                        }
                        $products[] = [
                            'id' => (string)($p['id'] ?? null),
                            'title' => $p['title'] ?? null,
                            'handle' => $p['handle'] ?? null,
                            'vendor' => $p['vendor'] ?? null,
                            'tags' => isset($p['tags']) ? array_map('trim', explode(',', $p['tags'])) : [],
                            'images' => $images,
                            'image' => ['src' => $images[0]['src'] ?? ($p['image']['src'] ?? null)],
                            'priceRangeV2' => null,
                            'raw' => $p,
                        ];
                    }

                    // parse next page_info from Link header
                    $linkHeader = $resp->header('Link');
                    $nextPage = null;
                    if ($linkHeader && preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $m)) {
                        $nextPage = $m[1];
                    }
                    if (!$nextPage) break;
                    $pageInfo = $nextPage;
                }

                Log::info('SHOPIFY SERVICE - rest fallback matched products', ['handle' => $handle, 'count' => count($products)]);
                return $products;
            } else {
                Log::info('SHOPIFY SERVICE - rest fallback found no collection id for handle', ['handle' => $handle]);
            }
        } catch (\Throwable $e) {
            Log::warning('SHOPIFY SERVICE - rest fallback error', ['handle' => $handle, 'error' => $e->getMessage()]);
        }

        // nothing found
        return [];
    }

    /**
     * Convert Shopify global ID (gid) to numeric ID
     */
    public static function gidToId(?string $gid): ?int
    {
        if (!$gid) {
            return null;
        }
        $parts = explode('/', $gid);
        return (int) end($parts);
    }
}
