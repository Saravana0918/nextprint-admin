<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductVariant;

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        // Validate incoming fields (product_id required)
        $validated = $request->validate([
            'product_id'         => 'required|integer',
            'shopify_product_id' => 'nullable|string',
            'variant_id'         => 'nullable',
            'size'               => 'nullable|string',
            'quantity'           => 'nullable|integer|min:1',
            'name_text'          => 'nullable|string|max:60',
            'number_text'        => 'nullable|string|max:20',
            'font'               => 'nullable|string|max:100',
            'color'              => 'nullable|string|max:20',
            'preview_url'        => 'nullable|url',
            'preview_data'       => 'nullable|string',
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'] ?? 1;
        $variantId = $validated['variant_id'] ?? null;
        $size = $validated['size'] ?? null;
        $shopifyProductId = $validated['shopify_product_id'] ?? null;

        Log::info('designer: addToCart_called', [
            'product_id' => $productId,
            'size' => $size,
            'variant_id_incoming' => $variantId ? (string)$variantId : null,
            'shopify_product_id' => $shopifyProductId,
        ]);

        // 1) Try DB lookup if product_variants table exists
        if (empty($variantId) && Schema::hasTable('product_variants')) {
            try {
                $pv = ProductVariant::where('product_id', $productId)
                     ->when($size, function ($q) use ($size) {
                         return $q->where('option_value', $size)
                                  ->orWhere('option_value', strtoupper($size))
                                  ->orWhere('option_value', strtolower($size));
                     })->first();
                if ($pv && !empty($pv->shopify_variant_id)) {
                    $variantId = (string) $pv->shopify_variant_id;
                    Log::info('designer: variant_found_db', ['variant' => $variantId]);
                }
            } catch (\Throwable $e) {
                Log::warning('designer: product_variants_lookup_failed', ['err' => $e->getMessage()]);
            }
        }

        // 2) If still no variant -> fetch product from Shopify Admin API and pick variant (fallback)
        if (empty($variantId) && $shopifyProductId) {
            try {
                $shop = env('SHOPIFY_STORE');
                $token = env('SHOPIFY_ADMIN_API_TOKEN');

                Log::info('designer: attempt_admin_fetch', ['shop' => $shop ? $shop : null, 'admin_token_present' => !empty($token)]);

                if ($shop && $token) {
                    $resp = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json'
                    ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

                    Log::info('designer: admin_fetch_response', ['status' => $resp->status(), 'body' => $resp->body()]);

                    if ($resp->successful() && !empty($resp->json('product'))) {
                        $productData = $resp->json('product');
                        $variants = $productData['variants'] ?? [];
                        foreach ($variants as $v) {
                            $opt1 = $v['option1'] ?? '';
                            $title = $v['title'] ?? '';
                            if ($size && (strcasecmp(trim($opt1), trim($size)) === 0 || stripos($title, $size) !== false)) {
                                $variantId = $v['id'];
                                break;
                            }
                        }
                        if (empty($variantId) && !empty($variants)) {
                            $variantId = $variants[0]['id'];
                        }
                        if (!empty($variantId)) {
                            $variantId = (string) $variantId;
                        }
                        Log::info('designer: variant_selected_admin', ['variant' => $variantId]);
                    }
                } else {
                    Log::warning('designer: admin_fetch_skipped_missing_env', ['shop' => $shop, 'token_present' => !empty($token)]);
                }
            } catch (\Throwable $e) {
                Log::warning('designer: shopify_variants_fetch_failed', ['err' => $e->getMessage()]);
            }
        }

        if (empty($variantId)) {
            Log::error('designer: no_variant', ['product_id'=>$productId, 'size'=>$size, 'shopify_product_id'=>$shopifyProductId]);
            // Return JSON error (frontend expects JSON)
            return response()->json(['error' => 'Could not determine a product variant (size).'], 422);
        }

        // Save preview_data to file if present
        $previewUrl = $validated['preview_url'] ?? null;
        if (empty($previewUrl) && !empty($validated['preview_data'])) {
            try {
                $data = preg_replace('/^data:image\/\w+;base64,/', '', $validated['preview_data']);
                $data = str_replace(' ', '+', $data);
                $file = 'preview_' . Str::random(8) . '.png';
                $dir = public_path('previews');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . '/' . $file, base64_decode($data));
                $previewUrl = url('previews/'.$file);
                Log::info('designer: preview_saved', ['url' => $previewUrl]);
            } catch (\Throwable $e) {
                Log::warning('designer: preview_save_failed', ['err' => $e->getMessage()]);
            }
        }

        // Build custom attributes
        $customAttrs = [
            ['key' => 'Name',   'value' => $validated['name_text'] ?? ''],
            ['key' => 'Number', 'value' => $validated['number_text'] ?? ''],
            ['key' => 'Font',   'value' => $validated['font'] ?? ''],
            ['key' => 'Color',  'value' => $validated['color'] ?? ''],
            ['key' => 'PreviewUrl', 'value' => $previewUrl ?? ''],
        ];

        // Shopify Storefront API
        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');

        Log::info('designer: storefront_check', [
            'shop' => $shop ? $shop : null,
            'storefront_present' => !empty($storefrontToken),
        ]);

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront_token_or_shop_missing', ['shop'=>$shop, 'storefront' => !empty($storefrontToken)]);
            return response()->json(['error' => 'Storefront token or shop config missing.'], 500);
        }

        // convert variant id to gid
        $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;

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

        $lineItem = [
            'merchandiseId' => $variantGid,
            'quantity' => (int)$quantity,
            'attributes' => array_map(function($a){
                return ['key'=>$a['key'], 'value'=>$a['value']];
            }, $customAttrs),
        ];

        $variables = [
            'input' => [
                'lines' => [$lineItem]
            ]
        ];

        // Log payload
        Log::info('designer: preparing_cart_create', [
            'variantGid' => $variantGid,
            'quantity' => $quantity,
            'variables' => $variables,
        ]);

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
                return response()->json([
                    'error' => 'cartCreate_userErrors',
                    'details' => $userErrors,
                    'body' => $resp->body()
                ], 500);
            }

            // Extract cart and checkoutUrl
            $cart = data_get($data, 'data.cartCreate.cart');
            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: data_get($cart, 'url') ?: null;

            // log parsed cart for debugging
            Log::info('designer: cartCreate_response_parsed', ['cart' => $cart, 'checkoutUrl' => $checkoutUrl]);

            // If checkoutUrl present -> return it
            if (!empty($checkoutUrl)) {
                return response()->json(['checkoutUrl' => $checkoutUrl]);
            }

            // FALLBACK: if cartCreate didn't return checkoutUrl, build a /cart/{variant}:{qty} URL
            Log::warning('designer: cartCreate_no_url_falling_back', ['variantId' => $variantId ?? null, 'productId' => $productId ?? null]);

            // try get numeric variant id if we have it (strip gid if present)
            $numericVariantId = $variantId ?? null;
            if (is_string($numericVariantId) && str_contains($numericVariantId, '/')) {
                $parts = explode('/', $numericVariantId);
                $numericVariantId = end($parts);
            }

            // if still empty try product_variants table
            if (empty($numericVariantId) && Schema::hasTable('product_variants')) {
                try {
                    $pv = ProductVariant::where('product_id', $productId)
                          ->whereNotNull('shopify_variant_id')
                          ->first();
                    if ($pv) $numericVariantId = $pv->shopify_variant_id;
                } catch (\Throwable $e) {
                    Log::warning('designer: fallback_lookup_failed', ['err' => $e->getMessage()]);
                }
            }

            if (!empty($numericVariantId) && !empty($shop)) {
                $fallback = "https://{$shop}/cart/{$numericVariantId}:{$quantity}";
                Log::info('designer: fallback_cart_url', ['fallback' => $fallback]);
                return response()->json(['checkoutUrl' => $fallback]);
            }

            // final fallback: return error json (and log body)
            Log::error('designer: cartCreate_no_checkout_and_no_fallback', ['body' => $resp->body()]);
            return response()->json(['error'=>'no_checkout_url','body'=>$resp->body() ?? null], 500);

        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'exception', 'msg' => $e->getMessage()], 500);
        }
    }
}
