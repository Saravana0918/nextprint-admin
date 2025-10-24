<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PublicDesignerController extends Controller
{
    /**
     * Show public designer for a product + view
     *
     * Expects query params: product_id (shopify id or local id), view_id (optional)
     *
     * Returns view with:
     * - product, view, areas
     * - layoutSlots (filtered name+number)
     * - originalLayoutSlots (full set)
     * - artworkUrl, artworkOrigW, artworkOrigH
     * - showUpload (bool)
     * - displayPrice (float)
     */
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // ----------------------------
        // product lookup (robust) - eager load relations
        // ----------------------------
        $product = null;

        if ($productId) {
            // if looks like long numeric => possibly shopify id
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 8) {
                $product = Product::with(['views', 'views.areas', 'variants'])
                    ->where('shopify_product_id', $productId)
                    ->first();

                if (! $product) {
                    // fallback: local primary key
                    $product = Product::with(['views', 'views.areas', 'variants'])->find((int)$productId);
                }
            } else {
                // short numeric likely local id
                if (ctype_digit((string)$productId)) {
                    $product = Product::with(['views', 'views.areas', 'variants'])->find((int)$productId);
                }
            }

            // further fallback: try matching across common columns
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
            Log::warning("designer: product not found for product_id={$productId}");
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
        // Build layoutSlots with normalization (returns percent values 0..100)
        // ----------------------------
        $layoutSlots = [];

        foreach ($areas as $a) {
            // Prefer percent fields; fallback to mm or px fields if present.
            // We'll normalize to percent (0..100)
            $leftRaw  = $a->left_pct ?? $a->x_pct ?? $a->left ?? $a->x ?? null;
            $topRaw   = $a->top_pct  ?? $a->y_pct ?? $a->top  ?? $a->y ?? null;
            $wRaw     = $a->width_pct ?? $a->w_pct ?? $a->width ?? $a->w ?? null;
            $hRaw     = $a->height_pct ?? $a->h_pct ?? $a->height ?? $a->h ?? null;

            // If DB stored mm or px you'll need to convert using the original artwork dimensions.
            // Assume values <= 1 are fractional and represent 0..1 (multiply by 100)
            $left  = is_null($leftRaw) ? 0 : (float)$leftRaw;
            $top   = is_null($topRaw)  ? 0 : (float)$topRaw;
            $w     = is_null($wRaw)    ? 10 : (float)$wRaw;
            $h     = is_null($hRaw)    ? 10 : (float)$hRaw;

            if ($left <= 1) $left *= 100;
            if ($top  <= 1) $top  *= 100;
            if ($w    <= 1) $w    *= 100;
            if ($h    <= 1) $h    *= 100;

            // mask_url - prefer public storage URL if mask path stored
            $mask = null;
            if (!empty($a->mask_svg_path)) {
                try {
                    // If mask path is stored in storage/app/public...
                    if (Storage::disk('public')->exists($a->mask_svg_path)) {
                        $mask = Storage::disk('public')->url($a->mask_svg_path);
                    } else {
                        // fallback: if it's already a URL or "/files/..." path use directly
                        $mask = (strpos($a->mask_svg_path, 'http') === 0 || strpos($a->mask_svg_path, '/')) ? $a->mask_svg_path : null;
                    }
                } catch (\Throwable $e) {
                    $mask = null;
                }
            }

            // slot key determination (name/number heuristics)
            $slotKey = null;
            if (!empty($a->slot_key)) $slotKey = strtolower(trim($a->slot_key));
            if (!$slotKey && !empty($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                if (strpos($n, 'num') !== false || strpos($n,'no') !== false || strpos($n,'number') !== false) $slotKey = 'number';
            }

            // template_id heuristics
            if (!$slotKey && isset($a->template_id)) {
                if ((int)$a->template_id === 1) $slotKey = 'name';
                if ((int)$a->template_id === 2) $slotKey = 'number';
            }

            // final fallback stable key
            if (!$slotKey) {
                $slotKey = 'slot_' . ($a->id ?? uniqid());
            }

            $layoutSlots[$slotKey] = [
                'id' => $a->id ?? null,
                'left_pct'  => round($left, 6),
                'top_pct'   => round($top, 6),
                'width_pct' => round($w, 6),
                'height_pct'=> round($h, 6),
                'rotation'  => (int)($a->rotation ?? 0),
                'name'      => $a->name ?? null,
                'slot_key'  => $a->slot_key ?? null,
                'template_id'=> $a->template_id ?? null,
                'mask'      => $mask,
                'raw'       => $a, // keep original area object for debug if needed
            ];
        }

        // Ensure 'name' and 'number' exist as fallbacks (sane defaults)
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

        // Keep a copy of the full/original layout for front-end debug & artwork detection
        $originalLayoutSlots = $layoutSlots;

        // ----------------------------
        // Determine if the view has an artwork/logo area (to show upload)
        // ----------------------------
        $hasArtworkSlot = false;
        foreach ($originalLayoutSlots as $slotKey => $slot) {
            $k = strtolower((string)$slotKey);
            if (in_array($k, ['logo','artwork','team_logo','graphic','image','art','badge','patch','patches'])) {
                $hasArtworkSlot = true;
                break;
            }
            if (!empty($slot['mask'])) {
                $hasArtworkSlot = true;
                break;
            }
            if (!in_array($k, ['name','number']) && !empty($slot['width_pct']) && !empty($slot['height_pct'])) {
                if ($slot['width_pct'] > 2 || $slot['height_pct'] > 2) {
                    $hasArtworkSlot = true;
                    break;
                }
            }
        }
        $showUpload = (bool)$hasArtworkSlot;

        Log::info("designer: product_id={$product->id} showUpload=" . (int)$showUpload . " hasArtworkSlot=" . (int)$hasArtworkSlot);

        // ----------------------------
        // Resolve artwork URL and original pixel dimensions (origW, origH)
        // ----------------------------
        $artworkUrl = null;
        $origW = 0;
        $origH = 0;

        if ($view) {
            // prefer stored public URL fields if present
            if (!empty($view->image_url)) {
                $artworkUrl = $view->image_url;
            } elseif (!empty($view->image_path)) {
                // common: path stored in storage/app/public
                try {
                    if (Storage::disk('public')->exists($view->image_path)) {
                        $artworkUrl = Storage::disk('public')->url($view->image_path);
                    } else {
                        // if image_path looks like absolute path or URL, use directly
                        $artworkUrl = $view->image_path;
                    }
                } catch (\Throwable $e) {
                    $artworkUrl = $view->image_path;
                }
            } elseif (!empty($view->image)) {
                // other naming conventions
                $artworkUrl = $view->image;
            }
        }

        // original dims: prefer DB fields then try reading file on disk
        if ($view) {
            if (!empty($view->original_width) && !empty($view->original_height)) {
                $origW = (int)$view->original_width;
                $origH = (int)$view->original_height;
            } else {
                // try reading file from storage (if we have a storage path)
                try {
                    $possiblePaths = [];
                    if (!empty($view->image_path)) $possiblePaths[] = storage_path('app/public/' . ltrim($view->image_path, '/'));
                    if (!empty($view->image_local_path)) $possiblePaths[] = $view->image_local_path;
                    // if artworkUrl is a storage url '/storage/...' derive path
                    if (!empty($artworkUrl) && strpos($artworkUrl, '/storage/') !== false) {
                        $rel = substr($artworkUrl, strpos($artworkUrl, '/storage/') + 9);
                        $possiblePaths[] = storage_path('app/public/' . ltrim($rel, '/'));
                    }

                    foreach ($possiblePaths as $p) {
                        if (!empty($p) && file_exists($p)) {
                            try {
                                [$w, $h] = getimagesize($p);
                                if ($w && $h) {
                                    $origW = (int)$w;
                                    $origH = (int)$h;
                                    break;
                                }
                            } catch (\Throwable $e) {
                                // ignore and continue
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('designer: error detecting artwork size: ' . $e->getMessage());
                }
            }
        }

        // fallback if still missing
        if ($origW <= 0) $origW = 1200;
        if ($origH <= 0) $origH = 1200;

        // ----------------------------
        // Compute a safe display price
        // ----------------------------
        $displayPrice = null;
        try {
            if (isset($product->min_price) && is_numeric($product->min_price) && (float)$product->min_price > 0) {
                $displayPrice = (float)$product->min_price;
            } elseif (isset($product->price) && is_numeric($product->price) && (float)$product->price > 0) {
                $displayPrice = (float)$product->price;
            } else {
                // try variants
                if ($product->relationLoaded('variants')) {
                    $variantPrices = [];
                    foreach ($product->variants as $v) {
                        if (!empty($v->price) && (float)$v->price > 0) $variantPrices[] = (float)$v->price;
                        elseif (!empty($v->price_cents) && (int)$v->price_cents > 0) $variantPrices[] = (float)$v->price_cents / 100;
                        elseif (!empty($v->price_in_cents) && (int)$v->price_in_cents > 0) $variantPrices[] = (float)$v->price_in_cents / 100;
                    }
                    if (count($variantPrices)) $displayPrice = min($variantPrices);
                } else {
                    // DB-level min
                    if (Schema::hasColumn('variants','price')) {
                        $minP = $product->variants()->min('price');
                        if ($minP && $minP > 0) $displayPrice = (float)$minP;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('designer: price compute failed: ' . $e->getMessage());
        }
        if ($displayPrice === null) $displayPrice = 0.00;

        // ----------------------------
        // Filter layoutSlots to only name + number for the front-end interactive UI
        // but also pass originalLayoutSlots for cropping/artwork coordinates
        // ----------------------------
        $filteredLayoutSlots = [];
        if (!empty($layoutSlots) && is_array($layoutSlots)) {
            foreach (['name', 'number'] as $k) {
                if (isset($layoutSlots[$k])) $filteredLayoutSlots[$k] = $layoutSlots[$k];
            }
        }

        // final return
        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
            'layoutSlots' => $filteredLayoutSlots,
            'originalLayoutSlots' => $originalLayoutSlots,
            'showUpload' => $showUpload,
            'hasArtworkSlot' => $hasArtworkSlot,
            'artworkUrl' => $artworkUrl,
            'artworkOrigW' => (int)$origW,
            'artworkOrigH' => (int)$origH,
            'displayPrice' => (float)$displayPrice,
        ]);
    }
}
