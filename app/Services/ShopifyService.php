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
        // .env => SHOPIFY_STORE, SHOPIFY_ADMIN_API_TOKEN, SHOPIFY_API_VERSION
        $this->base    = 'https://' . env('SHOPIFY_STORE') . '/admin/api';
        $this->token   = trim((string) env('SHOPIFY_ADMIN_API_TOKEN'));
        $this->version = env('SHOPIFY_API_VERSION', '2024-07');
    }

    /**
     * Low-level GraphQL caller
     */
    private function gql(string $query, array $variables = []): array
    {
        $url = "{$this->base}/{$this->version}/graphql.json";

        $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->token,
                ])
                // If you prefer project-scoped CA bundle instead of php.ini, uncomment:
                // ->withOptions(['verify' => base_path('storage/certs/cacert.pem')])
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
     * (Legacy) Fetch by collection handle (kept for compatibility)
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

                  # Prefer the actual cover media first
                  featuredMedia { preview { image { url } } }
                  media(first: 5) {
                    edges { node { ... on MediaImage { image { url } } } }
                  }
                  featuredImage { url }                         # legacy fallback
                  images(first: 1) { edges { node { url } } }   # last fallback

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
            $col  = $data['collectionByHandle'] ?? null;
            if (!$col) break;

            foreach (($col['products']['edges'] ?? []) as $e) {
                yield $e['node'];
            }

            $pi     = $col['products']['pageInfo'] ?? [];
            $cursor = !empty($pi['hasNextPage']) ? ($pi['endCursor'] ?? null) : null;
        } while ($cursor);
    }

    /**
     * Fetch products that have the metafield nextprint.show=true
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

                # Modern image fields (prefer these)
                featuredMedia { preview { image { url } } }
                media(first: 5) {
                  edges { node { ... on MediaImage { image { url } } } }
                }
                featuredImage { url }                         # legacy fallback
                images(first: 1) { edges { node { url } } }   # last fallback

                priceRangeV2 { minVariantPrice { amount } }
              }
            }
          }
        }
        GQL;

        $after = null;
        do {
            $data  = $this->gql($gql, ['query' => $queryString, 'after' => $after]);
            foreach (($data['products']['edges'] ?? []) as $e) {
                yield $e['node'];
            }

            $pi    = $data['products']['pageInfo'] ?? [];
            $after = !empty($pi['hasNextPage']) ? ($pi['endCursor'] ?? null) : null;
        } while ($after);
    }

    /**
     * Sync all NextPrint-eligible products to local DB (shopify_products table)
     * Returns number of upserts.
     */
    public function syncNextprintToLocal(): int
    {
        $count = 0;

        foreach ($this->productsByNextprintFlag() as $p) {
            // Pick the REAL product cover first; fallbacks after that
            $img = data_get($p, 'featuredMedia.preview.image.url')
                ?? data_get($p, 'media.edges.0.node.image.url')
                ?? data_get($p, 'featuredImage.url')
                ?? data_get($p, 'images.edges.0.node.url')
                ?? null;

            $min = $p['priceRangeV2']['minVariantPrice']['amount'] ?? null;

            \App\Models\ShopifyProduct::updateOrCreate(
                ['handle' => $p['handle'] ?? null],
                [
                    // If you keep a numeric shopify id column, uncomment:
                    // 'shopify_id' => self::gidToId($p['id'] ?? ''),

                    'title'     => $p['title']  ?? '',
                    'vendor'    => $p['vendor'] ?? '',
                    'status'    => $p['status'] ?? 'ACTIVE',
                    'image_url' => $img,
                    'min_price' => $min,
                ]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Convert Shopify GID to numeric ID (e.g. gid://shopify/Product/123456789 -> 123456789)
     */
    public static function gidToId(?string $gid): ?int
    {
        if (!$gid) return null;
        $parts = explode('/', $gid);
        return (int) end($parts);
    }
}
