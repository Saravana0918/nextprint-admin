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

    /**
     * Store the team and attempt to add players to Shopify cart.
     * Expects front-end to POST JSON like:
     * {
     *   product_id: 6052,
     *   players: [
     *     { number: "12", name:"ALAN", size:"M", font:"bebas", color:"#fff", variant_id: "451..." },
     *     ...
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'        => 'required|integer|exists:products,id',
            'players'           => 'required|array|min:1',
            'players.*.name'    => 'required|string|max:60',
            'players.*.number'  => ['required','regex:/^\d{1,3}$/'],
            'players.*.size'    => 'nullable|string|max:20',
            'players.*.font'    => 'nullable|string|max:100',
            'players.*.color'   => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable', // allow incoming variant id
        ]);

        // Save team (ensure Team model has $fillable ['product_id','players','created_by'] and players cast to array)
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players'    => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Prepare players payload for Shopify controller:
        // attempt to resolve variant_id per player (using product_variants table if available)
        $resolvedPlayers = [];
        $shopifyProductId = null;
        $product = Product::find($data['product_id']);
        if ($product && isset($product->shopify_product_id)) {
            $shopifyProductId = (string) $product->shopify_product_id;
        }

        foreach ($data['players'] as $idx => $p) {
            $player = [
                'name' => $p['name'] ?? '',
                'number' => $p['number'] ?? '',
                'size' => $p['size'] ?? null,
                'font' => $p['font'] ?? null,
                'color' => $p['color'] ?? null,
                // variant_id may be populated below
            ];

            // Use incoming variant_id if provided (string)
            if (!empty($p['variant_id'])) {
                $player['variant_id'] = (string) $p['variant_id'];
                $resolvedPlayers[] = $player;
                continue;
            }

            // Try DB lookup: product_variants (match by option_value or normalized size)
            $variantId = null;
            try {
                if (!empty($player['size']) && Schema::hasTable('product_variants')) {
                    $pvQuery = ProductVariant::where('product_id', $data['product_id'])
                                ->whereNotNull('shopify_variant_id')
                                ->where(function ($q) use ($player) {
                                    $q->where('option_value', $player['size'])
                                      ->orWhere('option_value', strtoupper($player['size']))
                                      ->orWhere('option_value', strtolower($player['size']));
                                })->first();

                    if ($pvQuery && !empty($pvQuery->shopify_variant_id)) {
                        $variantId = (string) $pvQuery->shopify_variant_id;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('team.variant_lookup_failed', ['err' => $e->getMessage(), 'player' => $player]);
            }

            // If not found via DB, we'll leave variant_id null and ShopifyCartController will fall back
            if ($variantId) {
                $player['variant_id'] = $variantId;
            }

            $resolvedPlayers[] = $player;
        }

        // Build payload for ShopifyCartController
        $shopifyPayload = [
            'product_id' => $data['product_id'],            // local product id (int)
            'shopify_product_id' => $shopifyProductId,      // string or null
            'players' => $resolvedPlayers,                  // array of per-player objects
            'team_id'  => $team->id,
        ];

        Log::info('team.store: calling shopify addToCart', ['payload' => $shopifyPayload]);

        // Call ShopifyCartController::addToCart
        try {
            $shopifyController = app(ShopifyCartController::class);
            $shopifyRequest = new Request($shopifyPayload);

            $resp = $shopifyController->addToCart($shopifyRequest);

            $checkoutUrl = null;

            if ($resp instanceof RedirectResponse) {
                // controller returned a redirect; return it
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

            // If request was AJAX / JS fetch, return JSON with checkoutUrl
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'team_id' => $team->id,
                    'checkoutUrl' => $checkoutUrl,
                ], 200);
            }

            // Normal post: redirect to checkout if present
            if (!empty($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }

            // fallback: redirect to team show page
            return redirect()->route('team.show', $team->id)
                             ->with('success', 'Team saved. Proceed to cart manually.');

        } catch (\Throwable $e) {
            Log::error('Shopify addToCart failed: '.$e->getMessage(), [
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
