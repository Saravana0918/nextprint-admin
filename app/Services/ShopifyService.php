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
     * This uses the REST products.json endpoint and filters by tags (client-side).
     * Returns number of processed products.
     */
    public function syncNextprintToLocal(): int
    {
        $perPage = 250;
        $page = 1;
        $processed = 0;

        // Use REST endpoint (products.json) for simple tag-based filtering client-side.
        do {
            $url = "{$this->base}/admin/api/{$this->version}/products.json";
            $resp = Http::withHeaders([
                        'X-Shopify-Access-Token' => $this->token,
                    ])
                    ->get($url, ['limit' => $perPage, 'page' => $page]);

            $resp->throw();
            $data = $resp->json();

            $products = $data['products'] ?? [];

            foreach ($products as $product) {
    // Tags may be a comma-separated string
    $tags = [];
    if (!empty($product['tags'])) {
        $tags = array_map('trim', explode(',', $product['tags']));
    }

    // Filter: only sync products with the tag "Show in NextPrint" (case-insensitive)
    $lower = array_map('strtolower', $tags);
    if (!in_array('show in nextprint', $lower, true)) {
        continue;
    }

    // Upsert into local products table
    \App\Models\Product::updateOrCreate(
        ['shopify_product_id' => $product['id']],
        [
            'name'   => $product['title'] ?? null,
            'price'  => $product['variants'][0]['price'] ?? 0,
            'vendor' => $product['vendor'] ?? null,
            'status' => $product['status'] ?? 'active',
        ]
    );

    $processed++;
}


            // if fewer than perPage results, that's the last page
            $done = count($products) < $perPage;
            $page++;
        } while (!$done);

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
