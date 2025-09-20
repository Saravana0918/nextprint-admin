<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
        $perPage = 250;
        $processed = 0;
        $pageInfo = null;
        $url = "{$this->base}/admin/api/{$this->version}/products.json";

        // tags to match (lowercase). You can change or extend this list as needed.
        $matchTags = [
            'show in nextprint',
            'customized',
        ];

        while (true) {
            $params = ['limit' => $perPage];
            if ($pageInfo) {
                $params['page_info'] = $pageInfo;
            }

            $resp = Http::withHeaders([
                        'X-Shopify-Access-Token' => $this->token,
                    ])->get($url, $params);

            $resp->throw();
            $data = $resp->json();
            $products = $data['products'] ?? [];

            foreach ($products as $product) {
                // Tags may be a comma-separated string
                $tags = [];
                if (!empty($product['tags'])) {
                    $tags = array_map('trim', explode(',', $product['tags']));
                }

                // make tags lowercase for case-insensitive comparison
                $lower = array_map('strtolower', $tags);

                // if none of the configured matchTags are present, skip
                $found = false;
                foreach ($matchTags as $mt) {
                    if (in_array($mt, $lower, true)) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }

                // Upsert into local products table (adjust fields to your schema)
                \App\Models\Product::updateOrCreate(
                    ['shopify_product_id' => $product['id']],
                    [
                        'name'   => $product['title'] ?? null,
                        'price'  => $product['variants'][0]['price'] ?? 0,
                        'vendor' => $product['vendor'] ?? null,
                        'status' => $product['status'] ?? 'active',
                        // add other mappings as needed
                    ]
                );

                $processed++;
            }

            // Cursor-based pagination: parse Link header for rel="next"
            $linkHeader = $resp->header('Link'); // may be null
            $nextPageInfo = null;
            if ($linkHeader && preg_match('/<[^>]+[?&]page_info=([^&>]+)[^>]*>;\s*rel="next"/', $linkHeader, $m)) {
                $nextPageInfo = $m[1];
            }

            if (!$nextPageInfo) {
                break; // no more pages
            }

            $pageInfo = $nextPageInfo;
        }

        return $processed;
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
