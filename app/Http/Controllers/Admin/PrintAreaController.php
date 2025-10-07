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
    \Log::info('BULK SAVE CALLED', ['product'=>$product->id ?? null, 'view'=>$view->id ?? null, 'payload'=> $req->all()]);
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

    \DB::beginTransaction();
    try {
        // clear old areas (full replace). If you want update-in-place, change this logic.
        PrintArea::where('product_view_id', $view->id)->delete();

        foreach ($data['areas'] as $a) {
            $row = new PrintArea(['product_view_id' => $view->id]);

            $row->template_id   = $a['template_id']   ?? null;

            // normalize mask path: store only relative path (no host)
            if (!empty($a['mask_svg_path'])) {
                $mask = $a['mask_svg_path'];
                // if the incoming value is a full URL, strip to filename/path
                $mask = preg_replace('#^https?://[^/]+/files/#', '', $mask);
                $mask = preg_replace('#^/files/#', '', $mask);
                $row->mask_svg_path = $mask;
            } else {
                $row->mask_svg_path = null;
            }

            // convert percentages (same as your code)
            if (!empty($view->view_width_pct) && !empty($view->view_height_pct)) {
                $vw = floatval($view->view_width_pct);
                $vh = floatval($view->view_height_pct);
                $vl = floatval($view->view_left_pct);
                $vt = floatval($view->view_top_pct);
                if ($vw > 0 && $vh > 0) {
                    $row->left_pct   = round($vl + (floatval($a['left_pct']) * $vw / 100.0), 5);
                    $row->top_pct    = round($vt + (floatval($a['top_pct'])  * $vh / 100.0), 5);
                    $row->width_pct  = round(floatval($a['width_pct'])  * $vw / 100.0, 5);
                    $row->height_pct = round(floatval($a['height_pct']) * $vh / 100.0, 5);
                } else {
                    $row->left_pct   = round(floatval($a['left_pct']), 5);
                    $row->top_pct    = round(floatval($a['top_pct']), 5);
                    $row->width_pct  = round(floatval($a['width_pct']), 5);
                    $row->height_pct = round(floatval($a['height_pct']), 5);
                }
            } else {
                $row->left_pct   = round(floatval($a['left_pct']), 5);
                $row->top_pct    = round(floatval($a['top_pct']), 5);
                $row->width_pct  = round(floatval($a['width_pct']), 5);
                $row->height_pct = round(floatval($a['height_pct']), 5);
            }

            $row->rotation = $a['rotation'] ?? 0;

            // save slot_key as well (important for designer)
            $row->slot_key = $a['slot_key'] ?? null;

            // name fallback
            $row->name = !empty($a['name'])
                ? $a['name']
                : (!empty($a['slot_key']) ? ucfirst($a['slot_key']) : 'Area');

            // legacy fields (kept zero)
            $row->x_mm = $row->y_mm = $row->width_mm = $row->height_mm = 0;
            $row->dpi  = 0;

            $row->save();
        }

        \DB::commit();
        return response()->json(['ok' => true]);
    } catch (\Throwable $e) {
        \DB::rollBack();
        \Log::error('Areas bulk save failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString(), 'payload'=>$data]);
        return response()->json(['ok'=>false,'error'=>'save_failed'], 500);
    }
}


}
