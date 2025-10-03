<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyService
{
    private string $base;
    private string $token;
    private string $version;

    public function __construct()
    {
        // Read store value and normalize (accept either "nextprint.in" or "https://nextprint.in")
        $storeRaw = trim((string) env('SHOPIFY_STORE', ''));
        if ($storeRaw === '') {
            throw new RuntimeException('SHOPIFY_STORE not set in .env');
        }
        // remove protocol and trailing slash
        $store = preg_replace('#^https?://#', '', $storeRaw);
        $store = rtrim($store, '/');

        $this->base = 'https://' . $store; // safe base url: https://example.myshopify.com or https://yourdomain.com
        $this->token = trim((string) env('SHOPIFY_ADMIN_API_TOKEN', ''));
        if ($this->token === '') {
            throw new RuntimeException('SHOPIFY_ADMIN_API_TOKEN not set in .env');
        }
        $this->version = env('SHOPIFY_API_VERSION', '2024-10');

        Log::info('SHOPIFY SERVICE constructed', ['base' => $this->base, 'version' => $this->version]);
    }

    /**
     * Low-level GraphQL caller
     *
     * @return array
     * @throws \Throwable
     */
    private function gql(string $query, array $variables = []): array
    {
        $url = "{$this->base}/admin/api/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Accept' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $resp->throw();

        $json = $resp->json();

        if (isset($json['errors'])) {
            // GraphQL-level errors
            throw new RuntimeException('Shopify GraphQL errors: ' . json_encode($json['errors']));
        }

        return $json['data'] ?? [];
    }

    /**
     * Sync products in a particular "Nextprint" collection to local DB using REST collection -> products API.
     * Returns number processed.
     */
    public function syncNextprintToLocal(): int
    {
        $collectionName = trim((string) env('NEXTPRINT_COLLECTION', 'Show in NextPrint'));
        if ($collectionName === '') {
            Log::warning('NEXTPRINT_COLLECTION is empty');
            return 0;
        }

        // find collection id (custom or smart) by title
        $collectionId = $this->findCollectionIdByTitle($collectionName);
        if (!$collectionId) {
            Log::info('No collection id found for nextprint', ['collection' => $collectionName]);
            return 0;
        }

        $processed = 0;
        $perPage = 250;
        $pageInfo = null;
        $baseUrl = "{$this->base}/admin/api/{$this->version}/collections/{$collectionId}/products.json";

        while (true) {
            $params = ['limit' => $perPage];
            if ($pageInfo) $params['page_info'] = $pageInfo;

            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($baseUrl, $params);
            if ($resp->failed()) {
                Log::warning('REST products fetch failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                break;
            }
            $data = $resp->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                $minPrice = $this->extractMinPriceFromRestProduct($product);

                // Upsert to local Product model
                try {
                    \App\Models\Product::updateOrCreate(
                        ['shopify_product_id' => (string)($product['id'] ?? '')],
                        [
                            'name' => $product['title'] ?? null,
                            'price' => $minPrice,
                            'min_price' => $minPrice,
                            'vendor' => $product['vendor'] ?? null,
                            'status' => $product['status'] ?? 'active',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to upsert product', ['id' => $product['id'] ?? null, 'error' => $e->getMessage()]);
                }

                $processed++;
            }

            // parse Link header for next page_info
            $linkHeader = $resp->header('Link');
            $nextPageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $m)) {
                $nextPageInfo = $m[1];
            }

            if (!$nextPageInfo) break;
            $pageInfo = $nextPageInfo;
        }

        Log::info('syncNextprintToLocal completed', ['collection' => $collectionName, 'processed' => $processed]);
        return $processed;
    }

    /**
     * Helper to extract minimum price from REST product payload (variants / top-level).
     */
    private function extractMinPriceFromRestProduct(array $product): float
    {
        $variantPrices = [];

        if (!empty($product['variants']) && is_array($product['variants'])) {
            foreach ($product['variants'] as $v) {
                if (!empty($v['price']) && is_numeric($v['price']) && (float)$v['price'] >= 0.0) {
                    $variantPrices[] = (float)$v['price'];
                } elseif (!empty($v['price_cents']) && is_numeric($v['price_cents'])) {
                    $variantPrices[] = (float)$v['price_cents'] / 100.0;
                } elseif (!empty($v['price_in_cents']) && is_numeric($v['price_in_cents'])) {
                    $variantPrices[] = (float)$v['price_in_cents'] / 100.0;
                }
            }
        }

        if (!empty($variantPrices)) {
            return min($variantPrices);
        }

        // fallback: priceRangeV2 present (from GraphQL-shaped extension)
        if (!empty($product['priceRangeV2']['minVariantPrice']['amount'])) {
            return (float) $product['priceRangeV2']['minVariantPrice']['amount'];
        }

        // fallback: top-level price or variants[0]
        if (!empty($product['price']) && is_numeric($product['price'])) {
            return (float) $product['price'];
        }
        if (!empty($product['variants'][0]['price']) && is_numeric($product['variants'][0]['price'])) {
            return (float) $product['variants'][0]['price'];
        }

        return 0.00;
    }

    /**
     * Try GraphQL first to get products by collection handle, else fallback to REST.
     * Returns array of items (each item includes 'id','title','handle','vendor','tags','images','image','priceRangeV2','min_price','raw')
     */
    public function productsByCollectionHandle(string $handle, int $perPage = 250): array
    {
        // GraphQL query
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
          variants(first: 10) {
            edges {
              node { id price }
            }
          }
        }
      }
    }
  }
}
GQL;

        // Try GraphQL
        try {
            $data = $this->gql($gql, ['handle' => $handle, 'first' => $perPage]);

            if (!empty($data['collectionByHandle']['products']['edges'])) {
                $items = [];
                foreach ($data['collectionByHandle']['products']['edges'] as $edge) {
                    $node = $edge['node'] ?? [];
                    // build images array
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

                    $minPrice = null;
                    if (!empty($node['priceRangeV2']['minVariantPrice']['amount'])) {
                        $minPrice = (float) $node['priceRangeV2']['minVariantPrice']['amount'];
                    } else {
                        // fallback to variants in GraphQL shape
                        if (!empty($node['variants']['edges']) && is_array($node['variants']['edges'])) {
                            $vprices = [];
                            foreach ($node['variants']['edges'] as $ve) {
                                $price = $ve['node']['price'] ?? null;
                                if ($price !== null && is_numeric($price)) $vprices[] = (float)$price;
                            }
                            if (!empty($vprices)) $minPrice = min($vprices);
                        }
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
                        'min_price' => $minPrice,
                        'raw' => $node,
                    ];
                }

                Log::info('SHOPIFY SERVICE - graphql matched products', ['handle' => $handle, 'count' => count($items)]);
                return $items;
            }

            Log::info('SHOPIFY SERVICE - graphql returned no products (or collection not found)', ['handle' => $handle]);
        } catch (\Throwable $e) {
            Log::warning('SHOPIFY SERVICE - graphql error', ['handle' => $handle, 'error' => $e->getMessage()]);
        }

        // REST fallback: find collection id by handle then use /collections/{id}/products.json
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
                        if (empty($images) && !empty($p['image']['src'])) {
                            $images[] = ['src' => $p['image']['src']];
                        }

                        $minPrice = $this->extractMinPriceFromRestProduct($p);

                        $products[] = [
                            'id' => (string)($p['id'] ?? null),
                            'title' => $p['title'] ?? null,
                            'handle' => $p['handle'] ?? null,
                            'vendor' => $p['vendor'] ?? null,
                            'tags' => isset($p['tags']) ? array_map('trim', explode(',', $p['tags'])) : [],
                            'images' => $images,
                            'image' => ['src' => $images[0]['src'] ?? ($p['image']['src'] ?? null)],
                            'priceRangeV2' => null,
                            'min_price' => $minPrice,
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

    /**
     * Find collection id by title (custom or smart)
     */
    private function findCollectionIdByTitle(string $title): ?int
    {
        $types = ['custom_collections', 'smart_collections'];
        foreach ($types as $type) {
            try {
                $url = "{$this->base}/admin/api/{$this->version}/{$type}.json";
                $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['title' => $title, 'limit' => 250]);
                if ($resp->failed()) continue;
                $arr = $resp->json()[$type] ?? [];
                if (!empty($arr[0]['id'])) {
                    return (int) $arr[0]['id'];
                }
            } catch (\Throwable $e) {
                Log::warning('findCollectionIdByTitle error', ['type' => $type, 'error' => $e->getMessage()]);
            }
        }
        return null;
    }

    /**
     * Convert Shopify gid format to numeric id
     */
    public static function gidToId(?string $gid): ?int
    {
        if (!$gid) return null;
        $parts = explode('/', $gid);
        $last = end($parts);
        return is_numeric($last) ? (int)$last : null;
    }
}
