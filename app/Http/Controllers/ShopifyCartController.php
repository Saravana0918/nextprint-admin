<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductVariant; // if present

class ShopifyCartController extends Controller
{
    // Endpoint: POST /designer/upload-preview
    // Accepts: preview_data (base64 image)
    // Returns: JSON { success: true, url: '/previews/preview_xxx.png' } on success
    public function uploadPreview(Request $request)
    {
        $request->validate([
            'preview_data' => 'required|string',
        ]);

        $data = $request->input('preview_data');

        try {
            // strip prefix if provided
            $payload = preg_replace('/^data:image\/\w+;base64,/', '', $data);
            $payload = str_replace(' ', '+', $payload);

            $fileName = 'preview_' . Str::random(10) . '.png';
            $dir = public_path('previews');

            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Cannot create previews directory");
                }
            }

            $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
            file_put_contents($filePath, base64_decode($payload));
            // ensure file is readable by web: chmod 0644
            @chmod($filePath, 0644);

            $url = url('previews/' . $fileName);
            return response()->json(['success' => true, 'url' => $url]);
        } catch (\Throwable $e) {
            Log::error('designer: uploadPreview_failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Preview save failed'], 500);
        }
    }

    // Existing addToCart: expects preview_url OR preview_data already uploaded
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id'   => 'required|integer',
            'shopify_product_id' => 'nullable|string',
            'variant_id'   => 'nullable',
            'size'         => 'nullable|string',
            'quantity'     => 'nullable|integer|min:1',
            'name_text'    => 'nullable|string|max:40',
            'number_text'  => 'nullable|string|max:6',
            'font'         => 'nullable|string|max:100',
            'color'        => 'nullable|string|max:20',
            'preview_url'  => 'nullable|url',
            // if you used preview_data directly previously, prefer uploadPreview flow
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'] ?? 1;
        $variantId = $validated['variant_id'] ?? null;
        $size = $validated['size'] ?? null;
        $shopifyProductId = $validated['shopify_product_id'] ?? null;

        // Step 1: db lookup (if table exists)
        if (empty($variantId) && Schema::hasTable('product_variants')) {
            try {
                $pvQuery = ProductVariant::where('product_id', $productId);
                if ($size) $pvQuery->where('option_value', $size);
                $pv = $pvQuery->first();
                if ($pv && !empty($pv->shopify_variant_id)) {
                    $variantId = $pv->shopify_variant_id;
                }
            } catch (\Throwable $e) {
                Log::warning('designer: product_variants lookup failed', ['err' => $e->getMessage()]);
            }
        }

        // Step 2: fetch from Shopify Admin API if needed (fallback)
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
                Log::warning('designer: shopify variants fetch failed', ['err' => $e->getMessage()]);
            }
        }

        if (empty($variantId)) {
            Log::error('designer: no_variant', ['product_id' => $productId, 'size' => $size, 'shopify_product_id' => $shopifyProductId]);
            return back()->withErrors(['variant' => 'Could not determine a product variant (size).']);
        }

        // Build custom attributes
        $customAttrs = [
            ['key' => 'Name', 'value' => $validated['name_text'] ?? ''],
            ['key' => 'Number', 'value' => $validated['number_text'] ?? ''],
            ['key' => 'Font', 'value' => $validated['font'] ?? ''],
            ['key' => 'Color', 'value' => $validated['color'] ?? ''],
            ['key' => 'PreviewUrl', 'value' => $validated['preview_url'] ?? ''],
        ];

        // Use Storefront API to create checkout and redirect user to checkout URL
        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');

        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront token or shop missing');
            return back()->withErrors(['shopify' => 'Storefront token or shop config missing.']);
        }

        // variant gid
        $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;

        $mutation = <<<'GRAPHQL'
mutation checkoutCreate($input: CheckoutCreateInput!) {
  checkoutCreate(input: $input) {
    checkout {
      id
      webUrl
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $lineItem = [
            'variantId' => $variantGid,
            'quantity'  => (int)$quantity,
            'customAttributes' => array_map(function ($a) {
                return ['key' => $a['key'], 'value' => $a['value']];
            }, $customAttrs),
        ];

        $variables = [
            'input' => [
                'lineItems' => [$lineItem],
            ],
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
                Log::error('designer: checkoutCreate_failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return back()->withErrors(['shopify' => 'Failed to create checkout (storefront API).']);
            }

            $data = $resp->json();
            $webUrl = data_get($data, 'data.checkoutCreate.checkout.webUrl');

            if (empty($webUrl)) {
                Log::error('designer: checkoutCreate_no_weburl', ['body' => $resp->body()]);
                return back()->withErrors(['shopify' => 'Checkout created but no webUrl returned.']);
            }

            return redirect()->away($webUrl);
        } catch (\Throwable $e) {
            Log::error('designer: checkoutCreate_exception', ['err' => $e->getMessage()]);
            return back()->withErrors(['shopify' => 'Checkout creation failed.']);
        }
    }
}
