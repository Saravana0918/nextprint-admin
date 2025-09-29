<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Models\Team;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Http\Controllers\ShopifyCartController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TeamController extends Controller
{
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = null;
        if ($productId) {
            $product = Product::find($productId);
        }

        $prefill = [
            'name'   => $request->query('prefill_name', ''),
            'number' => $request->query('prefill_number', ''),
            'font'   => $request->query('prefill_font', ''),
            'color'  => $request->query('prefill_color', ''),
            'size'   => $request->query('prefill_size', ''),
        ];

        return view('team.create', compact('product', 'prefill'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'        => 'required|integer|exists:products,id',
            'players'           => 'required|array|min:1',
            'players.*.name'    => 'required|string|max:60',
            'players.*.number'  => ['required', 'regex:/^\d{1,3}$/'],
            'players.*.size'    => 'nullable|string|max:10',
            'players.*.font'    => 'nullable|string|max:50',
            'players.*.color'   => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable', // optional if UI passes variant id
        ]);

        // Save team
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players'    => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Build players payload for ShopifyCartController:
        // for each player we want: name, number, size, font, color, variant_id(optional)
        $playersForShopify = [];
        foreach ($data['players'] as $p) {
            $playersForShopify[] = [
                'name'       => $p['name'] ?? '',
                'number'     => $p['number'] ?? '',
                'size'       => $p['size'] ?? null,
                'font'       => $p['font'] ?? '',
                'color'      => $p['color'] ?? '',
                'variant_id' => $p['variant_id'] ?? null,
            ];
        }

        // Add helpful context: product shopify id if available
        $product = Product::find($data['product_id']);
        $shopifyProductId = $product->shopify_product_id ?? null;

        $shopifyPayload = [
            'product_id' => $data['product_id'],
            'players'    => $playersForShopify,
            'shopify_product_id' => $shopifyProductId ? (string)$shopifyProductId : null,
            'team_id'    => $team->id,
        ];

        // Call ShopifyCartController (which accepts players array)
        try {
            $shopifyController = app(ShopifyCartController::class);
            $resp = $shopifyController->addToCart(new Request($shopifyPayload));

            $checkoutUrl = null;

            if ($resp instanceof RedirectResponse) {
                return $resp;
            }

            if ($resp instanceof JsonResponse) {
                $json = $resp->getData(true);
                $checkoutUrl = $json['checkoutUrl'] ?? $json['checkout_url'] ?? null;
            } elseif ($resp instanceof Response) {
                $content = $resp->getContent();
                $maybe = @json_decode($content, true);
                if (is_array($maybe)) {
                    $checkoutUrl = $maybe['checkoutUrl'] ?? $maybe['checkout_url'] ?? null;
                }
            } elseif (is_array($resp)) {
                $checkoutUrl = $resp['checkoutUrl'] ?? $resp['checkout_url'] ?? null;
            } elseif (is_string($resp)) {
                if (filter_var($resp, FILTER_VALIDATE_URL)) {
                    $checkoutUrl = $resp;
                } else {
                    $maybe = @json_decode($resp, true);
                    if (is_array($maybe)) {
                        $checkoutUrl = $maybe['checkoutUrl'] ?? $maybe['checkout_url'] ?? null;
                    }
                }
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'team_id' => $team->id,
                    'checkoutUrl' => $checkoutUrl
                ], 200);
            }

            if (!empty($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }

            return redirect()->route('team.show', $team->id)->with('success', 'Team saved. Proceed to cart manually.');

        } catch (\Throwable $e) {
            Log::error('Shopify addToCart failed: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'payload' => $shopifyPayload
            ]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not add to Shopify cart.'], 500);
            }
            return back()->with('error', 'Could not add to Shopify cart. Please try again.');
        }
    }
}
