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
            'preview_data'       => 'nullable|string', // optional base64
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'] ?? 1;
        $variantId = $validated['variant_id'] ?? null;
        $size = $validated['size'] ?? null;
        $shopifyProductId = $validated['shopify_product_id'] ?? null;

        // 1) Try DB lookup if product_variants table exists
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

        // 2) If still no variant -> fetch product from Shopify Admin API and pick variant (fallback)
        if (empty($variantId) && $shopifyProductId) {
            try {
                $shop = env('SHOPIFY_STORE');
                $token = env('SHOPIFY_ADMIN_API_TOKEN');
                if ($shop && $token) {
                    $resp = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Content-Type' => 'application/json'
                    ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

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

        // Optionally save preview_data to file and generate preview_url
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
            } catch (\Throwable $e) {
                Log::warning('designer: preview save failed', ['err'=>$e->getMessage()]);
            }
        }

        // Build custom attributes (for cart line item)
        $customAttrs = [
            ['key' => 'Name',   'value' => $validated['name_text'] ?? ''],
            ['key' => 'Number', 'value' => $validated['number_text'] ?? ''],
            ['key' => 'Font',   'value' => $validated['font'] ?? ''],
            ['key' => 'Color',  'value' => $validated['color'] ?? ''],
            ['key' => 'PreviewUrl', 'value' => $previewUrl ?? ''],
        ];

        // --- Use Storefront API cartCreate mutation (recommended) ---
        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront token or shop missing');
            return back()->withErrors(['shopify' => 'Storefront token or shop config missing.']);
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
                // Storefront cart attributes use key/value
                return ['key'=>$a['key'], 'value'=>$a['value']];
            }, $customAttrs),
        ];

        $variables = [
            'input' => [
                'lines' => [$lineItem]
            ]
        ];

        try {
            $endpoint = "https://{$shop}/api/2024-10/graphql.json";
            $resp = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => $storefrontToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

            if (!$resp->successful()) {
                Log::error('designer: cartCreate_failed', ['status'=>$resp->status(), 'body'=>$resp->body()]);
                return back()->withErrors(['shopify' => 'Failed to create cart (storefront API).']);
            }

            $data = $resp->json();
            $cart = data_get($data, 'data.cartCreate.cart');
            $userErrors = data_get($data, 'data.cartCreate.userErrors', []);

            if (!empty($userErrors)) {
                Log::error('designer: cartCreate_userErrors', ['errors'=>$userErrors, 'body'=>$resp->body()]);
                return back()->withErrors(['shopify' => 'Storefront error: '.json_encode($userErrors)]);
            }

            // Some API versions return checkoutUrl or webUrl differently - check both
            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: data_get($cart, 'url') ?: null;

            if (empty($checkoutUrl)) {
                Log::error('designer: cartCreate_no_url', ['body'=>$resp->body()]);
                return back()->withErrors(['shopify' => 'Cart created but no checkout URL returned.']);
            }

            // Redirect user to Shopify checkout/cart
            return redirect()->away($checkoutUrl);

        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err'=>$e->getMessage()]);
            return back()->withErrors(['shopify' => 'Checkout creation failed.']);
        }
    }

    public function uploadPreview(Request $request)
{
    $request->validate(['preview_data' => 'required|string']);
    try {
        $data = preg_replace('/^data:image\/\w+;base64,/', '', $request->input('preview_data'));
        $data = str_replace(' ', '+', $data);
        $file = 'preview_' . \Illuminate\Support\Str::random(8) . '.png';
        $dir = public_path('previews');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($dir . '/' . $file, base64_decode($data));
        $url = url('previews/' . $file);
        return response()->json(['url' => $url], 200);
    } catch (\Throwable $e) {
        \Log::error('designer: upload_preview_failed', ['err' => $e->getMessage()]);
        return response()->json(['error' => 'Upload failed'], 500);
    }
}
}
