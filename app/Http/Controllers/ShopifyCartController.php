<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductVariant;

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id'           => 'required|integer',
            'shopify_product_id'   => 'nullable|string',
            'players'              => 'nullable|array',
            'players.*.name'       => 'nullable|string|max:60',
            'players.*.number'     => 'nullable|string|max:20',
            'players.*.size'       => 'nullable|string|max:20',
            'players.*.font'       => 'nullable|string|max:100',
            'players.*.color'      => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable',
            'quantity'             => 'nullable|integer|min:1',
            'preview_data'         => 'nullable|string',
            'preview_url'          => 'nullable|url',
        ]);

        $productId = $validated['product_id'];
        $shopifyProductId = $validated['shopify_product_id'] ?? null;
        $players = $validated['players'] ?? null;
        $fallbackQuantity = $validated['quantity'] ?? 1;

        Log::info('designer: addToCart_called', [
            'product_id' => $productId,
            'players_count' => is_array($players) ? count($players) : 0,
            'shopify_product_id' => $shopifyProductId,
        ]);

        // normalizer: extract numeric id if gid:// present
        $normalizeId = function($id){
            if (empty($id)) return null;
            if (is_string($id) && str_contains($id, 'gid://')) {
                if (preg_match('/(\d+)$/', $id, $m)) return $m[1];
            }
            return (string)$id;
        };

        // helper to resolve variant id (string numeric id)
        $resolveVariant = function($productId, $size = null, $incomingVariant = null, $shopifyProductId = null) use ($normalizeId) {
            // normalize inputs
            $incomingVariant = $normalizeId($incomingVariant);
            $shopifyProductId = $normalizeId($shopifyProductId);

            // prefer incoming variant id
            if (!empty($incomingVariant)) {
                return (string)$incomingVariant;
            }

            // try product_variants DB table if exists
            if (!empty($size) && Schema::hasTable('product_variants')) {
                try {
                    $pv = ProductVariant::where('product_id', $productId)
                        ->where(function($q) use ($size) {
                            $q->where('option_value', $size)
                              ->orWhere('option_value', strtoupper($size))
                              ->orWhere('option_value', strtolower($size));
                        })->whereNotNull('shopify_variant_id')->first();

                    if ($pv && !empty($pv->shopify_variant_id)) {
                        if (preg_match('/(\d+)$/', $pv->shopify_variant_id, $m)) {
                            return (string)$m[1];
                        }
                        return (string)$pv->shopify_variant_id;
                    }
                } catch (\Throwable $e) {
                    Log::warning('designer: product_variants_lookup_failed', ['err'=>$e->getMessage()]);
                }
            }

            // fallback: fetch from Shopify Admin API and pick a variant
            if (!empty($shopifyProductId)) {
                try {
                    $shop = env('SHOPIFY_STORE');
                    $adminToken = env('SHOPIFY_ADMIN_API_TOKEN');
                    if ($shop && $adminToken) {
                        $resp = Http::withHeaders([
                            'X-Shopify-Access-Token' => $adminToken,
                            'Content-Type' => 'application/json'
                        ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

                        if ($resp->successful() && !empty($resp->json('product'))) {
                            $productData = $resp->json('product');
                            $variants = $productData['variants'] ?? [];
                            foreach ($variants as $v) {
                                $opt1 = $v['option1'] ?? '';
                                $title = $v['title'] ?? '';
                                if ($size && (strcasecmp(trim($opt1), trim($size)) === 0 || stripos($title, $size) !== false)) {
                                    return (string)$v['id'];
                                }
                            }
                            if (!empty($variants)) return (string)$variants[0]['id'];
                        } else {
                            Log::warning('designer: admin_fetch_unexpected', [
                                'product' => $shopifyProductId,
                                'status' => $resp->status(),
                                'body' => substr($resp->body(), 0, 1000)
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('designer: admin_fetch_failed', ['err'=>$e->getMessage()]);
                }
            }

            return null;
        };

        $lines = [];

        if (is_array($players) && count($players) > 0) {
            foreach ($players as $pl) {
                $size = $pl['size'] ?? null;
                $incomingVariant = $pl['variant_id'] ?? null;

                $variantId = $resolveVariant($productId, $size, $incomingVariant, $shopifyProductId);

                if (empty($variantId)) {
                    Log::warning('designer: no_variant_for_player', ['player' => $pl, 'product_id' => $productId]);
                    continue;
                }

                $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;

                $attrs = [
                    ['key' => 'Name', 'value' => ($pl['name'] ?? '')],
                    ['key' => 'Number', 'value' => ($pl['number'] ?? '')],
                    ['key' => 'Font', 'value' => ($pl['font'] ?? '')],
                    ['key' => 'Color', 'value' => ($pl['color'] ?? '')],
                ];

                $lines[] = [
                    'merchandiseId' => $variantGid,
                    'quantity' => 1,
                    'attributes' => array_map(fn($a) => ['key' => $a['key'], 'value' => $a['value']], $attrs),
                ];
            }
        }

        if (empty($lines)) {
            $singleVariant = $resolveVariant($productId, $request->input('size', null), null, $shopifyProductId);
            if (empty($singleVariant)) {
                Log::error('designer: no_variant_any', ['product_id' => $productId, 'shopify_product_id' => $shopifyProductId]);
                return response()->json(['error' => 'Could not determine any product variant (size).'], 422);
            }
            $lines[] = [
                'merchandiseId' => 'gid://shopify/ProductVariant/' . (string)$singleVariant,
                'quantity' => (int)$fallbackQuantity,
                'attributes' => [
                    ['key' => 'Name', 'value' => $request->input('name_text', '')],
                    ['key' => 'Number', 'value' => $request->input('number_text', '')],
                    ['key' => 'Font', 'value' => $request->input('font', '')],
                    ['key' => 'Color', 'value' => $request->input('color', '')],
                ],
            ];
        }

        $mutation = <<<'GRAPHQL'
mutation cartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
      createdAt
      updatedAt
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $variables = ['input' => ['lines' => $lines]];

        Log::info('designer: preparing_cart_create', [
            'lines' => $lines,
            'players_count' => count($lines),
            'product_id' => $productId,
            'shopify_product_id' => $shopifyProductId,
            'shop_env' => env('SHOPIFY_STORE'),
        ]);

        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront_token_missing', ['shop' => $shop]);
            return response()->json(['error' => 'Storefront token or shop missing'], 500);
        }

        try {
            $endpoint = "https://{$shop}/api/2024-10/graphql.json";
            Log::info('designer: cartCreate_request', ['endpoint' => $endpoint, 'variables' => $variables]);

            $resp = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => $storefrontToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

            Log::info('designer: cartCreate_response', ['status' => $resp->status(), 'body' => substr($resp->body(), 0, 2000)]);

            if (!$resp->successful()) {
                return response()->json(['error' => 'cartCreate_failed', 'status' => $resp->status(), 'body' => $resp->body()], 500);
            }

            $data = $resp->json();
            $userErrors = data_get($data, 'data.cartCreate.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('designer: cartCreate_userErrors', ['errors' => $userErrors, 'body' => $resp->body()]);
                // return errors to client to help debugging
                return response()->json(['error' => 'cartCreate_userErrors', 'details' => $userErrors, 'body' => $resp->body()], 422);
            }

            $cart = data_get($data, 'data.cartCreate.cart');
            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: null;

            if (!empty($checkoutUrl)) {
                return response()->json(['checkoutUrl' => $checkoutUrl]);
            }

            // fallback to numeric cart url
            $firstLine = $lines[0] ?? null;
            if ($firstLine) {
                $gid = $firstLine['merchandiseId'];
                $numeric = $gid;
                if (is_string($numeric) && preg_match('/(\d+)$/', $numeric, $m)) {
                    $numeric = $m[1];
                }
                if (!empty($numeric)) {
                    $fallback = "https://{$shop}/cart/{$numeric}:{$firstLine['quantity']}";
                    return response()->json(['checkoutUrl' => $fallback]);
                }
            }

            return response()->json(['error' => 'no_checkout_url', 'body' => $resp->body()], 500);
        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'exception', 'msg' => $e->getMessage()], 500);
        }
    }
}
