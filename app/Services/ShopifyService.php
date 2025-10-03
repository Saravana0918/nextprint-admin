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

    /**
     * Fetch products via REST and sync to local DB (robust)
     */
    public function syncNextprintToLocal(): int
    {
        $collectionName = trim((string) env('NEXTPRINT_COLLECTION', 'Show in NextPrint'));
        if ($collectionName === '') {
            throw new \RuntimeException('NEXTPRINT_COLLECTION not set in .env');
        }

        // find collection id by name (custom or smart)
        $findCollectionId = function(string $name) {
            // custom_collections
            $url = "{$this->base}/admin/api/{$this->version}/custom_collections.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['title' => $name]);
            $resp->throw();
            $data = $resp->json();
            if (!empty($data['custom_collections'][0]['id'])) {
                return (int) $data['custom_collections'][0]['id'];
            }

            // smart_collections
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
            Log::info('SHOPIFY SERVICE - no collection found', ['collection' => $collectionName]);
            return 0;
        }

        $processed = 0;
        $perPage = 250;
        $pageInfo = null;
        $baseUrl = "{$this->base}/admin/api/{$this->version}/collections/{$collectionId}/products.json";

        while (true) {
            $params = ['limit' => $perPage];
            if ($pageInfo) {
                $params['page_info'] = $pageInfo;
            }

            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
            $resp->throw();
            $data = $resp->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                // Normalize shopify id (accept gid or plain id)
                $rawId = $product['id'] ?? null;
                $shopifyId = null;
                if (is_string($rawId) && strpos($rawId, 'gid://') === 0) {
                    $shopifyId = self::gidToId($rawId);
                } else {
                    $shopifyId = (int) $rawId;
                }

                // compute min & max price robustly:
                $minPrice = 0.00;
                $maxPrice = 0.00;

                // If product has priceRangeV2 (GraphQL shape present in some REST payloads), use it
                if (!empty($product['priceRangeV2']['minVariantPrice']['amount'])) {
                    $minPrice = (float) $product['priceRangeV2']['minVariantPrice']['amount'];
                    $maxPrice = (float) ($product['priceRangeV2']['maxVariantPrice']['amount'] ?? $minPrice);
                } else {
                    // fallback: examine variants (REST)
                    $variantPrices = [];
                    if (!empty($product['variants']) && is_array($product['variants'])) {
                        foreach ($product['variants'] as $v) {
                            if (!empty($v['price']) && is_numeric($v['price'])) {
                                $variantPrices[] = (float) $v['price'];
                            } elseif (!empty($v['price_cents']) && is_numeric($v['price_cents'])) {
                                $variantPrices[] = ((float)$v['price_cents']) / 100.0;
                            } elseif (!empty($v['price_in_cents']) && is_numeric($v['price_in_cents'])) {
                                $variantPrices[] = ((float)$v['price_in_cents']) / 100.0;
                            }
                        }
                    }

                    if (!empty($variantPrices)) {
                        $minPrice = min($variantPrices);
                        $maxPrice = max($variantPrices);
                    } else {
                        // extra fallback
                        if (!empty($product['variants'][0]['price']) && is_numeric($product['variants'][0]['price'])) {
                            $minPrice = $maxPrice = (float) $product['variants'][0]['price'];
                        } elseif (!empty($product['price']) && is_numeric($product['price'])) {
                            $minPrice = $maxPrice = (float) $product['price'];
                        }
                    }
                }

                $minPrice = $minPrice ?: 0.00;
                $maxPrice = $maxPrice ?: $minPrice;

                // Upsert into products table
                try {
                    \App\Models\Product::updateOrCreate(
                        ['shopify_product_id' => $shopifyId],
                        [
                            'name'      => $product['title'] ?? null,
                            'price'     => $minPrice,
                            'min_price' => $minPrice,
                            'max_price' => $maxPrice,
                            'vendor'    => $product['vendor'] ?? null,
                            'status'    => $product['status'] ?? 'active',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('SHOPIFY SERVICE - product upsert failed', ['id' => $shopifyId, 'error' => $e->getMessage()]);
                }

                $processed++;
            }

            // parse Link header for next page_info
            $linkHeader = $resp->header('Link');
            $nextPageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $m)) {
                $nextPageInfo = $m[1];
            }

            if (!$nextPageInfo) {
                break;
            }
            $pageInfo = $nextPageInfo;
        }

        Log::info('SHOPIFY SERVICE - sync completed', ['collection_id' => $collectionId, 'processed' => $processed]);

        return $processed;
    }


    /**
     * Return all products for a collection given its handle (GraphQL preferred, REST fallback)
     */
    public function productsByCollectionHandle(string $handle, int $perPage = 250): array
    {
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

                    $items[] = [
                        'id' => $node['id'] ?? null,
                        'title' => $node['title'] ?? null,
                        'handle' => $node['handle'] ?? null,
                        'vendor' => $node['vendor'] ?? null,
                        'tags' => $node['tags'] ?? [],
                        'images' => $images,
                        'image' => ['src' => $images[0]['src'] ?? ($node['featuredImage']['url'] ?? null)],
                        'priceRangeV2' => $node['priceRangeV2'] ?? null,
                        'raw' => $node,
                    ];
                }

                Log::info('SHOPIFY SERVICE - graphql matched products', ['handle' => $handle, 'count' => count($items)]);
                return $items;
            }

            Log::info('SHOPIFY SERVICE - graphql returned no products for handle', ['handle' => $handle]);
        } catch (\Throwable $e) {
            Log::warning('SHOPIFY SERVICE - graphql error', ['handle' => $handle, 'error' => $e->getMessage()]);
        }

        // REST fallback (same approach as earlier but robust)
        try {
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

        return [];
    }
}
