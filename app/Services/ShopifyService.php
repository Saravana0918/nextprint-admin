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
    // collection name to look for (can set in .env as NEXTPRINT_COLLECTION)
    $collectionName = trim((string) env('NEXTPRINT_COLLECTION', 'Show in NextPrint'));
    if ($collectionName === '') {
        throw new \RuntimeException('NEXTPRINT_COLLECTION not set in .env');
    }

    // helper to find collection id (search both custom_collections and smart_collections)
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
        // no collection found — nothing to sync
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
            // upsert product into local DB (adjust schema fields as needed)
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
