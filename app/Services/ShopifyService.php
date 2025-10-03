<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyService
{
    private string $base;
    private string $token;
    private string $version;

    public function __construct()
    {
        // SHOPIFY_STORE must be domain only like "yours.myshopify.com"
        $store = trim((string) env('SHOPIFY_STORE', ''));
        // normalize: strip schema and trailing slash if present
        $store = preg_replace('#^https?://#', '', $store);
        $store = rtrim($store, "/");

        if (empty($store)) {
            throw new \RuntimeException('SHOPIFY_STORE not set or invalid in .env (should be domain only)');
        }
        $this->base = 'https://' . $store;
        $this->token = trim((string) env('SHOPIFY_ADMIN_API_TOKEN', ''));
        if (empty($this->token)) {
            throw new \RuntimeException('SHOPIFY_ADMIN_API_TOKEN not set in .env');
        }
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
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $resp->throw();
        $body = $resp->json();

        if (isset($body['errors']) && !empty($body['errors'])) {
            throw new \RuntimeException('Shopify GraphQL errors: ' . json_encode($body['errors']));
        }

        return $body['data'] ?? [];
    }

    /**
     * Convert numeric gid or REST numeric id to integer
     */
    public static function gidToId($id): ?int
    {
        if ($id === null) return null;
        $id = (string) $id;
        if (Str::contains($id, '/')) {
            $parts = explode('/', $id);
            $last = end($parts);
            return is_numeric($last) ? (int) $last : null;
        }
        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Find a collection id by either handle (preferred) or title (case-insensitive).
     * Returns integer id or null.
     */
    private function findCollectionIdByHandleOrTitle(?string $handleOrTitle): ?int
    {
        if (empty($handleOrTitle)) return null;

        // First try handle matching (smart + custom collections)
        foreach (['custom_collections','smart_collections'] as $type) {
            $url = "{$this->base}/admin/api/{$this->version}/{$type}.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['limit' => 250]);
            if ($resp->failed()) continue;
            $arr = $resp->json()[$type] ?? [];
            foreach ($arr as $c) {
                // match handle exactly
                if (!empty($c['handle']) && $c['handle'] === $handleOrTitle) {
                    return (int)$c['id'];
                }
            }
        }

        // If none by handle, try title (case-insensitive)
        $needle = mb_strtolower($handleOrTitle);
        foreach (['custom_collections','smart_collections'] as $type) {
            $url = "{$this->base}/admin/api/{$this->version}/{$type}.json";
            $resp = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])->get($url, ['limit' => 250]);
            if ($resp->failed()) continue;
            $arr = $resp->json()[$type] ?? [];
            foreach ($arr as $c) {
                $title = mb_strtolower($c['title'] ?? '');
                if ($title === $needle) {
                    return (int)$c['id'];
                }
            }
        }

        return null;
    }

    /**
     * Sync all products belonging to a collection (by handle or title) to local DB.
     * Returns number of processed products.
     *
     * Requires in .env either:
     *   NEXTPRINT_COLLECTION_HANDLE=show-in-nextprint   (preferred)
     * or
     *   NEXTPRINT_COLLECTION="Show in NextPrint"        (title fallback)
     */
    public function syncNextprintToLocal(): int
    {
        $handleEnv = trim((string) env('NEXTPRINT_COLLECTION_HANDLE', ''));
        $collectionEnv = trim((string) env('NEXTPRINT_COLLECTION', ''));
        $searchKey = $handleEnv !== '' ? $handleEnv : ($collectionEnv !== '' ? $collectionEnv : null);
        if (!$searchKey) {
            throw new \RuntimeException('Set either NEXTPRINT_COLLECTION_HANDLE or NEXTPRINT_COLLECTION in .env');
        }

        $collectionId = $this->findCollectionIdByHandleOrTitle($searchKey);
        if (!$collectionId) {
            Log::info('SHOPIFY SERVICE - no collection id found for', ['search' => $searchKey]);
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
            $resp->throw();
            $data = $resp->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                // normalize id
                $shopifyId = self::gidToId($product['id'] ?? $product['id'] ?? null);
                if (!$shopifyId && !empty($product['id'])) {
                    // sometimes rest returns numeric string
                    $shopifyId = is_numeric($product['id']) ? (int)$product['id'] : null;
                }

                // collect possible price values
                $variantPrices = [];
                if (!empty($product['variants']) && is_array($product['variants'])) {
                    foreach ($product['variants'] as $v) {
                        if (!empty($v['price']) && is_numeric($v['price']) && (float)$v['price'] >= 0) {
                            $variantPrices[] = (float)$v['price'];
                        } elseif (!empty($v['compare_at_price']) && is_numeric($v['compare_at_price'])) {
                            $variantPrices[] = (float)$v['compare_at_price'];
                        }
                    }
                }

                // priceRangeV2 possibility in some shapes
                if (empty($variantPrices) && !empty($product['priceRangeV2']['minVariantPrice']['amount'])) {
                    $amt = $product['priceRangeV2']['minVariantPrice']['amount'];
                    if (is_numeric($amt)) $variantPrices[] = (float)$amt;
                }

                // fallback top-level
                if (empty($variantPrices)) {
                    if (!empty($product['price']) && is_numeric($product['price'])) {
                        $variantPrices[] = (float)$product['price'];
                    } elseif (!empty($product['variants'][0]['price']) && is_numeric($product['variants'][0]['price'])) {
                        $variantPrices[] = (float)$product['variants'][0]['price'];
                    }
                }

                $minPrice = !empty($variantPrices) ? min($variantPrices) : 0.00;

                // Upsert into products table. Adjust fields to match your schema.
                // IMPORTANT: ensure products table has column `shopify_product_id` (int) in your migrations.
                try {
                    \App\Models\Product::updateOrCreate(
                        ['shopify_product_id' => $shopifyId],
                        [
                            'name' => $product['title'] ?? $product['name'] ?? null,
                            'price' => round($minPrice, 2),
                            'min_price' => round($minPrice, 2),
                            'vendor' => $product['vendor'] ?? null,
                            'raw_response' => json_encode($product),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('SHOPIFY SYNC - DB upsert failed', ['shopify_id' => $shopifyId, 'error' => $e->getMessage()]);
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

        Log::info('SHOPIFY SERVICE - sync complete', ['processed' => $processed, 'collection' => $searchKey]);
        return $processed;
    }

    /**
     * GraphQL-based product fetch by collection handle.
     * Returns array of products (normalized)
     */
    public function productsByCollectionHandle(string $handle, int $perPage = 50): array
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
          images(first: 10) {
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
                        'image' => ['src' => $images[0]['src'] ?? null],
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

        return [];
    }
}
