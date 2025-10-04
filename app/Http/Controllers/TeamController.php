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

    // prefill values (unchanged)
    $prefill = [
        'name'   => $request->query('prefill_name', ''),
        'number' => $request->query('prefill_number', ''),
        'font'   => $request->query('prefill_font', ''),
        'color'  => $request->query('prefill_color', ''),
        'size'   => $request->query('prefill_size', ''),
    ];

    // default slots (fallback)
    $defaultLayout = [
        'name' => ['left_pct'=>67, 'top_pct'=>18, 'width_pct'=>22, 'height_pct'=>8, 'rotation'=>0],
        'number' => ['left_pct'=>62, 'top_pct'=>48, 'width_pct'=>30, 'height_pct'=>18, 'rotation'=>0]
    ];

    if (!$product) {
        return view('team.create', [
            'product' => null,
            'prefill' => $prefill,
            'layoutSlots' => $defaultLayout
        ]);
    }

    // STEP A: fetch candidate decoration areas for this product
    $areas = collect();

    // A1: try model relation if exists
    try {
        if (method_exists($product, 'areas')) {
            $areas = $product->areas()->get();
        } elseif (method_exists($product, 'decorationAreas')) {
            $areas = $product->decorationAreas()->get();
        }
    } catch (\Throwable $e) {
        Log::debug('Team.create: product relation read failed: ' . $e->getMessage());
    }

    // A2: fallback to common tables if relation returned nothing
    if ($areas->isEmpty()) {
        $candidateTables = ['product_areas','product_view_areas','view_areas','areas','decoration_areas'];
        foreach ($candidateTables as $tbl) {
            try {
                if (!Schema::hasTable($tbl)) continue;
                // build query dynamically depending on available columns
                $q = DB::table($tbl);
                if (Schema::hasColumn($tbl, 'product_id')) {
                    $q->where('product_id', $product->id);
                } elseif (Schema::hasColumn($tbl, 'product_view_id')) {
                    $q->where('product_view_id', $product->id);
                } elseif (Schema::hasColumn($tbl, 'view_id')) {
                    $q->where('view_id', $product->id);
                } else {
                    // try generic match by product id column name presence elsewhere
                    $q->whereRaw('1 = 0'); // no-op to avoid accidental large selects
                }

                $rows = $q->get();
                if ($rows && $rows->count() > 0) {
                    $areas = collect($rows);
                    break;
                }
            } catch (\Throwable $e) {
                // ignore and continue
            }
        }
    }

    // STEP B: determine image original dims (prefer stored fields)
    $imgW = $product->image_width ?? $product->orig_width ?? $product->width ?? null;
    $imgH = $product->image_height ?? $product->orig_height ?? $product->height ?? null;

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
            // ignore
        }
    }

    if (empty($imgW) || empty($imgH)) {
        $imgW = 1200;
        $imgH = 800;
    }

    // STEP C: build layoutSlots based on detected areas (prefer 'back' handle/name)
    $layoutSlots = $defaultLayout;

    if ($areas->isNotEmpty()) {
        // prefer explicit 'back' area by handle/name if present
        $backArea = $areas->first(function($a) {
            $nameKeys = ['handle','name','area_name','key','label'];
            foreach ($nameKeys as $k) {
                if (isset($a->$k) && is_string($a->$k) && stripos($a->$k, 'back') !== false) return true;
            }
            return false;
        });

        // if no labelled back area, pick two areas: top-most (smaller y) => name, bottom-most => number
        if (!$backArea) {
            // if dataset contains multiple boxes, choose two by Y coordinate
            // normalize to objects with numeric top (y) if available
            $withCoords = $areas->map(function($a) {
                // prefer percent fields if available
                $left_pct = $a->left_pct ?? $a->x_pct ?? null;
                $top_pct  = $a->top_pct  ?? $a->y_pct ?? null;
                $w_pct    = $a->width_pct ?? $a->w_pct ?? null;
                $h_pct    = $a->height_pct ?? $a->h_pct ?? null;

                // pixel fields
                $x = $a->x ?? $a->left ?? $a->px_x ?? null;
                $y = $a->y ?? $a->top ?? $a->px_y ?? null;
                $w = $a->width ?? $a->w ?? $a->px_w ?? null;
                $h = $a->height ?? $a->h ?? $a->px_h ?? null;

                return (object)[
                    'raw' => $a,
                    'left_pct'  => $left_pct !== null ? floatval($left_pct) : null,
                    'top_pct'   => $top_pct  !== null ? floatval($top_pct)  : null,
                    'width_pct' => $w_pct    !== null ? floatval($w_pct)    : null,
                    'height_pct'=> $h_pct    !== null ? floatval($h_pct)    : null,
                    'x' => $x !== null ? floatval($x) : null,
                    'y' => $y !== null ? floatval($y) : null,
                    'w' => $w !== null ? floatval($w) : null,
                    'h' => $h !== null ? floatval($h) : null,
                ];
            });

            // try to compute top in percent for sorting
            $withCoords = $withCoords->map(function($o) use ($imgW, $imgH) {
                if ($o->top_pct === null) {
                    if ($o->y !== null) $o->top_pct = ($o->y / $imgH) * 100;
                    else $o->top_pct = null;
                }
                return $o;
            });

            // filter out those without any top info
            $cand = $withCoords->filter(function($o){ return $o->top_pct !== null; });

            if ($cand->count() >= 2) {
                // sort by top_pct ascending -> top boxes first
                $sorted = $cand->sortBy('top_pct')->values();
                $upper = $sorted->first();
                $lower = $sorted->last();
                $areasForSlots = [$upper, $lower];
            } else {
                // fallback: use the single area we have for both name & number with heuristics
                $single = $withCoords->first();
                $areasForSlots = [$single, $single];
            }
        } else {
            // backArea found: use it and split into name/number internally
            $areasForSlots = [ (object)['raw' => $backArea] , (object)['raw' => $backArea] ];
        }

        // compute percent values for the bounding area(s)
        // prefer reading percents if present, otherwise convert pixels -> percents using imgW/imgH
        $prepareSlotFrom = function($entry) use ($imgW, $imgH) {
            $raw = $entry->raw;
            // detect percent fields
            if (isset($raw->left_pct) && isset($raw->top_pct) && isset($raw->width_pct) && isset($raw->height_pct)) {
                return [
                    'left' => floatval($raw->left_pct),
                    'top'  => floatval($raw->top_pct),
                    'w'    => floatval($raw->width_pct),
                    'h'    => floatval($raw->height_pct)
                ];
            }
            // else if pixel fields
            $x = $raw->x ?? $raw->left ?? $raw->px_x ?? null;
            $y = $raw->y ?? $raw->top ?? $raw->px_y ?? null;
            $w = $raw->width ?? $raw->w ?? $raw->px_w ?? null;
            $h = $raw->height ?? $raw->h ?? $raw->px_h ?? null;

            if ($x !== null && $y !== null && $w !== null && $h !== null) {
                return [
                    'left' => ($x / $imgW) * 100,
                    'top'  => ($y / $imgH) * 100,
                    'w'    => ($w / $imgW) * 100,
                    'h'    => ($h / $imgH) * 100
                ];
            }

            // no clear coords -> fallback
            return [ 'left' => 60, 'top' => 30, 'w' => 30, 'h' => 40 ];
        };

        // compute a merged area if both upper & lower are same (split)
        $upperSrc = $areasForSlots[0];
        $lowerSrc = $areasForSlots[1];

        $upperArea = $prepareSlotFrom($upperSrc);
        $lowerArea = $prepareSlotFrom($lowerSrc);

        // If both came from same area, we'll split vertically: name top 20%, number bottom 36% (tweakable)
        if ($upperSrc === $lowerSrc) {
            $left = $upperArea['left'];
            $top = $upperArea['top'];
            $wPct = $upperArea['w'];
            $hPct = $upperArea['h'];

            $centerX = $left + ($wPct * 0.5);
            $nameTop = $top + ($hPct * 0.18);
            $numTop  = $top + ($hPct * 0.62);

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
        } else {
            // If two distinct boxes (upper & lower), compute center for each
            $centerUpperX = $upperArea['left'] + ($upperArea['w'] * 0.5);
            $centerLowerX = $lowerArea['left'] + ($lowerArea['w'] * 0.5);

            $layoutSlots = [
                'name' => [
                    'left_pct'  => round($centerUpperX, 3),
                    'top_pct'   => round($upperArea['top'] + ($upperArea['h'] * 0.18), 3),
                    'width_pct' => round(max(8, $upperArea['w'] * 0.9), 3),
                    'height_pct'=> round(max(6, $upperArea['h'] * 0.28), 3),
                    'rotation'  => 0
                ],
                'number' => [
                    'left_pct'  => round($centerLowerX, 3),
                    'top_pct'   => round($lowerArea['top'] + ($lowerArea['h'] * 0.45), 3),
                    'width_pct' => round(max(8, $lowerArea['w'] * 0.9), 3),
                    'height_pct'=> round(max(8, $lowerArea['h'] * 0.5), 3),
                    'rotation'  => 0
                ]
            ];
        }
    } // end areas->isNotEmpty()

    // debug
    Log::debug('Team.create computed layoutSlots', [
        'product_id' => $product->id ?? null,
        'imgW' => $imgW, 'imgH' => $imgH,
        'layoutSlots' => $layoutSlots,
        'foundAreasCount' => $areas->count()
    ]);

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
