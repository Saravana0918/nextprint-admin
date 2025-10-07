<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PublicDesignerController extends Controller
{
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // ----------------------------
        // product lookup (robust) - eager load variants, views and view->areas
        // ----------------------------
        $product = null;

        if ($productId) {
            // If looks like a Shopify product id (long numeric), prefer shopify_product_id
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 8) {
                $product = Product::with(['views','views.areas','variants'])
                            ->where('shopify_product_id', $productId)
                            ->first();

                if (! $product) {
                    // fallback: local primary key
                    $product = Product::with(['views','views.areas','variants'])->find((int)$productId);
                }
            } else {
                // short numeric likely local id
                if (ctype_digit((string)$productId)) {
                    $product = Product::with(['views','views.areas','variants'])->find((int)$productId);
                }
            }

            // fallback: try matching across useful columns
            if (!$product) {
                $query = Product::with(['views','views.areas','variants']);
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
        // Build layoutSlots with server-side normalization (including mask svg public URL)
        // ----------------------------
        $layoutSlots = [];

        foreach ($areas as $a) {
            $left  = (float)($a->left_pct ?? 0);
            $top   = (float)($a->top_pct ?? 0);
            $w     = (float)($a->width_pct ?? 10);
            $h     = (float)($a->height_pct ?? 10);

            if ($left <= 1) $left *= 100;
            if ($top   <= 1) $top  *= 100;
            if ($w     <= 1) $w    *= 100;
            if ($h     <= 1) $h    *= 100;

            // determine slot key (prefer explicit slot_key, then heuristics by name)
            $slotKey = null;
            if (!empty($a->slot_key)) $slotKey = strtolower(trim($a->slot_key));

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

            // mask svg public URL
            $maskUrl = null;
            if (!empty($a->mask_svg_path)) {
                // If path stored in storage disk
                try {
                    $maskUrl = Storage::disk('public')->url($a->mask_svg_path);
                } catch (\Throwable $e) {
                    // fallback to /files/<path> (you already use this pattern)
                    $maskUrl = url('/files/' . ltrim($a->mask_svg_path, '/'));
                }
            }

            $layoutSlots[$slotKey] = [
                'id'         => $a->id,
                'left_pct'   => round($left, 6),
                'top_pct'    => round($top, 6),
                'width_pct'  => round($w, 6),
                'height_pct' => round($h, 6),
                'rotation'   => (int)($a->rotation ?? 0),
                'name'       => $a->name ?? null,
                'slot_key'   => $a->slot_key ?? null,
                'mask'       => $maskUrl,
            ];
        }

        // ensure both keys exist (fallback defaults)
        if (!isset($layoutSlots['name'])) {
            $layoutSlots['name'] = [
                'id' => null, 'left_pct' => 10, 'top_pct' => 5, 'width_pct' => 60, 'height_pct' => 8, 'rotation' => 0, 'mask' => null
            ];
        }
        if (!isset($layoutSlots['number'])) {
            $layoutSlots['number'] = [
                'id' => null, 'left_pct' => 10, 'top_pct' => 75, 'width_pct' => 30, 'height_pct' => 10, 'rotation' => 0, 'mask' => null
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

            if ($displayPrice === null && method_exists($product, 'variants')) {
                if ($product->relationLoaded('variants')) {
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

        if ($displayPrice === null) $displayPrice = 0.00;

        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
            'layoutSlots' => $layoutSlots,
            'displayPrice' => (float)$displayPrice,
        ]);
    }
}
