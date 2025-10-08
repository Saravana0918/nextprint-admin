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
        $originalLayout = [];

        foreach ($areas as $a) {
            // prefer explicit percent values, fallback to mm→percent if you stored mm; here assume pct fields exist
            $left  = (float)($a->left_pct ?? $a->x_mm ?? 0);
            $top   = (float)($a->top_pct ?? $a->y_mm ?? 0);
            $w     = (float)($a->width_pct ?? $a->width_mm ?? 10);
            $h     = (float)($a->height_pct ?? $a->height_mm ?? 10);

            // If any value looks like fraction (0..1) convert to percent
            if ($left <= 1) $left *= 100;
            if ($top   <= 1) $top  *= 100;
            if ($w     <= 1) $w    *= 100;
            if ($h     <= 1) $h    *= 100;

            // mask_url - build a public URL if mask_svg_path exists
            $mask = null;
            if (!empty($a->mask_svg_path)) {
                try {
                    $possible = (string)$a->mask_svg_path;
                    if (stripos($possible, 'http://') === 0 || stripos($possible, 'https://') === 0) {
                        $mask = $possible;
                    } elseif (strpos($possible, '/files/') === 0) {
                        $mask = $possible;
                    } else {
                        // try storage public disk
                        $mask = Storage::disk('public')->url(ltrim($possible, '/'));
                    }
                } catch (\Throwable $e) {
                    $mask = '/files/' . ltrim((string)$a->mask_svg_path, '/');
                }
            }

            // slot key & name
            $slotKey = null;
            if (!empty($a->slot_key)) $slotKey = strtolower(trim($a->slot_key));
            if (!$slotKey && !empty($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                if (strpos($n, 'num') !== false || strpos($n,'no') !== false || strpos($n,'number') !== false) $slotKey = 'number';
            }

            // fallback by template_id (if you use templates to denote name/number)
            if (!$slotKey && isset($a->template_id)) {
                if ((int)$a->template_id === 1) $slotKey = 'name';
                if ((int)$a->template_id === 2) $slotKey = 'number';
            }

            // final fallback naming
            if (!$slotKey) {
                // choose a stable key: use the DB id to make unique key names
                $slotKey = 'slot_' . ($a->id ?? uniqid());
            }

            $normalized = [
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
                'mask_svg_path' => $a->mask_svg_path ?? null,
                // keep other raw attrs if needed by front-end
                'raw' => $a->toArray(),
            ];

            // add to original full layout (keyed by slotKey so it's easy to find)
            $originalLayout[$slotKey] = $normalized;

            // also add into the "working" layoutSlots (we will filter to name/number later)
            $layoutSlots[$slotKey] = $normalized;
        }

        // ensure both name/number exist in working set (fallback defaults)
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
        // Decide whether to show upload option for this product
        // (robust: detect artwork/logo slot in originalLayout OR fall back to previous heuristics)
        // ----------------------------
       $hasArtworkSlot = false;
$artKeywords = ['logo','artwork','team_logo','graphic','image','art','badge','patch'];

if (!empty($originalLayout) && is_array($originalLayout)) {
    foreach ($originalLayout as $slotKey => $slot) {
        $k = strtolower($slotKey);

        // ✅ NEW: detect TEAM LOGO or ARTWORK name
        if (!empty($slot['name'])) {
            $slotNameLower = strtolower($slot['name']);
            if (strpos($slotNameLower, 'team logo') !== false ||
                strpos($slotNameLower, 'logo') !== false ||
                strpos($slotNameLower, 'artwork') !== false ||
                strpos($slotNameLower, 'graphic') !== false) {
                $hasArtworkSlot = true;
                break;
            }
        }

        // 1) keyword in key name
        foreach ($artKeywords as $kw) {
            if (strpos($k, $kw) !== false) {
                $hasArtworkSlot = true;
                break 2;
            }
        }

        // 2) mask presence (mask implies image area)
        if (!empty($slot['mask'])) {
            $hasArtworkSlot = true;
            break;
        }

        // 3) explicit type metadata
        if (!empty($slot['type']) && in_array(strtolower($slot['type']), ['image','artwork','logo'])) {
            $hasArtworkSlot = true;
            break;
        }

        // 4) heuristics: large area
        if (!empty($slot['width_pct']) && !empty($slot['height_pct'])) {
            if ((float)$slot['width_pct'] >= 25 || (float)$slot['height_pct'] >= 25) {
                $hasArtworkSlot = true;
                break;
            }
        }
    }
}

        // start with artwork-detection result
        $showUpload = (bool)$hasArtworkSlot;

        // combine with your existing explicit heuristics so old behavior remains:
        if (!$showUpload && isset($product->is_regular)) {
            $showUpload = (bool)$product->is_regular;
        }

        if (!$showUpload && isset($product->category) && is_object($product->category)) {
            $cat = strtolower(trim($product->category->slug ?? $product->category->name ?? ''));
            if ($cat === 'regular' || $cat === 'regulars' || $cat === 'regular-category') {
                $showUpload = true;
            }
        }

        if (!$showUpload && isset($product->category) && is_string($product->category)) {
            $cat = strtolower(trim($product->category));
            if ($cat === 'regular' || stripos($cat, 'regular') !== false) $showUpload = true;
        }

        if (!$showUpload) {
            if (!empty($product->type) && strtolower($product->type) === 'regular') $showUpload = true;
            if (!$showUpload && !empty($product->tags) && is_string($product->tags) && stripos($product->tags, 'regular') !== false) $showUpload = true;
        }

        // Log the final decision for quick debugging
        \Log::info('designer: showUpload=' . (int)$showUpload . ' product_id=' . ($product->id ?? 'unknown') . ' hasArtworkSlot=' . (int)$hasArtworkSlot);

        // ----------------------------
        // Filter layoutSlots to only name & number for overlays (so overlays use only these keys)
        // Keep originalLayout (full) to send to the view for masks/uploads
        // ----------------------------
        $filteredSlots = [];
        foreach ($layoutSlots as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, ['name','number'])) {
                $filteredSlots[$lk] = $v;
            }
        }
        // make sure both exist
        if (!isset($filteredSlots['name'])) $filteredSlots['name'] = $layoutSlots['name'];
        if (!isset($filteredSlots['number'])) $filteredSlots['number'] = $layoutSlots['number'];

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
            'layoutSlots' => $filteredSlots,        // name/number for overlays
            'originalLayoutSlots' => $originalLayout, // full layout for masks/uploads
            'displayPrice' => (float)$displayPrice,
            'showUpload' => $showUpload,
        ]);
    }
}
