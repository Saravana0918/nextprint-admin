<?php

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

    $product = null;

    if ($productId) {
        if (ctype_digit((string)$productId)) {
            $product = Product::with(['views','views.areas'])->find((int)$productId);
        }

        if (!$product) {
            $query = Product::with(['views','views.areas']);
            $cols = [];
            if (\Schema::hasColumn('products','shopify_product_id')) $cols[] = 'shopify_product_id';
            if (\Schema::hasColumn('products','name')) $cols[] = 'name';
            if (\Schema::hasColumn('products','sku')) $cols[] = 'sku';

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

    if (!$product) abort(404, 'Product not found');

    // Resolve view (explicit view_id or fallback)
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

    // Build layoutSlots with normalization (server-side)
    $layoutSlots = [];

    foreach ($areas as $a) {
        // read raw
        $left  = (float)($a->left_pct ?? 0);
        $top   = (float)($a->top_pct ?? 0);
        $w     = (float)($a->width_pct ?? 10);
        $h     = (float)($a->height_pct ?? 10);

        // normalize: if stored as fraction (<=1) convert to percent
        if ($left <= 1) $left *= 100;
        if ($top   <= 1) $top  *= 100;
        if ($w     <= 1) $w    *= 100;
        if ($h     <= 1) $h    *= 100;

        // determine slot_key heuristics
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

        // fallback distribution
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

    // ensure both keys exist
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

    return view('public.designer', [
        'product' => $product,
        'view'    => $view,
        'areas'   => $areas,
        'layoutSlots' => $layoutSlots,
    ]);
}
}
