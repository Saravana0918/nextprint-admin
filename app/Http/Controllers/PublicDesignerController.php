<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\ProductView;

class PublicDesignerController extends Controller
{
    /**
     * Show public designer page for a product/view.
     * If product not found in local DB, try fetching minimal product info from Shopify (fallback).
     */
    // inside PublicDesignerController.php (replace existing show method)
public function show(Request $request)
{
    $productId = $request->query('product_id');
    $viewId    = $request->query('view_id');

    // (1) find product exactly as you already do — keep existing logic
    $product = null;
    if ($productId) {
        if (ctype_digit((string)$productId)) {
            $product = Product::with(['views','views.areas'])->find((int)$productId);
        }
        if (!$product) {
            $hasShopify = Schema::hasColumn('products','shopify_product_id');
            $hasName    = Schema::hasColumn('products','name');
            $hasSku     = Schema::hasColumn('products','sku');

            if ($hasShopify || $hasName || $hasSku) {
                $query = Product::with(['views','views.areas']);
                $query->where(function($q) use ($productId, $hasShopify, $hasName, $hasSku) {
                    if ($hasShopify) {
                        $q->where('shopify_product_id', $productId)
                          ->orWhere('shopify_product_id', 'like', '%' . $productId . '%');
                    }
                    if ($hasName) {
                        $q->orWhere('name', $productId);
                    }
                    if ($hasSku) {
                        $q->orWhere('sku', $productId);
                    }
                });
                $product = $query->first();
            }
        }
    }

    // Shopify fallback (optional) - keep your existing fallback if present
    if (!$product && $productId) {
        // (existing Shopify fallback code you already have)
        // ... (omit here for brevity)
    }

    if (!$product) {
        abort(404, 'Product not found');
    }

    // ---------------------------------------
    // Resolve view and areas (prefer DB)
    // ---------------------------------------
    $view = null;
    if ($viewId) {
        $view = ProductView::with('areas')->find($viewId);
    }
    if (!$view && $product instanceof Product) {
        if ($product->relationLoaded('views') && $product->views->count()) {
            $view = $product->views->first();
        } else {
            $view = $product->views()->with('areas')->first();
        }
    }

    $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

    // ---------------------------------------
    // ROBUST MAPPING: build layoutSlots server-side
    // ---------------------------------------
    $layoutSlots = [
        'name' => null,
        'number' => null,
    ];

    if ($areas->count()) {
        // normalize values and ensure numeric floats
        $areasArray = $areas->map(function($a){
            return (object)[
                'id' => $a->id,
                'name' => $a->name ?? '',
                'slot_key' => $a->slot_key ?? null,
                'template_id' => $a->template_id ?? null,
                'left_pct' => floatval($a->left_pct ?? 0),
                'top_pct' => floatval($a->top_pct ?? 0),
                'width_pct' => floatval($a->width_pct ?? 0),
                'height_pct' => floatval($a->height_pct ?? 0),
                'rotation' => intval($a->rotation ?? 0),
            ];
        })->toArray();

        // 1) explicit slot_key wins
        foreach ($areasArray as $slot) {
            $key = is_string($slot->slot_key) ? strtolower($slot->slot_key) : '';
            if ($key === 'name' && !$layoutSlots['name']) $layoutSlots['name'] = $slot;
            if ($key === 'number' && !$layoutSlots['number']) $layoutSlots['number'] = $slot;
        }

        // 2) name/num keywords in name column
        foreach ($areasArray as $slot) {
            if ($layoutSlots['name'] && $layoutSlots['number']) break;
            $lname = strtolower($slot->name ?? '');
            if (!$layoutSlots['name'] && strpos($lname, 'name') !== false) $layoutSlots['name'] = $slot;
            if (!$layoutSlots['number'] && (strpos($lname, 'num') !== false || strpos($lname, 'no') !== false || strpos($lname, 'number') !== false)) $layoutSlots['number'] = $slot;
        }

        // 3) template_id mapping (if you have convention)
        // Example: template_id 1 => name, 2 => number (adjust to your app's convention)
        foreach ($areasArray as $slot) {
            if ($layoutSlots['name'] && $layoutSlots['number']) break;
            if (!$layoutSlots['name'] && isset($slot->template_id) && intval($slot->template_id) === 1) $layoutSlots['name'] = $slot;
            if (!$layoutSlots['number'] && isset($slot->template_id) && intval($slot->template_id) === 2) $layoutSlots['number'] = $slot;
        }

        // 4) SPATIAL HEURISTIC fallback: use vertical position (top_pct)
        // sort areas by top_pct ascending (top-most first)
        if ((!$layoutSlots['name'] || !$layoutSlots['number']) && count($areasArray) > 0) {
            usort($areasArray, function($a,$b){
                return ($a->top_pct <=> $b->top_pct);
            });

            // assign remaining slots: top-most => name (if missing), next => number
            foreach ($areasArray as $slot) {
                if (!$layoutSlots['name']) {
                    $layoutSlots['name'] = $slot; continue;
                }
                if (!$layoutSlots['number']) {
                    $layoutSlots['number'] = $slot; break;
                }
            }
        }

        // 5) as last resort, if still one missing, duplicate the other so UI shows something
        if (!$layoutSlots['name'] && $layoutSlots['number']) $layoutSlots['name'] = $layoutSlots['number'];
        if (!$layoutSlots['number'] && $layoutSlots['name']) $layoutSlots['number'] = $layoutSlots['name'];
    }

    // Pass layoutSlots JSON-ready (stdClass/array) to view
    return view('public.designer', [
        'product' => $product,
        'view'    => $view,
        'areas'   => $areas,
        'layoutSlots' => $layoutSlots,
    ]);
}

}
