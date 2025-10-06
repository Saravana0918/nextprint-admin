<?php
// file: app/Http/Controllers/PublicDesignerController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;

class PublicDesignerController extends Controller
{
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // ----------------------------
        // product lookup (robust)
        // ----------------------------
        $product = null;

        if ($productId) {
            // if it's numeric and looks like a Shopify ID (>=8 digits), try shopify_product_id first
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 8) {
                $product = Product::with(['views','views.areas'])
                            ->where('shopify_product_id', $productId)
                            ->first();

                if (! $product) {
                    // fallback to primary key id
                    $product = Product::with(['views','views.areas'])->find((int)$productId);
                }
            } else {
                // normal: try primary key first
                if (ctype_digit((string)$productId)) {
                    $product = Product::with(['views','views.areas'])->find((int)$productId);
                }
            }

            // existing fallback: try matching on other columns if still not found
            if (!$product) {
                $query = Product::with(['views','views.areas']);
                $cols = [];
                if (Schema::hasColumn('products','shopify_product_id')) $cols[] = 'shopify_product_id';
                if (Schema::hasColumn('products','name')) $cols[] = 'name';
                if (Schema::hasColumn('products','sku')) $cols[] = 'sku';

                if (count($cols)) {
                    $query->where(function($q) use ($productId, $cols) {
                        foreach ($cols as $c) {
                            $q->orWhere($c, $productId);
                        }
                    });
                    $product = $query->first();
                }
            }
        }

        if (!$product) {
            \Log::warning("designer: product not found for product_id={$productId}");
            abort(404, 'Product not found');
        }

        // ----------------------------
        // Resolve view (explicit view_id or fallback to first)
        // ----------------------------
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            if ($product->relationLoaded('views') && $product->views->count()) {
                $view = $product->views->first();
            } else {
                $view = $product->views()->with('areas')->first();
            }
        }

        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // ----------------------------
        // Build layoutSlots with server-side normalization
        // ----------------------------
        $layoutSlots = [];

        foreach ($areas as $a) {
            // read raw
            $left  = (float)($a->left_pct ?? 0);
            $top   = (float)($a->top_pct ?? 0);
            $w     = (float)($a->width_pct ?? 10);
            $h     = (float)($a->height_pct ?? 10);

            // normalize: fractions -> percent
            if ($left <= 1) $left *= 100;
            if ($top   <= 1) $top  *= 100;
            if ($w     <= 1) $w    *= 100;
            if ($h     <= 1) $h    *= 100;

            // heuristics for slot_key
            $slotKey = null;
            if (!empty($a->slot_key)) {
                $slotKey = strtolower(trim($a->slot_key));
            }

            if (!$slotKey && !empty($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                if (strpos($n, 'num') !== false || strpos($n, 'no') !== false || strpos($n,'number') !== false) $slotKey = 'number';
            }

            if (!$slotKey && isset($a->template_id)) {
                if ((int)$a->template_id === 1) $slotKey = 'name';
                if ((int)$a->template_id === 2) $slotKey = 'number';
            }

            if (!$slotKey) {
                if (!isset($layoutSlots['name'])) $slotKey = 'name';
                else $slotKey = 'number';
            }

            $layoutSlots[$slotKey] = [
                'id' => $a->id,
                'left_pct'  => round($left, 6),
                'top_pct'   => round($top, 6),
                'width_pct' => round($w, 6),
                'height_pct'=> round($h, 6),
                'rotation'  => (int)($a->rotation ?? 0),
                'name'      => $a->name ?? null,
                'slot_key'  => $a->slot_key ?? null,
            ];
        }

        if (!isset($layoutSlots['name'])) {
            $layoutSlots['name'] = [
                'id' => null, 'left_pct' => 10, 'top_pct' => 5, 'width_pct' => 60, 'height_pct' => 8, 'rotation' => 0
            ];
        }
        if (!isset($layoutSlots['number'])) {
            $layoutSlots['number'] = [
                'id' => null, 'left_pct' => 10, 'top_pct' => 75, 'width_pct' => 30, 'height_pct' => 10, 'rotation' => 0
            ];
        }

        // ----------------------------
        // compute a safe display price (robust)
        // ----------------------------
        $displayPrice = null;

        try {
            \Log::info("designer: product id={$product->id} shopify_product_id={$product->shopify_product_id} min_price=" . ($product->min_price ?? 'NULL') . " price=" . ($product->price ?? 'NULL'));

            if (isset($product->min_price) && is_numeric($product->min_price) && (float)$product->min_price > 0) {
                $displayPrice = (float)$product->min_price;
            } elseif (isset($product->price) && is_numeric($product->price) && (float)$product->price > 0) {
                $displayPrice = (float)$product->price;
            }

            // fallback: inspect variants for price, price_cents, price_in_cents
            if ($displayPrice === null && method_exists($product, 'variants')) {
                // log sample variants for debugging
                if ($product->relationLoaded('variants')) {
                    foreach ($product->variants as $v) {
                        \Log::info("designer: variant sample id=" . ($v->id ?? 'n/a') .
                                   " price=" . ($v->price ?? 'n/a') .
                                   " price_cents=" . ($v->price_cents ?? 'n/a') .
                                   " price_in_cents=" . ($v->price_in_cents ?? 'n/a'));
                    }

                    // collect variant prices (robust)
                    $variantPrices = [];
                    foreach ($product->variants as $v) {
                        if (!empty($v->price) && (float)$v->price > 0) {
                            $variantPrices[] = (float)$v->price;
                        } elseif (!empty($v->price_cents) && (int)$v->price_cents > 0) {
                            $variantPrices[] = (float)$v->price_cents / 100;
                        } elseif (!empty($v->price_in_cents) && (int)$v->price_in_cents > 0) {
                            $variantPrices[] = (float)$v->price_in_cents / 100;
                        }
                    }
                    if (count($variantPrices)) $displayPrice = min($variantPrices);
                } else {
                    // DB-level checks (min queries) if relation not loaded
                    $variantPrices = [];

                    if (Schema::hasColumn('variants','price')) {
                        $minP = $product->variants()->min('price');
                        if ($minP && $minP > 0) $variantPrices[] = (float)$minP;
                    }

                    if (Schema::hasColumn('variants','price_cents')) {
                        $minPc = $product->variants()->whereNotNull('price_cents')->min('price_cents');
                        if ($minPc && $minPc > 0) $variantPrices[] = (float)$minPc / 100;
                    }

                    if (Schema::hasColumn('variants','price_in_cents')) {
                        $minPi = $product->variants()->whereNotNull('price_in_cents')->min('price_in_cents');
                        if ($minPi && $minPi > 0) $variantPrices[] = (float)$minPi / 100;
                    }

                    if (count($variantPrices)) $displayPrice = min($variantPrices);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('designer: price compute failed: ' . $e->getMessage());
        }

        // final fallback: zero (blade will show formatted)
        if ($displayPrice === null) $displayPrice = 0.00;

        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
            'layoutSlots' => $layoutSlots,
            'displayPrice' => (float)$displayPrice,
        ]);
    }

    // Example in DesignerController.php
public function showDesigner($id) {
    // ensure variants relation is eager loaded
    $product = Product::with('variants')->find($id);
    if (!$product) abort(404);
    return view('designer', compact('product'));
}
}
