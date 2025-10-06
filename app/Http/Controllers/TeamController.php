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
    /**
     * Show create form.
     */
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        if (! $productId) {
            abort(404, 'product_id missing');
        }

        // eager load variants to help client-side mapping
        $product = Product::with('variants')->find($productId);
        if (! $product) {
            abort(404, 'Product not found: ' . $productId);
        }

        $prefill = $request->only(['prefill_name','prefill_number','prefill_font','prefill_color','prefill_size']);

        $layoutSlots = [];
        if (!empty($product->layout_slots)) {
            $layoutSlots = is_array($product->layout_slots) ? $product->layout_slots : json_decode($product->layout_slots, true);
        }

        // If layoutSlots passed via query (designer -> team) use it
        if (empty($layoutSlots) && $request->has('layoutSlots')) {
            $raw = urldecode($request->query('layoutSlots'));
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $layoutSlots = $decoded;
        }

        // build variantMap server-side (uppercase keys) so create.blade can use it
        $variantMap = [];
        foreach ($product->variants as $v) {
            $k = trim((string)($v->option_value ?? $v->option_name ?? ''));
            if ($k === '') continue;
            // prefer shopify_variant_id column if present, else variant_id
            $variantMap[strtoupper($k)] = (string) ($v->shopify_variant_id ?? $v->variant_id ?? '');
        }

        return view('team.create', compact('product','prefill','layoutSlots','variantMap'));
    }

    /**
     * Store team and try add to Shopify cart.
     */
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
            'players.*.variant_id' => 'nullable',
        ]);

        // Normalize players & ensure variant id is present if possible
        $normalized = [];
        $missing = [];
        foreach ($data['players'] as $i => $p) {
            $name = $p['name'] ?? '';
            $number = $p['number'] ?? '';
            $size = isset($p['size']) ? trim((string)$p['size']) : null;
            $incoming = isset($p['variant_id']) ? trim((string)$p['variant_id']) : null;

            $variant = $this->resolveVariantId($data['product_id'], $size, $incoming, $request->input('shopify_product_id', null));

            $normalized[] = [
                'name' => strtoupper(substr($name,0,12)),
                'number' => preg_replace('/\D/','',$number),
                'size' => $size,
                'font' => $p['font'] ?? '',
                'color' => $p['color'] ?? '',
                'variant_id' => $variant ? (string)$variant : null,
            ];

            if (empty($variant)) {
                $missing[] = ['index' => $i, 'name' => $name, 'number' => $number, 'size' => $size];
            }
        }

        if (! empty($missing)) {
            Log::warning('Team store missing variant ids', ['product_id' => $data['product_id'], 'missing' => $missing]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more players missing variant mapping (size). Please select valid size for each player.',
                    'missing' => $missing
                ], 422);
            }
            return back()->withInput()->with('error', 'One or more players are missing size → variant mapping. Ensure valid size selected.');
        }

        // Save team
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players' => $normalized,
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Prepare payload for ShopifyCartController
        $playersForShopify = [];
        foreach ($normalized as $p) {
            $playersForShopify[] = [
                'name' => $p['name'],
                'number' => $p['number'],
                'size' => $p['size'],
                'font' => $p['font'],
                'color' => $p['color'],
                'variant_id' => $p['variant_id'],
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
            // call controller method and get response
            $resp = $shopifyController->addToCart(new Request($shopifyPayload));

            // try extract checkout url
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

            // fallback: show team page (so user won't see 404)
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

    /**
     * Resolve variant id (numeric string) using multiple fallbacks:
     * 1) incoming variant id (if provided) - normalize
     * 2) product_variants DB table (option_value matches)
     * 3) loaded Product->variants relation (if exists)
     * 4) Shopify Admin API (last resort)
     */
    protected function resolveVariantId($productId, $size = null, $incomingVariant = null, $shopifyProductId = null)
    {
        // helper: strip gid if present, return null if empty
        $normalize = function($id) {
            if (empty($id)) return null;
            $s = (string)$id;
            if (strpos($s, 'gid://') !== false) {
                if (preg_match('/(\d+)$/', $s, $m)) return $m[1];
            }
            // if looks numeric, return as-is
            if (preg_match('/^\d+$/', $s)) return $s;
            // shopify_variant_id sometimes stored as string with prefix - attempt digits
            if (preg_match('/(\d+)/', $s, $m)) return $m[1];
            return null;
        };

        // 1) incoming explicit
        $normIncoming = $normalize($incomingVariant);
        if (! empty($normIncoming)) return (string)$normIncoming;

        // 2) try product_variants table (if exists)
        if (! empty($size) && Schema::hasTable('product_variants')) {
            try {
                $pv = ProductVariant::where('product_id', $productId)
                    ->where(function($q) use ($size) {
                        $q->where('option_value', $size)
                          ->orWhere('option_value', strtoupper($size))
                          ->orWhere('option_value', strtolower($size))
                          ->orWhere('option_value', 'LIKE', '%'.$size.'%');
                    })
                    ->whereNotNull('shopify_variant_id')
                    ->first();
                if ($pv && !empty($pv->shopify_variant_id)) {
                    $v = $normalize($pv->shopify_variant_id);
                    if ($v) return (string)$v;
                }
            } catch (\Throwable $e) {
                Log::warning('resolveVariantId: product_variants lookup failed: ' . $e->getMessage());
            }
        }

        // 3) try Product::variants relation in DB
        try {
            $product = Product::with('variants')->find($productId);
            if ($product && $product->relationLoaded('variants')) {
                foreach ($product->variants as $v) {
                    $opt = trim((string)($v->option_value ?? $v->option_name ?? ''));
                    if ($opt !== '' && $size && strcasecmp($opt, $size) === 0) {
                        $cand = $normalize($v->shopify_variant_id ?? $v->variant_id ?? null);
                        if ($cand) return (string)$cand;
                    }
                }
                // as fallback return first variant id if size not matched
                if ($product->variants->count() > 0) {
                    $first = $product->variants->first();
                    $cand = $normalize($first->shopify_variant_id ?? $first->variant_id ?? null);
                    if ($cand) return (string)$cand;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('resolveVariantId: product relation check failed: ' . $e->getMessage());
        }

        // 4) fallback: call Shopify Admin API to find variant by option value
        if (! empty($shopifyProductId)) {
            try {
                $shop = env('SHOPIFY_STORE');
                $adminToken = env('SHOPIFY_ADMIN_API_TOKEN');
                if ($shop && $adminToken) {
                    $resp = \Illuminate\Support\Facades\Http::withHeaders([
                        'X-Shopify-Access-Token' => $adminToken,
                        'Content-Type' => 'application/json'
                    ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

                    if ($resp->successful() && $resp->json('product')) {
                        $productData = $resp->json('product');
                        $variants = $productData['variants'] ?? [];
                        foreach ($variants as $var) {
                            $opt1 = $var['option1'] ?? '';
                            if ($size && (strcasecmp(trim($opt1), trim($size)) === 0 || stripos($var['title'] ?? '', $size) !== false)) {
                                return (string)$var['id'];
                            }
                        }
                        // fallback to first variant id
                        if (! empty($variants) && isset($variants[0]['id'])) {
                            return (string)$variants[0]['id'];
                        }
                    } else {
                        Log::warning('resolveVariantId: admin fetch unexpected', ['product' => $shopifyProductId, 'status' => $resp->status()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('resolveVariantId: admin API failed: ' . $e->getMessage());
            }
        }

        // no variant found
        return null;
    }

    /**
     * Basic show so redirect after create doesn't 404.
     */
    public function show($id)
    {
        $team = Team::with('product')->find($id);
        if (! $team) abort(404, 'Team not found');
        return view('team.show', compact('team'));
    }
}
