<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Product;
use App\Models\ProductVariant;

class ShopifyCartController extends Controller
{
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
            'preview_data' => 'nullable|string',
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'] ?? 1;
        $variantId = $validated['variant_id'] ?? null;
        $size = $validated['size'] ?? null;
        $shopifyProductId = $validated['shopify_product_id'] ?? null;

        // 1) Try DB lookup if product_variants table exists
        if (empty($variantId) && Schema::hasTable('product_variants')) {
            try {
                $query = ProductVariant::where('product_id', $productId);
                if ($size) $query->where('option_value', $size);
                $pv = $query->first();
                if ($pv && !empty($pv->shopify_variant_id)) {
                    $variantId = $pv->shopify_variant_id;
                }
            } catch (\Throwable $e) {
                Log::warning('designer: product_variants lookup failed', ['err'=>$e->getMessage()]);
            }
        }

        // 2) If still no variant -> fetch product from Shopify Admin API and pick variant
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

                        // fallback: first variant
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

        // Optional: save preview image locally (kept from previous flow)
        $previewUrl = null;
        if (!empty($validated['preview_data'])) {
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

        // Build customization array (kept for logging; not sent to storefront cart in this quick flow)
        $custom = [
            'name' => $validated['name_text'] ?? '',
            'number' => $validated['number_text'] ?? '',
            'font' => $validated['font'] ?? '',
            'color' => $validated['color'] ?? '',
            'preview_url' => $previewUrl,
        ];

        // -------- QUICK FLOW: redirect user to storefront cart permalink ----------
        // Set SHOPIFY_STORE_FRONT_URL in .env to your storefront domain (eg. https://nextprint.in)
        $storefront = env('SHOPIFY_STORE_FRONT_URL') ?: env('SHOPIFY_STORE');

        // remove admin. if someone set admin.* accidentally
        if (strpos($storefront, 'admin.') !== false) {
            $storefront = str_replace('admin.', '', $storefront);
        }

        // ensure scheme
        if (!preg_match('#^https?://#', $storefront)) {
            $storefront = 'https://' . $storefront;
        }

        $cartUrl = rtrim($storefront, '/') . '/cart/' . (int)$variantId . ':' . (int)$quantity;

        Log::info('designer: redirecting to storefront cart', [
            'cartUrl' => $cartUrl,
            'variant' => $variantId,
            'qty'     => $quantity,
            'custom'  => $custom,
        ]);

        return redirect()->away($cartUrl);
    }
}
