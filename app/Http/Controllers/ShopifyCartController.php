<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductVariant;
use App\Models\Product;

class ShopifyCartController extends Controller
{
    /**
     * Accepts either:
     * - single product payload (product_id, variant_id or size) OR
     * - players: array of players [{ number, name, size, font, color, variant_id? }]
     *
     * Returns JSON: { checkoutUrl } on success or { error } on failure.
     */
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id'         => 'required|integer',
            'shopify_product_id' => 'nullable|string',
            'variant_id'         => 'nullable',
            'size'               => 'nullable|string',
            'quantity'           => 'nullable|integer|min:1',
            'name_text'          => 'nullable|string|max:100',
            'number_text'        => 'nullable|string|max:50',
            'font'               => 'nullable|string|max:100',
            'color'              => 'nullable|string|max:20',
            'preview_url'        => 'nullable|url',
            'preview_data'       => 'nullable|string',

            // Accept players array from TeamController
            'players'            => 'nullable|array',
            'players.*.name'     => 'nullable|string|max:60',
            'players.*.number'   => 'nullable|string|max:10',
            'players.*.size'     => 'nullable|string|max:20',
            'players.*.font'     => 'nullable|string|max:100',
            'players.*.color'    => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable',
        ]);

        $productId = $validated['product_id'];
        $shopifyProductId = $validated['shopify_product_id'] ?? null;
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');
        $shop = env('SHOPIFY_STORE');

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront_token_or_shop_missing', ['shop'=>$shop, 'storefront' => !empty($storefrontToken)]);
            return response()->json(['error' => 'Storefront token or shop config missing.'], 500);
        }

        // Helper: find numeric variant id given productId + size or provided variant_id
        $resolveVariantId = function($productId, $size = null, $incomingVariant = null, $shopifyProductId = null) use ($shop) {
            // 1) if incoming variant provided, return it
            if (!empty($incomingVariant)) return $incomingVariant;

            // 2) try DB lookup table product_variants
            if (Schema::hasTable('product_variants')) {
                try {
                    $pvQuery = \App\Models\ProductVariant::where('product_id', $productId);
                    if (!empty($size)) $pvQuery->where('option_value', $size);
                    $pv = $pvQuery->first();
                    if ($pv && !empty($pv->shopify_variant_id)) return $pv->shopify_variant_id;
                } catch (\Throwable $e) {
                    Log::warning('designer: variant_lookup_error', ['err'=>$e->getMessage()]);
                }
            }

            // 3) fallback: query Shopify Admin API for product variants if shopifyProductId present
            if (!empty($shopifyProductId)) {
                try {
                    $token = env('SHOPIFY_ADMIN_API_TOKEN');
                    if ($shop && $token) {
                        $resp = Http::withHeaders([
                            'X-Shopify-Access-Token' => $token,
                            'Content-Type' => 'application/json'
                        ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");
                        if ($resp->successful() && !empty($resp->json('product'))) {
                            $variants = $resp->json('product.variants') ?? [];
                            foreach ($variants as $v) {
                                $opt1 = $v['option1'] ?? '';
                                $title = $v['title'] ?? '';
                                if (!empty($size) && (strcasecmp(trim($opt1), trim($size)) === 0 || stripos($title, $size) !== false)) {
                                    return $v['id'];
                                }
                            }
                            // if still nothing, return first
                            if (!empty($variants)) return $variants[0]['id'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('designer: admin_fetch_failed', ['err'=>$e->getMessage()]);
                }
            }

            return null;
        };

        // Build lines: either from players[] or from single payload
        $lines = [];
        if (!empty($validated['players']) && is_array($validated['players'])) {
            foreach ($validated['players'] as $p) {
                $size = $p['size'] ?? null;
                $variantId = $resolveVariantId($productId, $size, $p['variant_id'] ?? null, $shopifyProductId);
                if (empty($variantId)) {
                    Log::warning('designer: no_variant_for_player', ['product'=>$productId, 'player'=>$p]);
                    // skip player or return error — here we return error
                    return response()->json(['error' => 'Could not determine variant for a player (size)'], 422);
                }
                $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;
                $attrs = [
                    ['key' => 'Name', 'value' => $p['name'] ?? ''],
                    ['key' => 'Number', 'value' => $p['number'] ?? ''],
                    ['key' => 'Font', 'value' => $p['font'] ?? ($validated['font'] ?? '')],
                    ['key' => 'Color', 'value' => $p['color'] ?? ($validated['color'] ?? '')],
                ];
                $lines[] = [
                    'merchandiseId' => $variantGid,
                    'quantity' => 1,
                    'attributes' => $attrs,
                ];
            }
        } else {
            // single product flow (existing)
            $quantity = $validated['quantity'] ?? 1;
            $size = $validated['size'] ?? null;
            $variantId = $resolveVariantId($productId, $size, $validated['variant_id'] ?? null, $shopifyProductId);
            if (empty($variantId)) {
                Log::error('designer: no_variant_single', ['product_id'=>$productId, 'size'=>$size]);
                return response()->json(['error' => 'Could not determine a product variant (size).'], 422);
            }
            $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;
            $customAttrs = [
                ['key' => 'Name',   'value' => $validated['name_text'] ?? ''],
                ['key' => 'Number', 'value' => $validated['number_text'] ?? ''],
                ['key' => 'Font',   'value' => $validated['font'] ?? ''],
                ['key' => 'Color',  'value' => $validated['color'] ?? ''],
            ];
            $lines[] = [
                'merchandiseId' => $variantGid,
                'quantity' => (int)$quantity,
                'attributes' => $customAttrs,
            ];
        }

        // Build GraphQL mutation
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

        $endpoint = "https://{$shop}/api/2024-10/graphql.json";

        try {
            Log::info('designer: cartCreate_request', ['endpoint' => $endpoint, 'variables' => $variables]);

            $resp = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => $storefrontToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

            Log::info('designer: cartCreate_response', ['status' => $resp->status(), 'body' => $resp->body()]);

            if (!$resp->successful()) {
                return response()->json([
                    'error' => 'cartCreate_failed',
                    'status' => $resp->status(),
                    'body' => $resp->body()
                ], 500);
            }

            $data = $resp->json();
            $userErrors = data_get($data, 'data.cartCreate.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('designer: cartCreate_userErrors', ['errors' => $userErrors, 'body' => $resp->body()]);
                return response()->json(['error'=>'cartCreate_userErrors','details'=>$userErrors,'body'=>$resp->body()], 500);
            }

            $cart = data_get($data, 'data.cartCreate.cart');
            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: null;

            // If checkout URL not returned, fallback to /cart/{variantId}:{qty} for first line
            if (empty($checkoutUrl)) {
                Log::warning('designer: cartCreate_no_url_falling_back', ['lines'=>$lines]);
                // try build fallback using first numeric variant id
                $first = $lines[0] ?? null;
                if ($first && !empty($first['merchandiseId'])) {
                    $gid = $first['merchandiseId'];
                    // extract trailing numeric id
                    if (is_string($gid)) {
                        $parts = explode('/', $gid);
                        $numeric = end($parts);
                        $fallback = "https://{$shop}/cart/{$numeric}:{$first['quantity']}";
                        return response()->json(['checkoutUrl' => $fallback]);
                    }
                }
                return response()->json(['error'=>'no_checkout_url','body'=>$resp->body() ?? null], 500);
            }

            return response()->json(['checkoutUrl' => $checkoutUrl]);

        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'exception', 'msg' => $e->getMessage()], 500);
        }
    }
}
