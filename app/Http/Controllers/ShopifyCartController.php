<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        // 1. Validate inputs
        $validated = $request->validate([
            'product_id'   => 'required',
            'variant_id'   => 'required',
            'name_text'    => 'required|string|max:20',
            'number_text'  => 'required|string|max:3',
            'font'         => 'required|string',
            'color'        => 'required|string',
            'preview_data' => 'required', // base64 image
        ]);

        // 2. Save preview image
        $imageData = $validated['preview_data'];
        $image = str_replace('data:image/png;base64,', '', $imageData);
        $image = str_replace(' ', '+', $image);
        $fileName = 'preview_' . Str::random(10) . '.png';
        $filePath = public_path('previews/' . $fileName);

        file_put_contents($filePath, base64_decode($image));
        $previewUrl = url('previews/' . $fileName);

        // 3. Build metafield value (JSON)
        $customization = [
            'name'        => $validated['name_text'],
            'number'      => $validated['number_text'],
            'font'        => $validated['font'],
            'color'       => $validated['color'],
            'preview_url' => $previewUrl
        ];

        // 4. Shopify API call → Create Draft Order
        $shop = env('SHOPIFY_STORE'); // yogireddy.myshopify.com
        $token = env('SHOPIFY_ADMIN_API_TOKEN');

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Content-Type' => 'application/json'
        ])->post("https://$shop/admin/api/2025-01/draft_orders.json", [
            'draft_order' => [
                'line_items' => [[
                    'variant_id' => $validated['variant_id'],
                    'quantity'   => 1,
                    'properties' => [
                        ['name' => 'Customization', 'value' => json_encode($customization)]
                    ]
                ]],
                'use_customer_default_address' => true
            ]
        ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Shopify Draft Order creation failed',
                'details' => $response->json()
            ], 500);
        }

        $draftOrder = $response->json()['draft_order'];

        // 5. Redirect user to checkout invoice URL
        return redirect()->away($draftOrder['invoice_url']);
    }
}
