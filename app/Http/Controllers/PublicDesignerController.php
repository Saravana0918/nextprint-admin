<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PublicDesignerController extends Controller
{
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // ----------------------------
        // Product lookup (ALWAYS eager-load variants + views/areas)
        // ----------------------------
        $product = null;

        // helper: base query with relations
        $baseQuery = Product::with(['views','views.areas','variants']);

        if ($productId) {
            // if looks like a shopify product id (long numeric) try by shopify_product_id first
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 6) {
                $product = (clone $baseQuery)->where('shopify_product_id', $productId)->first();
            }

            // fallback by primary id
            if (!$product && ctype_digit((string)$productId)) {
                $product = (clone $baseQuery)->find((int)$productId);
            }

            // fallback: search common columns
            if (!$product) {
                $cols = [];
                if (Schema::hasColumn('products','shopify_product_id')) $cols[] = 'shopify_product_id';
                if (Schema::hasColumn('products','name')) $cols[] = 'name';
                if (Schema::hasColumn('products','sku')) $cols[] = 'sku';

                if (count($cols)) {
                    $q = clone $baseQuery;
                    $q->where(function($qq) use ($cols, $productId) {
                        foreach ($cols as $c) {
                            $qq->orWhere($c, $productId);
                        }
                    });
                    $product = $q->first();
                }
            }
        }

        if (!$product) {
            Log::warning("designer: product not found for product_id={$productId}");
            abort(404, 'Product not found');
        }

        // ----------------------------
        // Resolve view
        // ----------------------------
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            // product has views relation loaded because we eager-loaded above
            if ($product->relationLoaded('views') && $product->views->count()) {
                $view = $product->views->first();
            } else {
                $view = $product->views()->with('areas')->first();
            }
        }

        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // ----------------------------
        // Build layout slots
        // ----------------------------
        $layoutSlots = [];
        foreach ($areas as $a) {
            $left  = (float)($a->left_pct ?? $a->x_mm ?? 0);
            $top   = (float)($a->top_pct ?? $a->y_mm ?? 0);
            $w     = (float)($a->width_pct ?? $a->width_mm ?? 10);
            $h     = (float)($a->height_pct ?? $a->height_mm ?? 10);

            if ($left <= 1) $left *= 100;
            if ($top  <= 1) $top  *= 100;
            if ($w    <= 1) $w    *= 100;
            if ($h    <= 1) $h    *= 100;

            $mask = null;
            if (!empty($a->mask_svg_path)) {
                $mask = strpos($a->mask_svg_path, '/files/') === 0 ? $a->mask_svg_path : ('/files/' . ltrim($a->mask_svg_path, '/'));
            }

            $slotKey = null;
            if (!empty($a->slot_key)) $slotKey = strtolower(trim($a->slot_key));
            if (!$slotKey && !empty($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                if (strpos($n, 'num') !== false || strpos($n,'no') !== false || strpos($n,'number') !== false) $slotKey = 'number';
            }
            if (!$slotKey && isset($a->template_id)) {
                if ((int)$a->template_id === 1) $slotKey = 'name';
                if ((int)$a->template_id === 2) $slotKey = 'number';
            }
            if (!$slotKey) {
                $slotKey = 'slot_' . ($a->id ?? uniqid());
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
                'template_id' => $a->template_id ?? null,
                'mask'      => $mask,
            ];
        }

        $originalLayoutSlots = $layoutSlots;

        // ----------------------------
        // Detect artwork/logo slot
        // ----------------------------
        $hasArtworkSlot = false;
        foreach ($originalLayoutSlots as $slotKey => $slot) {
            $k = strtolower((string)$slotKey);
            if (in_array($k, ['logo','artwork','team_logo','graphic','image','art','badge','patch','patches'])) {
                $hasArtworkSlot = true; break;
            }
            if (!empty($slot['mask']) || !empty($slot['mask_url'])) {
                $hasArtworkSlot = true; break;
            }
            if (!in_array($k, ['name','number']) && !empty($slot['width_pct']) && !empty($slot['height_pct'])) {
                if ($slot['width_pct'] > 2 || $slot['height_pct'] > 2) {
                    $hasArtworkSlot = true; break;
                }
            }
        }
        $showUpload = (bool)$hasArtworkSlot;
        Log::info("designer: product_id={$product->id} showUpload=" . (int)$showUpload . " hasArtworkSlot=" . (int)$hasArtworkSlot);

        // ----------------------------
        // Filter only real areas (existing IDs) - keep same logic you had
        // ----------------------------
        $filteredLayoutSlots = [];
        if (!empty($layoutSlots) && is_array($layoutSlots)) {
            foreach (['name','number'] as $k) {
                if (isset($layoutSlots[$k]) && !empty($layoutSlots[$k]['id'])) {
                    $filteredLayoutSlots[$k] = $layoutSlots[$k];
                }
            }
            if (empty($filteredLayoutSlots['name'])) {
                $candidates = [];
                foreach ($layoutSlots as $key => $slot) {
                    $kl = strtolower((string)$key);
                    if ($kl === 'number' || $kl === 'name') continue;
                    if (!empty($slot['id'])) {
                        $w = isset($slot['width_pct']) ? (float)$slot['width_pct'] : 0;
                        $h = isset($slot['height_pct']) ? (float)$slot['height_pct'] : 0;
                        if ($w <= 1) $w *= 100;
                        if ($h <= 1) $h *= 100;
                        if ($w > 2 || $h > 2) {
                            $candidates[$key] = $slot;
                        }
                    }
                }
                if (count($candidates) === 1) {
                    $firstKey = array_keys($candidates)[0];
                    $filteredLayoutSlots['name'] = $candidates[$firstKey];
                    Log::info("designer: mapped single generic slot '{$firstKey}' -> name for product_id={$product->id}");
                }
            }
        }

        // ----------------------------
        // Compute display price (keep your existing logic)
        // ----------------------------
        $displayPrice = 0.00;
        try {
            if (isset($product->min_price) && is_numeric($product->min_price) && (float)$product->min_price > 0) {
                $displayPrice = (float)$product->min_price;
            } elseif (isset($product->price) && is_numeric($product->price) && (float)$product->price > 0) {
                $displayPrice = (float)$product->price;
            } else {
                if ($product->relationLoaded('variants')) {
                    $variantPrices = [];
                    foreach ($product->variants as $v) {
                        if (!empty($v->price) && (float)$v->price > 0) {
                            $variantPrices[] = (float)$v->price;
                        }
                    }
                    if (count($variantPrices)) $displayPrice = min($variantPrices);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('designer: price compute failed: ' . $e->getMessage());
        }

        // ----------------------------
        // Build sizeOptions and variantMap
        // ----------------------------
        $sizeOptions = [];
        $variantMap = [];
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $v) {
                $label = trim((string)($v->option_value ?? $v->option_name ?? $v->title ?? ''));
                $variantId = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? '');
                if ($label === '' || $variantId === '') continue;
                $sizeOptions[] = ['label' => $label, 'variant_id' => $variantId];
                $variantMap[strtoupper($label)] = $variantId;
            }
        }

        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
            'layoutSlots' => $filteredLayoutSlots,
            'originalLayoutSlots' => $originalLayoutSlots,
            'showUpload' => $showUpload,
            'hasArtworkSlot' => $hasArtworkSlot,
            'displayPrice' => (float)$displayPrice,
            'sizeOptions' => $sizeOptions,
            'variantMap' => $variantMap,
        ]);
    }
}
