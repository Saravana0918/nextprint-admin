<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PrintMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        Log::info('ADMIN CHECK - products in DB flagged', ['count' => DB::table('products')->where('is_in_nextprint',1)->count()]);

        $rows = DB::table('products as p')
            ->leftJoin('shopify_products as sp', 'sp.id', '=', 'p.shopify_product_id')

            ->leftJoin(DB::raw("
                (
                SELECT ppm.product_id,
                        GROUP_CONCAT(pm.name, ', ') AS methods
                FROM product_print_method ppm
                JOIN print_methods pm ON pm.id = ppm.print_method_id
                GROUP BY ppm.product_id
                ) m
            "), 'm.product_id', '=', 'p.id')

            ->leftJoin(DB::raw("
                (
                  SELECT v1.*
                  FROM product_views v1
                  JOIN (
                      SELECT product_id, MIN(id) AS id
                      FROM product_views
                      GROUP BY product_id
                  ) x ON x.product_id = v1.product_id AND x.id = v1.id
                ) pv
            "), 'pv.product_id', '=', 'p.id')

            ->select([
                'p.id','p.name','sp.vendor','sp.status','sp.min_price',
                DB::raw('pv.image_path  as view_image'),
                DB::raw('sp.image_url   as shop_image'),
                DB::raw('p.thumbnail    as legacy_image'),
                DB::raw('COALESCE(m.methods, "") as methods'),
                'p.preview_src'
            ])

            ->selectRaw("
                COALESCE(
                  NULLIF(p.preview_src, ''),
                  NULLIF(pv.image_path, ''),
                  NULLIF(sp.image_url, ''),
                  NULLIF(p.thumbnail, '')
                ) as preview_image
            ")
            ->orderBy('p.id', 'desc')
            ->where('p.is_in_nextprint', 1)
            ->paginate(30);

        foreach ($rows as $r) {
            $raw = $r->preview_image;
            if (!$raw) { $r->preview_src = null; continue; }
            $raw = str_replace('\\','/',$raw);
            if (preg_match('~^https?://~i',$raw)) {
                $r->preview_src = $raw;
                continue;
            }
            $raw = preg_replace('~^/?(storage|public)/~','', $raw);
            $raw = ltrim($raw,'/');
            $r->preview_src = Storage::disk('public')->url($raw);
        }

        return view('admin.products.index', compact('rows'));
    }

    public function edit(Product $product)
    {
        $methods = PrintMethod::all();
        return view('admin.products.edit', compact('product', 'methods'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'   => ['required','string','max:255'],
            'price'  => ['nullable','numeric'],
            'sku'    => ['nullable','string','max:255'],
            'status' => ['nullable','in:ACTIVE,INACTIVE'],
            'print_method_ids'   => ['array'],
            'print_method_ids.*' => ['integer','exists:print_methods,id'],
        ]);

        $product->update($data);
        $ids = $request->input('print_method_ids', []);
        $product->printMethods()->sync($ids);

        return redirect()->route('admin.products')->with('success','Product updated.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('admin.products')->with('success', 'Product deleted.');
    }

    public function goToDecoration(Product $product)
{
    // 1) Find existing first view or create default “Front”
    $view = $product->views()->first();
    if (!$view) {
        $view = $product->views()->create([
            'name'         => 'Front',
            'dpi'          => 300,
            'rotation'     => 0,
            'image_path'   => null,
            'bg_image_url' => $product->thumbnail ?? null,
        ]);
    }

    // prefer view bg, then product preview_src, then thumbnail
    $bg = $view->bg_image_url ?? $product->preview_src ?? $product->thumbnail ?? null;
    if ((empty($view->bg_image_url) || is_null($view->bg_image_url)) && !empty($bg)) {
        $view->bg_image_url = $bg;
        $view->save();
    }

    return redirect()->route('admin.areas.edit', [$product->id, $view->id]);
}


    public function methodsJson(Product $product)
    {
        $methods = $product->printMethods()->select('id','name','code','status')->orderBy('sort')->get();
        return response()->json([
            'product_id' => $product->id,
            'method_codes' => $methods->pluck('code')->values(),
            'methods' => $methods,
        ]);
    }

    // ---------------- Upload / Delete preview (model-binding)
    public function uploadPreview(Request $request, Product $product)
    {
        try {
            if (!$request->hasFile('preview_image')) {
                return response()->json(['message' => 'No file uploaded'], 422);
            }

            $request->validate(['preview_image' => 'image|max:5120']);

            $file = $request->file('preview_image');
            $path = $file->store('public/product-previews');
            $publicUrl = Storage::url($path);

            $product->preview_src = $publicUrl;
            $product->touch();
            $product->save();

            Log::info('Product preview uploaded', ['product_id' => $product->id, 'path' => $path]);

            return response()->json(['url' => $publicUrl], 200);
        } catch (\Throwable $e) {
            Log::error("uploadPreview error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Upload failed'], 500);
        }
    }

    public function deletePreview(Product $product)
    {
        try {
            if (!empty($product->preview_src) && preg_match('~^/storage/(.+)$~', $product->preview_src, $m)) {
                $relative = $m[1];
                if (Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                    Log::info('Deleted preview file', ['file' => $relative]);
                }
            }

            $product->preview_src = null;
            $product->touch();
            $product->save();

            return response()->json(['ok' => true], 200);
        } catch (\Throwable $e) {
            Log::error('deletePreview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Delete failed'], 500);
        }
    }
}
