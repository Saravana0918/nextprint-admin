<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class ShopifyService
{
    private string $base;
    private string $token;
    private string $version;

    public function __construct()
    {
        $this->base    = rtrim('https://' . trim((string) env('SHOPIFY_STORE', '')), '/');
        $this->token   = trim((string) env('SHOPIFY_ADMIN_API_TOKEN', ''));
        $this->version = env('SHOPIFY_API_VERSION', '2024-10');

        if (empty($this->base) || empty($this->token)) {
            // Fail early so developer sees the misconfig
            Log::warning('SHOPIFY SERVICE - missing env keys', [
                'base' => $this->base,
                'token_set' => !empty($this->token),
            ]);
        }
    }

    private function gql(string $query, array $variables = []): array
    {
        $url = "{$this->base}/admin/api/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->token,
                    'Accept' => 'application/json',
                ])
                ->post($url, [
                    'query'     => $query,
                    'variables' => $variables,
                ]);

        $resp->throw();

        $json = $resp->json();
        if (isset($json['errors'])) {
            throw new \RuntimeException(json_encode($json['errors']));
        }

        return $json['data'] ?? [];
    }

    /**
     * Convert gid://shopify/Product/12345 to 12345
     */
    public static function gidToId(?string $gid): ?int
    {
        if (!$gid) return null;
        $parts = preg_split('/[\/:]/', $gid);
        return (int) end($parts);
    }

    /**
     * productsByCollectionHandle - existing function (kept & slightly adapted)
     */
    public function productsByCollectionHandle(string $handle, int $perPage = 250): array
    {
        // GraphQL query (preferred)
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
          images(first: 10) {
            edges { node { url } }
          }
          featuredImage { url }
          priceRangeV2 {
            minVariantPrice { amount currencyCode }
            maxVariantPrice { amount currencyCode }
          }
          variants(first: 50) {
            edges { node { id price } }
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
                    if (!empty($node['images']['edges'])) {
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
                        'variants' => $node['variants'] ?? null,
                        'raw' => $node,
                    ];
                }
                Log::info('SHOPIFY SERVICE - graphql matched products', ['handle' => $handle, 'count' => count($items)]);
                return $items;
            }

            Log::info('SHOPIFY SERVICE - graphql returned no products', ['handle' => $handle]);
        } catch (\Throwable $e) {
            Log::warning('SHOPIFY SERVICE - graphql error', ['handle' => $handle, 'error' => $e->getMessage()]);
        }

        // REST fallback (try by collection handle via listing collections and matching handle)
        try {
            $colTypes = ['custom_collections', 'smart_collections'];
            $collectionId = null;
            foreach ($colTypes as $type) {
                $url = "{$this->base}/admin/api/{$this->version}/{$type}.json";
                $r = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['limit' => 250]);
                if ($r->failed()) continue;
                $arr = $r->json()[$type] ?? [];
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
                    $r = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
                    $r->throw();
                    $data = $r->json();
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

                    $linkHeader = $r->header('Link');
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

    /**
     * syncNextprintToLocal - robust implementation using collection handle from env
     */
    public function syncNextprintToLocal(): int
    {
        // prefer HANDLE env: set NEXTPRINT_COLLECTION_HANDLE=show-in-nextprint
        $collectionHandle = trim((string) env('NEXTPRINT_COLLECTION_HANDLE', ''));
        $collectionName = trim((string) env('NEXTPRINT_COLLECTION', ''));

        if ($collectionHandle === '' && $collectionName === '') {
            Log::warning('NEXTPRINT_COLLECTION and NEXTPRINT_COLLECTION_HANDLE both empty');
            return 0;
        }

        // If handle provided — use productsByCollectionHandle (GraphQL preferred)
        if ($collectionHandle !== '') {
            $items = $this->productsByCollectionHandle($collectionHandle, 250);
        } else {
            // No handle provided: attempt to find collection id by title (existing REST code) and then call REST products endpoint
            $items = $this->productsByCollectionHandle($this->slugify($collectionName), 250);
            // Note: slugify is used because handles are slugified titles. If not found, productsByCollectionHandle will fallback to REST search.
        }

        if (empty($items)) {
            Log::info('SHOPIFY SERVICE - no items returned for collection', ['handle' => $collectionHandle, 'name' => $collectionName]);
            return 0;
        }

        $processed = 0;
        foreach ($items as $product) {
            try {
                // Convert gid to numeric if present
                $shopifyId = null;
                if (!empty($product['id']) && is_string($product['id']) && str_contains($product['id'], 'gid://')) {
                    $shopifyId = self::gidToId($product['id']);
                } elseif (!empty($product['raw']['id'])) {
                    $shopifyId = (int)$product['raw']['id'];
                } elseif (!empty($product['raw']['id']) && is_numeric($product['raw']['id'])) {
                    $shopifyId = (int) $product['raw']['id'];
                }

                // Determine min price robustly
                $variantPrices = [];
                if (!empty($product['raw']['variants']) && is_array($product['raw']['variants'])) {
                    foreach ($product['raw']['variants'] as $v) {
                        // GraphQL variants may come under edges->node
                        if (isset($v['edges'])) {
                            foreach ($v['edges'] as $edge) {
                                $node = $edge['node'] ?? [];
                                if (!empty($node['price'])) $variantPrices[] = (float)$node['price'];
                            }
                        } elseif (!empty($v['price'])) {
                            if (is_numeric($v['price'])) $variantPrices[] = (float)$v['price'];
                        } elseif (!empty($v['node']['price'])) {
                            if (is_numeric($v['node']['price'])) $variantPrices[] = (float)$v['node']['price'];
                        }
                    }
                }
                // priceRangeV2 fallback (GraphQL)
                if (empty($variantPrices) && !empty($product['priceRangeV2']['minVariantPrice']['amount'])) {
                    $variantPrices[] = (float) $product['priceRangeV2']['minVariantPrice']['amount'];
                }
                // raw top-level price fallback
                if (empty($variantPrices) && !empty($product['raw']['variants'][0]['price'])) {
                    $variantPrices[] = (float) $product['raw']['variants'][0]['price'];
                }

                $minPrice = (!empty($variantPrices) ? min($variantPrices) : 0.00);

                // upsert into local DB
                if ($shopifyId) {
                    Product::updateOrCreate(
                        ['shopify_product_id' => $shopifyId],
                        [
                            'name' => $product['title'] ?? ($product['raw']['title'] ?? null),
                            'price' => $minPrice,
                            'min_price' => $minPrice,
                            'vendor' => $product['vendor'] ?? ($product['raw']['vendor'] ?? null),
                            'status' => $product['raw']['status'] ?? 'active',
                        ]
                    );
                    $processed++;
                } else {
                    Log::warning('SHOPIFY SERVICE - product missing shopify id, skipping', ['product' => $product]);
                }
            } catch (\Throwable $e) {
                Log::error('SHOPIFY SERVICE - error upserting product', ['error' => $e->getMessage(), 'product' => $product]);
            }
        }

        Log::info('SHOPIFY SERVICE - sync completed', ['processed' => $processed]);
        return $processed;
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s);
        $s = trim($s, '-');
        return $s;
    }
}
