<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Models\Team;
use App\Models\Product;
use App\Http\Controllers\ShopifyCartController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    public function create(Request $request)
{
    $productId = $request->query('product_id');
    $product = null;
    if ($productId) {
        $product = Product::find($productId);
    }

    // prefill from query (existing)
    $prefill = [
        'name'   => $request->query('prefill_name', ''),
        'number' => $request->query('prefill_number', ''),
        'font'   => $request->query('prefill_font', ''),
        'color'  => $request->query('prefill_color', ''),
        'size'   => $request->query('prefill_size', ''),
    ];

    // default fallback layoutSlots (safe fallback if we can't find area data)
    $defaultLayout = [
        'name' => ['left_pct'=>67, 'top_pct'=>18, 'width_pct'=>22, 'height_pct'=>8, 'rotation'=>0],
        'number' => ['left_pct'=>62, 'top_pct'=>48, 'width_pct'=>30, 'height_pct'=>18, 'rotation'=>0]
    ];

    // If no product, just return view with prefill and default layout
    if (!$product) {
        return view('team.create', [
            'product' => $product,
            'prefill' => $prefill,
            'layoutSlots' => $defaultLayout
        ]);
    }

    // STEP 1: attempt to locate decoration area row for this product
    $area = null;

    // 1a) If product model has a relation like areas or decorationAreas, try it
    try {
        if (method_exists($product, 'areas')) {
            $rel = $product->areas()->get();
            if ($rel->count()) {
                // prefer area with handle/back-like name if available
                $area = $rel->firstWhere('handle', 'back') ?? $rel->first();
            }
        } elseif (method_exists($product, 'decorationAreas')) {
            $rel = $product->decorationAreas()->get();
            if ($rel->count()) {
                $area = $rel->firstWhere('handle', 'back') ?? $rel->first();
            }
        }
    } catch (\Throwable $e) {
        // ignore relation errors
        Log::debug('TeamController.create: relation check failed: '.$e->getMessage());
    }

    // 1b) If not found, try common DB tables (adaptive)
    if (!$area) {
        $possibleTables = ['product_areas','product_view_areas','view_areas','areas','decoration_areas'];
        foreach ($possibleTables as $tbl) {
            try {
                if (Schema::hasTable($tbl)) {
                    // look for a row matching the product id (or view_id)
                    $row = DB::table($tbl)
                        ->where(function($q) use ($product) {
                            // try product_id column if exists
                            $cols = Schema::getColumnListing(DB::getTablePrefix() . 'product_areas');
                            // (we'll attempt generic queries; some tables may store product_id or product_view_id)
                        })
                        ->where(function($q) use ($product, $tbl) {
                            // Try product_id column if present
                            if (Schema::hasColumn($tbl, 'product_id')) {
                                $q->orWhere('product_id', $product->id);
                            }
                            // Try product_view_id or view_id fallback
                            if (Schema::hasColumn($tbl, 'product_view_id')) {
                                $q->orWhere('product_view_id', $product->id);
                            }
                            if (Schema::hasColumn($tbl, 'view_id')) {
                                $q->orWhere('view_id', $product->id);
                            }
                        })
                        ->first();
                    if ($row) { $area = $row; break; }
                }
            } catch (\Throwable $e) {
                // swallow table checking errors, continue
            }
        }
    }

    // 1c) If still nothing, attempt to find any area rows by product id in DB (last resort)
    if (!$area) {
        try {
            $area = DB::table('product_areas')->where('product_id', $product->id)->first();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // STEP 2: determine image original dimensions (prefer product fields if available)
    $imgW = $product->image_width ?? $product->orig_width ?? null;
    $imgH = $product->image_height ?? $product->orig_height ?? null;

    // If not present, try to fetch via getimagesize from the stored URL (works if accessible and not blocked)
    if (empty($imgW) || empty($imgH)) {
        try {
            $imgUrl = $product->image_url ?? $product->preview_src ?? null;
            if ($imgUrl) {
                $size = @getimagesize($imgUrl);
                if ($size && isset($size[0]) && isset($size[1])) {
                    $imgW = $size[0];
                    $imgH = $size[1];
                }
            }
        } catch (\Throwable $e) {
            // swallow
        }
    }

    // final fallback
    if (empty($imgW) || empty($imgH)) {
        $imgW = 1200;
        $imgH = 800;
    }

    // STEP 3: compute layoutSlots from $area if found
    $layoutSlots = $defaultLayout;

    if ($area) {
        // area might already have percent columns or pixel columns.
        // Common column names: x, y, width, height OR left_pct, top_pct, width_pct, height_pct
        $hasPercent = (property_exists($area, 'left_pct') && property_exists($area, 'top_pct'))
                      || (isset($area->left_pct) && isset($area->top_pct));

        if ($hasPercent) {
            // use stored percents directly
            $left = floatval($area->left_pct);
            $top  = floatval($area->top_pct);
            $wPct = floatval($area->width_pct ?? $area->w_pct ?? $area->width_percent ?? $area->width ?? 30);
            $hPct = floatval($area->height_pct ?? $area->h_pct ?? $area->height_percent ?? $area->height ?? 20);
        } else {
            // assume pixel coords exist: x,y,width,height (or left,top,w,h)
            $x = $area->x ?? $area->left ?? $area->px_x ?? null;
            $y = $area->y ?? $area->top ?? $area->px_y ?? null;
            $w = $area->width ?? $area->w ?? $area->px_w ?? null;
            $h = $area->height ?? $area->h ?? $area->px_h ?? null;

            if ($x !== null && $y !== null && $w !== null && $h !== null) {
                $left = ($x / $imgW) * 100;
                $top  = ($y / $imgH) * 100;
                $wPct = ($w / $imgW) * 100;
                $hPct = ($h / $imgH) * 100;
            } else {
                // if unknown shape, fallback to center/back region heuristics
                $left = 60; $top = 30; $wPct = 30; $hPct = 40;
            }
        }

        // Build two slots inside this area: name (upper) and number (lower)
        $centerX = $left + ($wPct * 0.5);
        $nameTop = $top + ($hPct * 0.20);   // top 20% area for name
        $numTop  = $top + ($hPct * 0.62);   // lower 62% area for number

        $layoutSlots = [
            'name' => [
                'left_pct'  => round($centerX, 3),
                'top_pct'   => round($nameTop, 3),
                'width_pct' => round(max(8, $wPct * 0.88), 3),
                'height_pct'=> round(max(6, $hPct * 0.22), 3),
                'rotation'  => 0
            ],
            'number' => [
                'left_pct'  => round($centerX, 3),
                'top_pct'   => round($numTop, 3),
                'width_pct' => round(max(8, $wPct * 0.94), 3),
                'height_pct'=> round(max(8, $hPct * 0.36), 3),
                'rotation'  => 0
            ]
        ];
    }

    // Debug logging (optional, remove in production)
    Log::debug('Team.create layoutSlots', ['product_id' => $product->id ?? null, 'layoutSlots' => $layoutSlots, 'imgW' => $imgW, 'imgH' => $imgH]);

    return view('team.create', [
        'product' => $product,
        'prefill' => $prefill,
        'layoutSlots' => $layoutSlots
    ]);
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
            'players.*.variant_id' => 'nullable',
        ]);

        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players' => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Build players payload
        $playersForShopify = [];
        foreach ($data['players'] as $p) {
            $playersForShopify[] = [
                'name' => $p['name'] ?? '',
                'number' => $p['number'] ?? '',
                'size' => $p['size'] ?? null,
                'font' => $p['font'] ?? '',
                'color' => $p['color'] ?? '',
                'variant_id' => $p['variant_id'] ?? null,
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
