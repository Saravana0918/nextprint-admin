<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PrintMethod;

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
            ])
            ->selectRaw("
                COALESCE(
                  NULLIF(pv.image_path, ''),
                  NULLIF(sp.image_url, ''),
                  NULLIF(p.thumbnail, '')
                ) as preview_image
            ")
            ->orderBy('p.id', 'desc')
            ->where('p.is_in_nextprint', 1)
            ->paginate(30);

        // build preview_src
        foreach ($rows as $r) {
            $raw = $r->preview_image;
            if (!$raw) { $r->preview_src = null; continue; }
            $raw = str_replace('\\','/',$raw);
            if (preg_match('~^https?://~i',$raw)) { $r->preview_src = $raw; continue; }
            $raw = preg_replace('~^/?(storage|public)/~','', $raw);
            $raw = ltrim($raw,'/');
            // ensure we serve via /files/ route to avoid missing symlink issues
            if (Storage::disk('public')->exists($raw)) {
                $r->preview_src = url('/files/'.$raw);
            } else {
                // fallback: try as-is (maybe it's stored externally)
                $r->preview_src = $raw;
            }
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
        // select or create a first view
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

        // if view bg empty and product thumbnail present, set it
        if ((empty($view->bg_image_url) || is_null($view->bg_image_url)) && !empty($product->thumbnail)) {
            $view->bg_image_url = $product->thumbnail;
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

    /**
     * Upload product preview image (AJAX POST)
     * Route: POST /admin/products/{product}/preview
     */
    public function uploadPreview(Request $request, Product $product)
{
    // simple validation
    $v = Validator::make($request->all(), [
        'preview_image' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120'],
    ]);
    if ($v->fails()) {
        return response()->json(['ok'=>false,'message'=>$v->errors()->first()], 422);
    }

    // store file to public disk under product-previews
    $file = $request->file('preview_image');
    $filename = time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
    $path = $file->storeAs('product-previews', $filename, 'public'); // storage/app/public/product-previews/...

    if (!$path) {
        return response()->json(['ok'=>false,'message'=>'Save failed'], 500);
    }

    // create full url for frontend (Storage::disk('public')->url)
    $url = Storage::disk('public')->url($path);

    // Save to product table (preview column) if you have it
    // assume product has 'preview_image' or 'preview' column — adapt if column name different
    $product->preview = $path; // store relative path
    $product->save();

    // ALSO: update first ProductView image_path so decoration editor sees it
    $view = $product->views()->first(); // use relation name you have (views())
    if ($view) {
        $view->image_path = 'storage/' . ltrim($path, '/'); // this matches how PrintAreaController later strips storage/
        // OR store without 'storage/' and let PrintAreaController handle; both ok. Keep consistent.
        $view->save();
    }

    \Log::info('Product preview uploaded', ['product' => $product->id, 'path' => $path]);

    return response()->json(['ok'=>true,'url'=>$url,'path'=>$path]);
}

public function deletePreview(Request $request, Product $product)
{
    // if product->preview contains relative path like "product-previews/xxx.jpg"
    $path = $product->preview ?? null;

    // Also check view->image_path
    $view = $product->views()->first();

    // remove file if exists on disk
    if ($path && Storage::disk('public')->exists($path)) {
        Storage::disk('public')->delete($path);
    }

    // clear product preview column
    if ($path) {
        $product->preview = null;
        $product->save();
    }

    // clear view image_path too (if it pointed to this)
    if ($view) {
        // if you stored as 'storage/product-previews/...' remove check or just nullify
        $view->image_path = null;
        $view->save();
    }

    \Log::info('Product preview deleted', ['product'=>$product->id]);

    return response('', 204);
}
}
