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
          priceRange {
            minVariantPrice { amount currencyCode }
            maxVariantPrice { amount currencyCode }
          }
          variants(first: 50) {
            edges { node { id price presentmentPrices(first:5) { edges { node { price { amount currencyCode } } } } } }
            nodes { id price }
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
                        'priceRangeV2' => $node['priceRangeV2'] ?? $node['priceRange'] ?? null,
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

    /**********************************************************************
     *  Price helpers & resolver
     **********************************************************************/
    private function isValidAmount($v): bool
    {
        if ($v === null) return false;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return false;
        }
        return is_numeric($v);
    }

    private function fetchPriceViaRest(int $productId)
    {
        try {
            if (empty($this->base) || empty($this->token)) return null;
            $url = "{$this->base}/admin/api/{$this->version}/products/{$productId}.json";
            $res = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Accept' => 'application/json',
            ])->timeout(15)->get($url);

            if (!$res->ok()) {
                Log::warning('fetchPriceViaRest:not-ok', ['productId' => $productId, 'status' => $res->status()]);
                return null;
            }

            $j = $res->json();
            $firstVariant = $j['product']['variants'][0] ?? null;
            if (!$firstVariant) return null;

            $price = $firstVariant['price'] ?? null;
            if (!$this->isValidAmount($price) && !empty($firstVariant['presentment_prices'][0]['price']['amount'])) {
                $price = $firstVariant['presentment_prices'][0]['price']['amount'];
            }
            if (!$this->isValidAmount($price) && !empty($firstVariant['presentment_prices'][0]['amount'])) {
                $price = $firstVariant['presentment_prices'][0]['amount'];
            }
            return $price;
        } catch (\Throwable $e) {
            Log::warning('fetchPriceViaRest:error', ['productId' => $productId, 'err' => $e->getMessage()]);
            return null;
        }
    }

    private function resolveMinPriceFromProductArray(array $product): ?float
    {
        // 1) priceRangeV2 or priceRange (GraphQL)
        if (!empty($product['priceRangeV2']['minVariantPrice']['amount']) && $this->isValidAmount($product['priceRangeV2']['minVariantPrice']['amount'])) {
            return (float) $product['priceRangeV2']['minVariantPrice']['amount'];
        }
        if (!empty($product['priceRange']['minVariantPrice']['amount']) && $this->isValidAmount($product['priceRange']['minVariantPrice']['amount'])) {
            return (float) $product['priceRange']['minVariantPrice']['amount'];
        }

        $vals = [];

        // 2) graphql variants edges->node
        if (!empty($product['variants']['edges']) && is_array($product['variants']['edges'])) {
            foreach ($product['variants']['edges'] as $edge) {
                $node = $edge['node'] ?? [];
                $price = $node['price'] ?? null;
                if (!$this->isValidAmount($price) && !empty($node['presentmentPrices']['edges'][0]['node']['price']['amount'])) {
                    $price = $node['presentmentPrices']['edges'][0]['node']['price']['amount'];
                }
                if ($this->isValidAmount($price)) $vals[] = (float)$price;
            }
        }

        // 3) graphql variants.nodes
        if (!empty($product['variants']['nodes']) && is_array($product['variants']['nodes'])) {
            foreach ($product['variants']['nodes'] as $v) {
                $price = $v['price'] ?? null;
                if ($this->isValidAmount($price)) $vals[] = (float)$price;
            }
        }

        // 4) REST-like product['variants'] (numeric array)
        if (!empty($product['variants']) && array_values($product['variants']) === $product['variants']) {
            foreach ($product['variants'] as $v) {
                if (!is_array($v)) continue;
                $price = $v['price'] ?? ($v['node']['price'] ?? null);
                if (!$this->isValidAmount($price) && !empty($v['presentment_prices'][0]['price']['amount'])) {
                    $price = $v['presentment_prices'][0]['price']['amount'];
                }
                if ($this->isValidAmount($price)) $vals[] = (float)$price;
            }
        }

        // 5) product['raw']['variants'] fallback
        if (!empty($product['raw']['variants']) && is_array($product['raw']['variants'])) {
            foreach ($product['raw']['variants'] as $v) {
                $price = $v['price'] ?? null;
                if (!$this->isValidAmount($price) && !empty($v['presentment_prices'][0]['price']['amount'])) {
                    $price = $v['presentment_prices'][0]['price']['amount'];
                }
                if ($this->isValidAmount($price)) $vals[] = (float)$price;
            }
        }

        if (!empty($vals)) {
            return min($vals);
        }

        // 6) final fallback: REST fetch single product by id
        $productId = null;
        if (!empty($product['id']) && is_string($product['id']) && str_contains($product['id'], 'gid://')) {
            $productId = self::gidToId($product['id']);
        } elseif (!empty($product['raw']['id'])) {
            $productId = (int)$product['raw']['id'];
        } elseif (!empty($product['id']) && is_numeric($product['id'])) {
            $productId = (int)$product['id'];
        }

        if ($productId) {
            $restPrice = $this->fetchPriceViaRest((int)$productId);
            if ($this->isValidAmount($restPrice)) {
                return (float)$restPrice;
            }
        }

        return null;
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
                } elseif (!empty($product['id']) && is_numeric($product['id'])) {
                    $shopifyId = (int) $product['id'];
                }

                if (!$shopifyId) {
                    Log::warning('SHOPIFY SERVICE - product missing shopify id, skipping', ['product' => $product]);
                    continue;
                }

                // Determine min price robustly using resolver
                $minPrice = $this->resolveMinPriceFromProductArray($product);

                // Only update price fields if we found a valid price (avoid erasing existing)
                $values = [
                    'name' => $product['title'] ?? ($product['raw']['title'] ?? null),
                    'vendor' => $product['vendor'] ?? ($product['raw']['vendor'] ?? null),
                    'status' => $product['raw']['status'] ?? 'active',
                ];

                if ($minPrice !== null && $this->isValidAmount($minPrice)) {
                    // normalize to 2 decimal string
                    $fmt = number_format((float)$minPrice, 2, '.', '');
                    $values['price'] = $fmt;
                    $values['min_price'] = $fmt;
                }

                Product::updateOrCreate(
                    ['shopify_product_id' => $shopifyId],
                    $values
                );

                $processed++;

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
