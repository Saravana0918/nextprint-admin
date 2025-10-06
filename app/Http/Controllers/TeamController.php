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

class TeamController extends Controller
{
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        if (!$productId) {
            abort(404, 'product_id missing');
        }

        // EAGER LOAD variants so view always gets them
        $product = Product::with('variants')->find($productId);
        if (!$product) {
            abort(404, 'Product not found: ' . $productId);
        }

        $prefill = $request->only(['prefill_name','prefill_number','prefill_font','prefill_color','prefill_size']);

        $layoutSlots = [];
        if (!empty($product->layout_slots)) {
            $layoutSlots = is_array($product->layout_slots) ? $product->layout_slots : json_decode($product->layout_slots, true);
        }

        if (empty($layoutSlots) && $request->has('layoutSlots')) {
            $raw = urldecode($request->query('layoutSlots'));
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $layoutSlots = $decoded;
        }

        // Build server-side variantMap and pass to view (optional but useful)
        $variantMap = [];
        foreach ($product->variants as $v) {
            $key = trim((string)($v->option_value ?? $v->option_name ?? ''));
            if ($key === '') continue;
            $variantMap[strtoupper($key)] = (string)($v->shopify_variant_id ?? $v->variant_id ?? '');
        }

        return view('team.create', compact('product','prefill','layoutSlots','variantMap'));
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
            'players.*.variant_id' => 'nullable', // we'll normalize below
        ]);

        // Normalize players, ensure variant_id exists if possible
        $normalizedPlayers = [];
        $missingVariantRows = [];

        foreach ($data['players'] as $index => $p) {
            $name = $p['name'] ?? '';
            $number = $p['number'] ?? '';
            $size = isset($p['size']) ? trim((string)$p['size']) : null;
            $variantId = isset($p['variant_id']) ? trim((string)$p['variant_id']) : null;

            // If variant_id missing but size present, try to lookup in DB
            if (empty($variantId) && $size) {
                $pv = ProductVariant::where('product_id', $data['product_id'])
                        ->whereRaw('UPPER(option_value) = ?', [strtoupper($size)])
                        ->first();
                if ($pv) {
                    $variantId = (string)$pv->shopify_variant_id;
                }
            }

            // collect normalized
            $normalizedPlayers[] = [
                'name' => $name,
                'number' => $number,
                'size' => $size,
                'font' => $p['font'] ?? '',
                'color' => $p['color'] ?? '',
                'variant_id' => $variantId ?: null,
            ];

            if (empty($variantId)) {
                $missingVariantRows[] = [
                    'index' => $index,
                    'name' => $name,
                    'number' => $number,
                    'size' => $size,
                ];
            }
        }

        // If any players are missing variant_id, fail with helpful message (avoid adding invalid cart lines)
        if (!empty($missingVariantRows)) {
            Log::warning('Team store missing variant ids', ['product_id' => $data['product_id'], 'missing' => $missingVariantRows]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more players are missing size → variant mapping. Please select a valid size for each player.',
                    'missing' => $missingVariantRows
                ], 422);
            }

            // You can redirect back with old input and an error message
            return back()->withInput()->with('error', 'One or more players are missing size → variant mapping. Please ensure each player has a valid size selected.');
        }

        // At this point all players have variant_id
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players' => $normalizedPlayers,
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Build players payload for Shopify controller
        $playersForShopify = [];
        foreach ($normalizedPlayers as $p) {
            $playersForShopify[] = [
                'name' => $p['name'],
                'number' => $p['number'],
                'size' => $p['size'],
                'font' => $p['font'],
                'color' => $p['color'],
                'variant_id' => $p['variant_id'], // guaranteed present
            ];
        }

        $product = Product::find($data['product_id']);
        $shopifyProductId = $product->shopify_product_id ?? null;

        $shopifyPayload = [
            'product_id' => $data['product_id'],
            'players' => $playersForShopify,
            'shopify_product_id' => $shopifyProductId ? (string)$shopifyProductId : null,
            'team_id' => $team->id,
        ];

        Log::info('TeamController: calling ShopifyCartController->addToCart', ['payload' => $shopifyPayload]);

        try {
            $shopifyController = app(ShopifyCartController::class);
            $resp = $shopifyController->addToCart(new Request($shopifyPayload));

            // inspect response for debugging
            Log::info('Shopify addToCart resp type: ' . (is_object($resp) ? get_class($resp) : gettype($resp)));
            if ($resp instanceof JsonResponse) {
                Log::info('Shopify addToCart json: ', $resp->getData(true));
            } elseif ($resp instanceof Response) {
                Log::info('Shopify addToCart content: ' . $resp->getContent());
            } elseif (is_array($resp)) {
                Log::info('Shopify addToCart array: ', $resp);
            } else {
                Log::info('Shopify addToCart other response: ' . print_r($resp, true));
            }

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
                if (is_array($maybe)) $checkoutUrl = $maybe['checkoutUrl'] ?? $maybe['checkout_url'] ?? null;
            } elseif (is_array($resp)) {
                $checkoutUrl = $resp['checkoutUrl'] ?? $resp['checkout_url'] ?? null;
            } elseif (is_string($resp) && filter_var($resp, FILTER_VALIDATE_URL)) {
                $checkoutUrl = $resp;
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'team_id' => $team->id,
                    'checkoutUrl' => $checkoutUrl,
                ], 200);
            }

            if (!empty($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }

            return redirect()->route('team.show', $team->id)->with('success', 'Team saved. Proceed to cart manually.');
        } catch (\Throwable $e) {
            Log::error('Shopify addToCart failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $shopifyPayload
            ]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not add to Shopify cart.'], 500);
            }
            return back()->with('error', 'Could not add to Shopify cart. Please try again.');
        }
    }
}
