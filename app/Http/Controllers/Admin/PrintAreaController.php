<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\PrintArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrintAreaController extends Controller
{
    public function edit(Product $product, ProductView $view)
    {
        $existing = $view->areas()
            ->select(
                'id','template_id','mask_svg_path',
                'x_mm','y_mm','width_mm','height_mm','dpi','rotation',
                'left_pct','top_pct','width_pct','height_pct',
                'name'
            )
            ->get();

        // uploaded local image → public URL
        $uploaded = null;
        if (!empty($view->image_path)) {
            $p = str_replace('\\','/',$view->image_path);
            $p = preg_replace('~^/?(storage|public)/~','',$p);
            $uploaded = Storage::disk('public')->url(ltrim($p,'/'));
        }

        // Shopify image (relation optional)
        $shopImage = optional($product->shopifyProduct)->image_url;

        // Final fallback chain
        $bgUrl = $uploaded ?: ($view->bg_image_url ?: ($product->thumbnail ?: $shopImage));

        return view('admin.areas.edit', compact('product','view','existing','bgUrl'));
    }

    public function update(Request $req, Product $product, ProductView $view)
    {
        $data = $req->validate([
            'x_mm'      => 'required|numeric',
            'y_mm'      => 'required|numeric',
            'width_mm'  => 'required|numeric',
            'height_mm' => 'required|numeric',
            'dpi'       => 'required|integer',
            'rotation'  => 'integer',
        ]);

        $area = $view->areas()->firstOrFail();
        $area->update($data);

        return back()->with('ok', 'Decoration area saved.');
    }

public function bulkSave(Request $req, Product $product, ProductView $view)
{
    $data = $req->validate([
        'stage_w' => 'nullable|numeric',
        'stage_h' => 'nullable|numeric',
        'areas'   => 'required|array',
        'areas.*.id'            => 'nullable|integer',
        'areas.*.template_id'   => 'nullable|integer',
        'areas.*.mask_svg_path' => 'nullable|string',
        'areas.*.left_pct'      => 'required|numeric',
        'areas.*.top_pct'       => 'required|numeric',
        'areas.*.width_pct'     => 'required|numeric',
        'areas.*.height_pct'    => 'required|numeric',
        'areas.*.rotation'      => 'nullable|numeric',
        'areas.*.name'          => 'nullable|string',
        'areas.*.slot_key'      => 'nullable|string',
    ]);

    // 🔥 Clear old areas
    PrintArea::where('product_view_id', $view->id)->delete();

    foreach ($data['areas'] as $a) {
        $row = new PrintArea(['product_view_id' => $view->id]);

        $row->template_id   = $a['template_id']   ?? null;
        $row->mask_svg_path = $a['mask_svg_path'] ?? null;

        // ✅ Single vs Collage handling
        // If the view has valid non-zero view_width_pct/height_pct we
// convert relative sub-view % → global %. Otherwise assume the
// incoming values are already absolute percentages and use them.
if (!empty($view->view_width_pct) && !empty($view->view_height_pct)) {
    // defensive numeric casts
    $vw = floatval($view->view_width_pct);
    $vh = floatval($view->view_height_pct);
    $vl = floatval($view->view_left_pct);
    $vt = floatval($view->view_top_pct);

    // convert only when view percentages make sense (>0)
    if ($vw > 0 && $vh > 0) {
        $row->left_pct   = round($vl + (floatval($a['left_pct']) * $vw / 100.0), 5);
        $row->top_pct    = round($vt + (floatval($a['top_pct'])  * $vh / 100.0), 5);
        $row->width_pct  = round(floatval($a['width_pct'])  * $vw / 100.0, 5);
        $row->height_pct = round(floatval($a['height_pct']) * $vh / 100.0, 5);
    } else {
        // fallback — treat incoming as absolute %
        $row->left_pct   = round(floatval($a['left_pct']), 5);
        $row->top_pct    = round(floatval($a['top_pct']), 5);
        $row->width_pct  = round(floatval($a['width_pct']), 5);
        $row->height_pct = round(floatval($a['height_pct']), 5);
    }
} else {
    // no view percentages defined — use incoming values as-is
    $row->left_pct   = round(floatval($a['left_pct']), 5);
    $row->top_pct    = round(floatval($a['top_pct']), 5);
    $row->width_pct  = round(floatval($a['width_pct']), 5);
    $row->height_pct = round(floatval($a['height_pct']), 5);
}


        $row->rotation   = $a['rotation'] ?? 0;

        $row->name = !empty($a['name'])
            ? $a['name']
            : (!empty($a['slot_key']) ? ucfirst($a['slot_key']) : 'Area');

        $row->x_mm = $row->y_mm = $row->width_mm = $row->height_mm = 0;
        $row->dpi  = 0;

        $row->save();
    }

    return response()->json(['ok' => true]);
}

}
