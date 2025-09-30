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

    // find collection id (custom or smart)
    $findCollectionId = function(string $name) {
        foreach (['custom_collections','smart_collections'] as $type) {
            $url  = "{$this->base}/admin/api/{$this->version}/{$type}.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['title' => $name]);
            if ($resp->failed()) continue;
            $data = $resp->json();
            if (!empty($data[$type][0]['id'])) return (int)$data[$type][0]['id'];
        }
        return null;
    };

    $collectionId = $findCollectionId($collectionName);
    if (!$collectionId) {
        Log::info('SHOPIFY SERVICE - no collection id', ['collection' => $collectionName]);
        return 0;
    }

    // helper: parse price from many shapes
    $parsePrice = function($v) {
        if ($v === null) return null;
        if (is_array($v)) {
            if (!empty($v[0]['price']['amount'])) return (float)$v[0]['price']['amount'];   // presentment_prices[0].price.amount
            if (!empty($v['price']['amount']))   return (float)$v['price']['amount'];       // price.amount
            if (!empty($v['amount']))            return (float)$v['amount'];                // {amount: "..."}
            if (!empty($v['minVariantPrice']['amount'])) return (float)$v['minVariantPrice']['amount']; // priceRangeV2
        }
        $s = preg_replace('/[^\d.\-]/', '', (string)$v);
        return $s === '' ? null : (float)$s;
    };

    // helper: compute prices from a FULL product (with variants)
    $computeFromProduct = function(array $product) use ($parsePrice) {
        $prices = [];

        if (!empty($product['variants']) && is_array($product['variants'])) {
            foreach ($product['variants'] as $vv) {
                foreach ([
                    $vv['price'] ?? null,
                    $vv['presentment_prices'] ?? null,
                    $vv['compare_at_price'] ?? null,
                    $vv['price_cents'] ?? null,
                    $vv['price_in_cents'] ?? null,
                    (is_array($vv['price'] ?? null) && isset(($vv['price'])[0]['amount'])) ? $vv['price'][0]['amount'] : null,
                ] as $cand) {
                    $p = $parsePrice($cand);
                    if ($p && $p > 0) { $prices[] = $p; break; }
                }
            }
        }

        // product-level fallbacks
        if (empty($prices)) {
            foreach ([
                $product['variants'][0]['price'] ?? null,
                $product['price'] ?? null,
            ] as $cand) {
                $p = $parsePrice($cand);
                if ($p && $p > 0) { $prices[] = $p; break; }
            }
        }

        if (empty($prices)) return [null,null,null];

        $min = round(min($prices), 2);
        $max = round(max($prices), 2);
        $primary = $min;
        return [$primary,$min,$max];
    };

    $processed = 0;
    $perPage   = 250;
    $pageInfo  = null;
    $baseUrl   = "{$this->base}/admin/api/{$this->version}/collections/{$collectionId}/products.json";

    while (true) {
        // IMPORTANT: ask REST to include variants explicitly
        $params = [
            'limit'  => $perPage,
            'fields' => 'id,title,handle,vendor,status,image,images,variants'
        ];
        if ($pageInfo) $params['page_info'] = $pageInfo;

        $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
        $resp->throw();
        $data     = $resp->json();
        $products = $data['products'] ?? [];

        foreach ($products as $product) {
            try {
                $pid   = $product['id'] ?? null;
                $title = $product['title'] ?? null;

                // If variants missing or empty, fetch FULL product as fallback
                if (empty($product['variants'])) {
                    Log::info('SHOPIFY SYNC - variants missing; fetching full product', ['id' => $pid, 'title' => $title]);
                    $full = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])
                        ->get("{$this->base}/admin/api/{$this->version}/products/{$pid}.json")
                        ->throw()
                        ->json('product') ?? [];
                    // merge what we need
                    $product['variants'] = $full['variants'] ?? [];
                    if (empty($product['image']) && !empty($full['image'])) $product['image'] = $full['image'];
                    if (empty($product['images']) && !empty($full['images'])) $product['images'] = $full['images'];
                }

                // compute prices
                [$primary,$min,$max] = $computeFromProduct($product);

                Log::info('SHOPIFY SYNC - price_result', [
                    'product_id'    => $pid,
                    'title'         => $title,
                    'variant_count' => count($product['variants'] ?? []),
                    'min_price'     => $min,
                    'max_price'     => $max,
                    'primary_price' => $primary,
                ]);

                // Upsert to local products table (change model if you use ShopifyProduct)
                \App\Models\Product::updateOrCreate(
                    ['shopify_product_id' => (string)$pid],
                    [
                        'name'      => $title,
                        'price'     => $primary,
                        'min_price' => $min,
                        'max_price' => $max,
                        'vendor'    => $product['vendor'] ?? null,
                        'status'    => $product['status'] ?? 'active',
                        'handle'    => $product['handle'] ?? null,
                        'image_url' => $product['image']['src'] ?? ($product['images'][0]['src'] ?? null),
                    ]
                );

                $processed++;
            } catch (\Throwable $e) {
                Log::error('SHOPIFY SYNC - product_upsert_error', [
                    'id' => $product['id'] ?? null,
                    'err' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // pagination
        $linkHeader   = $resp->header('Link');
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
