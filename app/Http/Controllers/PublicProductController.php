<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;            // ✅ correct import
use App\Models\ShopifyProduct;
use App\Models\Product;

class PublicProductController extends Controller
{
    /**
     * GET /api/public/products/{handle}/methods
     * Return method codes for a product handle (e.g., ["ADD TEXT"])
     */
     public function methodsByHandle(string $handle)
    {
        $shop = ShopifyProduct::where('handle', $handle)->first();
        if (!$shop) {
            return response()->json(['method_codes' => []]);
        }

        $product = Product::where('shopify_product_id', $shop->id)->first();
        if (!$product && Schema::hasColumn('products','handle')) {
            $product = Product::where('handle', $handle)->first();
        }
        if (!$product) {
            return response()->json(['method_codes' => []]);
        }

        $codes = $product->printMethods()
            ->pluck('code')
            ->map(fn($c) => strtoupper(trim($c)))
            ->values();

        return response()->json([
            'product_id'   => $product->id,
            'shopify_product_id'   => $shop->id,
            'method_codes' => $codes,
        ]);
    }

    /**
     * GET /api/public/products/{handle}/layout
     * Returns the preview image + normalized % positions for Name/Number
     * based on admin-drawn rectangles.
     */
    public function layout(string $handle)
{
    $shop = ShopifyProduct::where('handle', $handle)->first();
    $product = $shop
        ? Product::where('shopify_product_id', $shop->id)->first()
        : null;

    if (!$product && Schema::hasColumn('products','handle')) {
        $product = Product::where('handle', $handle)->first();
    }
    if (!$product) {
        return response()->json(['error' => 'not_found'], 404);
    }

    $tblViews = 'product_views';
    $tblAreas = 'print_areas';
    $tblTpl   = 'decoration_area_templates';

    $best = DB::table("$tblViews as v")
        ->leftJoin("$tblAreas as p", 'p.product_view_id', '=', 'v.id')
        ->leftJoin("$tblTpl as t", 't.id', '=', 'p.template_id')
        ->where('v.product_id', $product->id)
        ->whereIn('t.slot_key', ['name','number'])
        ->select('v.id', DB::raw('COUNT(DISTINCT t.slot_key) as slot_count'))
        ->groupBy('v.id')
        ->orderByDesc('slot_count')
        ->orderBy('v.id')
        ->first();

    if (!$best) {
        $best = DB::table($tblViews)->where('product_id',$product->id)->orderBy('id')->select('id')->first();
    }
    if (!$best) {
        $img = $product->image_url ?? asset('images/placeholder.png');
        return response()->json(['image'=>$img,'image_w'=>1200,'image_h'=>1600,'slots'=>[]]);
    }

    $view = DB::table($tblViews)->where('id', $best->id)->first();

    $img = null;
    foreach (['image_url','image','src','preview_src','path'] as $col) {
        if (!empty($view->$col)) { $img = $view->$col; break; }
    }
    if (!$img) {
        $img = $product->image_url ?? asset('images/placeholder.png');
    }

    $imgW = null; $imgH = null;
    foreach (['image_width','width','w_px','view_width'] as $col) {
        if (!empty($view->$col)) { $imgW = (int) $view->$col; break; }
    }
    foreach (['image_height','height','h_px','view_height'] as $col) {
        if (!empty($view->$col)) { $imgH = (int) $view->$col; break; }
    }
    if (!$imgW || !$imgH) { $imgW = $imgW ?: 1200; $imgH = $imgH ?: 1600; }

    $rows = DB::table("$tblAreas as p")
        ->join("$tblTpl as t",'t.id','=','p.template_id')
        ->where('p.product_view_id', $view->id)
        ->whereIn('t.slot_key', ['name','number'])
        ->select(
            't.slot_key',
            'p.left_pct','p.top_pct','p.width_pct','p.height_pct',
            'p.x_mm','p.y_mm','p.width_mm','p.height_mm','p.dpi',
            'p.rotation'
        )
        ->get();

    $slots = [];
    foreach ($rows as $a) {
        if ($a->width_pct !== null && $a->height_pct !== null) {
            $cx = (float)($a->left_pct + $a->width_pct  / 2);
            $cy = (float)($a->top_pct  + $a->height_pct / 2);

            $slots[$a->slot_key] = [
            'left_pct'     => (float)$a->left_pct,
            'top_pct'      => (float)$a->top_pct,
            'width_pct'    => (float)$a->width_pct,   
            'height_pct'   => (float)$a->height_pct,   
            'rotation'     => (float)($a->rotation ?: 0),
        ];
            continue;
        }
    }

            return response()->json([
            'image'   => $img,
            'image_w' => $imgW,
            'image_h' => $imgH,
            'view'    => $view->side ?? null,
            'slots'   => $slots,
        ]);
}
}
