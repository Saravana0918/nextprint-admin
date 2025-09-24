<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\ProductVariant; // if you have this model

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        // Validate basic inputs
        $validated = $request->validate([
            'product_id'   => 'required|integer',
            'shopify_product_id' => 'nullable',
            'variant_id'   => 'nullable',
            'size'         => 'nullable|string',
            'quantity'     => 'nullable|integer|min:1',
            'name_text'    => 'nullable|string|max:20',
            'number_text'  => 'nullable|string|max:5',
            'font'         => 'nullable|string|max:100',
            'color'        => 'nullable|string|max:20',
            'preview_data' => 'nullable|string', // base64
        ]);

        $productId = $validated['product_id'];
        $quantity = $validated['quantity'] ?? 1;
        $variantId = $validated['variant_id'] ?? null;

        // If variant_id not provided, try to resolve by size -> product_variants table
        if (empty($variantId) && !empty($validated['size'])) {
            $size = $validated['size'];
            $pv = ProductVariant::where('product_id', $productId)
                    ->where('option_value', $size)
                    ->first();
            if ($pv && !empty($pv->shopify_variant_id)) {
                $variantId = $pv->shopify_variant_id;
            }
        }

        // if still no variantId, attempt to pick default product variant
        if (empty($variantId)) {
            // Try to pick any variant from DB
            $pv = ProductVariant::where('product_id', $productId)->first();
            if ($pv && !empty($pv->shopify_variant_id)) {
                $variantId = $pv->shopify_variant_id;
            }
        }

        if (empty($variantId)) {
            Log::error('designer: addToCart missing variant', ['product_id'=>$productId, 'size'=>$validated['size'] ?? null]);
            return back()->withErrors(['variant' => 'Could not determine variant. Please contact support.']);
        }

        // Save preview image (optional)
        $previewUrl = null;
        if (!empty($validated['preview_data'])) {
            try {
                $imageData = $validated['preview_data'];
                $image = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                $image = str_replace(' ', '+', $image);
                $fileName = 'preview_' . Str::random(10) . '.png';
                $dir = public_path('previews');
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
                file_put_contents($filePath, base64_decode($image));
                $previewUrl = url('previews/' . $fileName);
            } catch (\Throwable $e) {
                Log::warning('designer: preview save failed', ['err'=>$e->getMessage()]);
            }
        }

        // Build customization properties as JSON or as Shopify line item properties
        $customization = [
            'name' => $validated['name_text'] ?? '',
            'number' => $validated['number_text'] ?? '',
            'font' => $validated['font'] ?? '',
            'color' => $validated['color'] ?? '',
            'preview_url' => $previewUrl,
        ];

        // Shopify Draft Order creation
        $shop = env('SHOPIFY_STORE'); // e.g. your-store.myshopify.com
        $token = env('SHOPIFY_ADMIN_API_TOKEN');

        if (empty($shop) || empty($token)) {
            Log::error('designer: shop/token missing', ['shop'=>$shop ? 'yes' : 'no']);
            return back()->withErrors(['shopify' => 'Shopify credentials missing on server.']);
        }

        $payload = [
            'draft_order' => [
                'line_items' => [[
                    'variant_id' => (int) $variantId,
                    'quantity' => (int) $quantity,
                    'properties' => [
                        ['name' => 'Customization', 'value' => json_encode($customization)]
                    ]
                ]],
                'use_customer_default_address' => true
            ]
        ];

        Log::info('designer: creating draft order', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'qty' => $quantity,
            'shop' => $shop
        ]);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json'
        ])->post("https://{$shop}/admin/api/2025-01/draft_orders.json", $payload);

        if (!$response->successful()) {
            Log::error('designer: draft_order_failed', ['status'=>$response->status(), 'body'=>$response->body()]);
            return back()->withErrors(['shopify' => 'Failed to create draft order.']);
        }

        $draft = $response->json('draft_order') ?? null;
        if (empty($draft) || empty($draft['invoice_url'])) {
            Log::error('designer: draft_order_no_invoice', ['body'=>$response->body()]);
            return back()->withErrors(['shopify' => 'Draft order created but invoice missing.']);
        }

        // redirect to invoice checkout
        return redirect()->away($draft['invoice_url']);
    }
}
