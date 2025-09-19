<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ShopifyService
{
    protected string $shopHandle;   // e.g. "yogireddy" (no protocol, no .myshopify.com)
    protected string $token;
    protected string $version;
    protected string $baseUrl;      // e.g. https://yogireddy.myshopify.com

    public function __construct()
    {
        // Read from .env
        $rawStore = trim((string) env('SHOPIFY_STORE', ''));
        $this->token = trim((string) env('SHOPIFY_ADMIN_API_TOKEN', ''));
        $this->version = env('SHOPIFY_API_VERSION', '2024-10');

        if ($rawStore === '') {
            throw new RuntimeException('SHOPIFY_STORE not set in .env');
        }
        if ($this->token === '') {
            throw new RuntimeException('SHOPIFY_ADMIN_API_TOKEN not set in .env');
        }

        // Accept either "handle" or "handle.myshopify.com" or full url
        $handle = $rawStore;
        // remove protocol if present
        $handle = preg_replace('#^https?://#i', '', $handle);
        // remove trailing slash
        $handle = rtrim($handle, '/');

        // if input contains ".myshopify.com" remove that
        if (Str::contains($handle, '.myshopify.com')) {
            $handle = preg_replace('/\.myshopify\.com$/i', '', $handle);
        }

        $this->shopHandle = $handle;
        $this->baseUrl = 'https://' . $this->shopHandle . '.myshopify.com';
    }

    /**
     * Low-level GraphQL caller (keeps your existing GraphQL helpers)
     */
    private function gql(string $query, array $variables = []): array
    {
        $url = "{$this->baseUrl}/admin/api/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Accept' => 'application/json',
        ])
            // ->withOptions(['verify' => base_path('storage/certs/cacert.pem')]) // optional
            ->post($url, [
                'query' => $query,
                'variables' => $variables,
            ])
            ->throw()
            ->json();

        if (isset($resp['errors'])) {
            throw new RuntimeException(json_encode($resp['errors']));
        }

        return $resp['data'] ?? [];
    }

    /**
     * (Legacy) Fetch by collection handle via GraphQL
     */
    public function productsByCollectionHandle(string $handle): \Generator
    {
        $query = <<<'GQL'
        query($handle: String!, $cursor: String) {
          collectionByHandle(handle: $handle) {
            products(first: 100, after: $cursor) {
              pageInfo { hasNextPage endCursor }
              edges {
                node {
                  id
                  title
                  handle
                  vendor
                  status
                  featuredMedia { preview { image { url } } }
                  media(first: 5) { edges { node { ... on MediaImage { image { url } } } } }
                  featuredImage { url }
                  images(first: 1) { edges { node { url } } }
                  priceRangeV2 { minVariantPrice { amount } }
                }
              }
            }
          }
        }
        GQL;

        $cursor = null;
        do {
            $data = $this->gql($query, ['handle' => $handle, 'cursor' => $cursor]);
            $col = $data['collectionByHandle'] ?? null;
            if (!$col) break;

            foreach (($col['products']['edges'] ?? []) as $e) {
                yield $e['node'];
            }

            $pi = $col['products']['pageInfo'] ?? [];
            $cursor = !empty($pi['hasNextPage']) ? ($pi['endCursor'] ?? null) : null;
        } while ($cursor);
    }

    /**
     * Fetch products that have a metafield nextprint.show true (GraphQL)
     */
    public function productsByNextprintFlag(bool $onlyActive = true): \Generator
    {
        $queryString = ($onlyActive ? 'status:active ' : '') . 'metafield:nextprint.show:true';

        $gql = <<<'GQL'
        query($query: String!, $after: String) {
          products(first: 100, query: $query, after: $after) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                title
                handle
                vendor
                status
                featuredMedia { preview { image { url } } }
                media(first: 5) { edges { node { ... on MediaImage { image { url } } } } }
                featuredImage { url }
                images(first: 1) { edges { node { url } } }
                priceRangeV2 { minVariantPrice { amount } }
              }
            }
          }
        }
        GQL;

        $after = null;
        do {
            $data = $this->gql($gql, ['query' => $queryString, 'after' => $after]);
            foreach (($data['products']['edges'] ?? []) as $e) {
                yield $e['node'];
            }

            $pi = $data['products']['pageInfo'] ?? [];
            $after = !empty($pi['hasNextPage']) ? ($pi['endCursor'] ?? null) : null;
        } while ($after);
    }

    /**
     * Sync all NextPrint-eligible products to local DB (shopify_product_id on products table).
     * Filters using the tag "Show in NextPrint" by default.
     *
     * Returns number of upserts performed (approx; counts processed products from REST fetch)
     */
    public function syncNextprintToLocal(array $options = []): int
    {
        $limit = $options['limit'] ?? 250; // Shopify REST max 250
        $sinceId = 0;
        $upserts = 0;

        // Use Shopify REST products.json, paginated by since_id
        do {
            $url = "{$this->baseUrl}/admin/api/{$this->version}/products.json";

            $query = [
                'limit' => $limit,
            ];
            if ($sinceId > 0) {
                $query['since_id'] = $sinceId;
            }

            $resp = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Accept' => 'application/json',
            ])
                ->get($url, $query)
                ->throw()
                ->json();

            $products = $resp['products'] ?? [];

            if (empty($products)) break;

            foreach ($products as $product) {
                // Normalize tags: Shopify returns string "tag1, tag2"
                $rawTags = $product['tags'] ?? '';
                $tags = is_array($rawTags) ? $rawTags : array_map('trim', explode(',', $rawTags));

                // Skip if tag "Show in NextPrint" not present
                if (!in_array('Show in NextPrint', $tags, true)) {
                    continue;
                }

                // Upsert into local products table (adjust fields to your schema)
                $shopifyId = (int) ($product['id'] ?? 0);
                if ($shopifyId === 0) continue;

                // Some safe extra fields
                $name = $product['title'] ?? '';
                $vendor = $product['vendor'] ?? null;
                $status = $product['status'] ?? ($product['published_at'] ? 'active' : 'draft');
                $price = 0;
                if (!empty($product['variants'][0]['price'])) {
                    $price = $product['variants'][0]['price'];
                }

                \App\Models\Product::updateOrCreate(
                    ['shopify_product_id' => $shopifyId],
                    [
                        'name' => $name,
                        'price' => $price,
                        'vendor' => $vendor,
                        'status' => $status,
                        // add any other mapping you need here (thumbnail, description, etc)
                        'meta' => json_encode([
                            'handle' => $product['handle'] ?? null,
                            'tags' => $tags,
                        ]),
                    ]
                );

                $upserts++;
            }

            // set since_id to last product id in this page for pagination
            $last = end($products);
            $sinceId = (int) ($last['id'] ?? 0);
            // continue until fewer than limit (no more pages)
            $fewerThanLimit = count($products) < $limit;
        } while (!$fewerThanLimit && $sinceId > 0);

        return $upserts;
    }

    /**
     * Convert Shopify GID to numeric ID (GraphQL IDs)
     */
    public static function gidToId(?string $gid): ?int
    {
        if (!$gid) return null;
        $parts = explode('/', $gid);
        return (int) end($parts);
    }
}
