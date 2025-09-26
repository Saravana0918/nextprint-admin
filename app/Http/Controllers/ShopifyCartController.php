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

        // --- Variant resolve ---
        if (empty($variantId) && Schema::hasTable('product_variants')) {
            try {
                $pv = ProductVariant::where('product_id', $productId)
                     ->when($size, function ($q) use ($size) {
                         return $q->where('option_value', $size);
                     })->first();
                if ($pv && !empty($pv->shopify_variant_id)) {
                    $variantId = $pv->shopify_variant_id;
                }
            } catch (\Throwable $e) {
                Log::warning('designer: product_variants lookup failed', ['err'=>$e->getMessage()]);
            }
        }

        if (empty($variantId) && $shopifyProductId) {
            try {
                $shop = env('SHOPIFY_STORE');
                $token = env('SHOPIFY_ADMIN_API_TOKEN');
                $resp = Http::withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json'
                ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

                if ($resp->successful() && !empty($resp->json('product'))) {
                    foreach ($resp->json('product.variants', []) as $v) {
                        if ($size && (strcasecmp(trim($v['option1'] ?? ''), trim($size)) === 0)) {
                            $variantId = $v['id'];
                            break;
                        }
                    }
                    if (empty($variantId) && !empty($resp->json('product.variants.0.id'))) {
                        $variantId = $resp->json('product.variants.0.id');
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('designer: shopify variants fetch failed', ['err'=>$e->getMessage()]);
            }
        }

        if (empty($variantId)) {
            Log::error('designer: no_variant', ['product_id'=>$productId, 'size'=>$size, 'shopify_product_id'=>$shopifyProductId]);
            return back()->withErrors(['variant' => 'Could not determine a product variant (size).']);
        }

        // --- Preview save ---
        $previewUrl = $validated['preview_url'] ?? null;
        if (empty($previewUrl) && !empty($validated['preview_data'])) {
            try {
                $data = preg_replace('/^data:image\/\w+;base64,/', '', $validated['preview_data']);
                $file = 'preview_' . Str::random(8) . '.png';
                $dir = public_path('previews');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . '/' . $file, base64_decode($data));
                $previewUrl = url('previews/'.$file);
            } catch (\Throwable $e) {
                Log::warning('designer: preview save failed', ['err'=>$e->getMessage()]);
            }
        }

        $customAttrs = [
            ['key' => 'Name',   'value' => $validated['name_text'] ?? ''],
            ['key' => 'Number', 'value' => $validated['number_text'] ?? ''],
            ['key' => 'Font',   'value' => $validated['font'] ?? ''],
            ['key' => 'Color',  'value' => $validated['color'] ?? ''],
            ['key' => 'PreviewUrl', 'value' => $previewUrl ?? ''],
        ];

        // --- Shopify Storefront API ---
        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront token or shop missing');
            return response()->json(['error' => 'Storefront token or shop config missing'], 500);
        }

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

        $variables = [
            'input' => [
                'lines' => [[
                    'merchandiseId' => $variantGid,
                    'quantity' => (int)$quantity,
                    'attributes' => $customAttrs,
                ]]
            ]
        ];

        try {
            Log::info('designer: cartCreate_request', ['variables' => $variables]);

            $endpoint = "https://{$shop}/api/2024-10/graphql.json";
            $resp = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => $storefrontToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

            Log::info('designer: cartCreate_response', ['status' => $resp->status(), 'body' => $resp->body()]);

            if (!$resp->successful()) {
                return response()->json(['error' => 'cartCreate_failed', 'status' => $resp->status(), 'body' => $resp->body()], 500);
            }

            $data = $resp->json();
            $cart = data_get($data, 'data.cartCreate.cart');
            $userErrors = data_get($data, 'data.cartCreate.userErrors', []);

            if (!empty($userErrors)) {
                return response()->json(['error' => 'cartCreate_userErrors', 'details' => $userErrors], 500);
            }

            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: data_get($cart, 'url');
            if (empty($checkoutUrl)) {
                return response()->json(['error' => 'no_checkout_url', 'body' => $resp->body()], 500);
            }

            // ✅ Success
            return response()->json(['checkoutUrl' => $checkoutUrl]);

        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err'=>$e->getMessage()]);
            return response()->json(['error' => 'exception', 'msg' => $e->getMessage()], 500);
        }
    }
}
