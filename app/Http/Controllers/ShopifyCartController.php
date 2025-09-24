<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        // 1) Validate inputs strictly
        $validated = $request->validate([
            'product_id'   => 'required|integer',
            'variant_id'   => 'required|numeric',
            'quantity'     => 'nullable|integer|min:1',
            'name_text'    => 'nullable|string|max:20',
            'number_text'  => 'nullable|string|max:3',
            'font'         => 'nullable|string|max:100',
            'color'        => 'nullable|string|max:50',
            'preview_data' => 'required|string', // base64 image
        ]);

        $qty = $validated['quantity'] ?? 1;

        // Validate base64 image and size (limit ~4MB)
        $base64 = $validated['preview_data'];
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $base64, $matches)) {
            return response()->json(['error' => 'Invalid image format. Expecting base64 PNG/JPEG.'], 422);
        }

        $data = preg_replace('#^data:image/\w+;base64,#i', '', $base64);
        $data = str_replace(' ', '+', $data);
        $imageBinary = base64_decode($data, true);

        if ($imageBinary === false) {
            return response()->json(['error' => 'Base64 decode failed.'], 422);
        }

        $sizeInBytes = strlen($imageBinary);
        $maxBytes = 4 * 1024 * 1024;
        if ($sizeInBytes > $maxBytes) {
            return response()->json(['error' => 'Image size too large. Max 4MB allowed.'], 413);
        }

        // Save preview image to storage/public/previews
        try {
            $fileName = 'preview_' . Str::random(12) . '.png';
            $path = 'previews/' . $fileName;
            Storage::disk('public')->put($path, $imageBinary);
            $previewUrl = Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('designer:preview_save_error', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to save preview image.'], 500);
        }

        // Build customization object
        $customization = [
            'name' => $validated['name_text'] ?? null,
            'number' => $validated['number_text'] ?? null,
            'font' => $validated['font'] ?? null,
            'color' => $validated['color'] ?? null,
            'preview_url' => $previewUrl,
        ];

        // Draft order payload
        $shop = env('SHOPIFY_STORE');
        $token = env('SHOPIFY_ADMIN_API_TOKEN');

        if (!$shop || !$token) {
            Log::error('designer:shopify_credentials_missing');
            return response()->json(['error' => 'Shopify credentials not configured.'], 500);
        }

        $customer_email = $request->user()->email ?? $request->input('customer_email') ?? null;

        $draftOrderPayload = [
            'draft_order' => [
                'line_items' => [
                    [
                        'variant_id' => (int)$validated['variant_id'],
                        'quantity' => (int)$qty,
                        'properties' => [
                            ['name' => 'Personalization', 'value' => json_encode($customization)]
                        ],
                    ]
                ],
                'email' => $customer_email,
                'use_customer_default_address' => true,
                'note' => 'Created from NextPrint designer - personalization included.'
            ]
        ];

        // Call Shopify Draft Orders API
        try {
            Log::info('designer:create_draft_order_payload', ['payload' => $draftOrderPayload]);
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ])->post("https://{$shop}/admin/api/2025-01/draft_orders.json", $draftOrderPayload);
        } catch (\Exception $e) {
            Log::error('designer:shopify_request_exception', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to contact Shopify API.'], 500);
        }

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('designer:shopify_failed', ['status' => $response->status(), 'body' => $body]);
            return response()->json([
                'error' => 'Shopify Draft Order creation failed',
                'details' => $response->json()
            ], 500);
        }

        $json = $response->json();
        $draftOrder = $json['draft_order'] ?? null;

        if (!$draftOrder || !isset($draftOrder['invoice_url'])) {
            Log::error('designer:draft_order_missing_invoice', ['response' => $json]);
            return response()->json(['error' => 'Draft order created but invoice_url missing.'], 500);
        }

        Log::info('designer:draft_created', ['draft_id' => $draftOrder['id'], 'invoice' => $draftOrder['invoice_url']]);

        return redirect()->away($draftOrder['invoice_url']);
    }
}
