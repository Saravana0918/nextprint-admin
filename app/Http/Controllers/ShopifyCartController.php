<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            'selected_font'=> 'nullable|string|max:100',
            'text_color'   => 'nullable|string|max:20',
            'preview_data' => 'nullable|string',
        ]);

        $productId = $validated['product_id'];
        $quantity = (int) ($validated['quantity'] ?? 1);
        $variantId = $validated['variant_id'] ?? null;
        $size = $validated['size'] ?? null;
        $shopifyProductId = $validated['shopify_product_id'] ?? null;

        // 1) Try DB lookup if product_variants table exists
        if (empty($variantId) && Schema::hasTable('product_variants')) {
            try {
                $pvQuery = ProductVariant::where('product_id', $productId);
                if (!empty($size)) {
                    $pvQuery->where('option_value', $size);
                }
                $pv = $pvQuery->first();
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
                        if (empty($variantId) && !empty($variants)) {
                            $variantId = $variants[0]['id'];
                        }
                    } else {
                        Log::warning('designer: admin product fetch failed', ['status'=>$resp->status(),'body'=>$resp->body()]);
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

        // Save preview image (optional)
        $previewUrl = null;
        if (!empty($validated['preview_data'])) {
            try {
                $data = preg_replace('/^data:image\/\w+;base64,/', '', $validated['preview_data']);
                $data = str_replace(' ', '+', $data);
                $file = 'preview_' . Str::random(10) . '.png';
                $dir = public_path('previews');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . '/' . $file, base64_decode($data));
                $previewUrl = url('previews/'.$file);
            } catch (\Throwable $e) {
                Log::warning('designer: preview save failed', ['err'=>$e->getMessage()]);
            }
        }

        // Build customization as a JSON/string to attach as a line item property
        $customization = [
            'name' => $validated['name_text'] ?? '',
            'number' => $validated['number_text'] ?? '',
            'font' => $validated['selected_font'] ?? ($validated['font'] ?? ''),
            'color' => $validated['text_color'] ?? ($validated['color'] ?? ''),
            'preview_url' => $previewUrl ?? '',
        ];

        // ---------------- ADMIN Draft Order (recommended) ----------------
        $shop = env('SHOPIFY_STORE');
        $token = env('SHOPIFY_ADMIN_API_TOKEN');

        if (empty($shop) || empty($token)) {
            Log::error('designer: shopify admin credentials missing');
            return back()->withErrors(['shopify' => 'Shopify admin config missing.']);
        }

        $payload = [
            'draft_order' => [
                'line_items' => [[
                    'variant_id' => (int) $variantId,
                    'quantity'   => (int) $quantity,
                    // Shopify draft order properties -> use properties array of name/value
                    'properties' => [
                        ['name' => 'Customization', 'value' => json_encode($customization)],
                    ],
                ]],
                // optionally set note, shipping_address, applied_discount, use_customer_default_address...
                'use_customer_default_address' => true
            ]
        ];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ])->post("https://{$shop}/admin/api/2025-01/draft_orders.json", $payload);

            if (!$response->successful()) {
                Log::error('designer: draft_order_failed', ['status'=>$response->status(), 'body'=>$response->body()]);
                return back()->withErrors(['shopify' => 'Failed to create draft order (admin API).']);
            }

            $draft = $response->json('draft_order') ?? null;
            if (empty($draft) || empty($draft['invoice_url'])) {
                Log::error('designer: draft_no_invoice', ['body'=>$response->body()]);
                return back()->withErrors(['shopify' => 'Draft order created but invoice URL not returned.']);
            }

            Log::info('designer: draft_created', ['draft_id'=>$draft['id'] ?? null, 'invoice'=>$draft['invoice_url'] ?? null]);

            // Redirect the customer to Shopify's invoice/checkout page
            return redirect()->away($draft['invoice_url']);
        } catch (\Throwable $e) {
            Log::error('designer: draft_order_exception', ['err'=>$e->getMessage()]);
            return back()->withErrors(['shopify' => 'Failed to create draft order.']);
        }
    }
}
