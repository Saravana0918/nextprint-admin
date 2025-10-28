<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        Log::info('ADMIN CHECK - products in DB flagged', ['count' => \DB::table('products')->where('is_in_nextprint',1)->count()]);
        $rows = \DB::table('products as p')
            ->leftJoin('shopify_products as sp', 'sp.id', '=', 'p.shopify_product_id')
            ->leftJoin(\DB::raw("(
                SELECT ppm.product_id,
                       GROUP_CONCAT(pm.name, ', ') AS methods
                FROM product_print_method ppm
                JOIN print_methods pm ON pm.id = ppm.print_method_id
                GROUP BY ppm.product_id
            ) m"), 'm.product_id', '=', 'p.id')
            ->leftJoin(\DB::raw("(
                SELECT v1.*
                FROM product_views v1
                JOIN (
                    SELECT product_id, MIN(id) AS id
                    FROM product_views
                    GROUP BY product_id
                ) x ON x.product_id = v1.product_id AND x.id = v1.id
            ) pv"), 'pv.product_id', '=', 'p.id')
            ->select([
                'p.id','p.name','sp.vendor','sp.status','sp.min_price',
                \DB::raw('pv.image_path  as view_image'),
                \DB::raw('sp.image_url   as shop_image'),
                \DB::raw('p.thumbnail    as legacy_image'),
                \DB::raw('COALESCE(m.methods, "") as methods'),
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
            // Storage::disk('public')->url expects relative path
            $r->preview_src = Storage::disk('public')->url($raw);
        }

        return view('admin.products.index', compact('rows'));
    }

    public function edit(Product $product)
    {
        $methods = \App\Models\PrintMethod::all();  // fetch all print methods
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
        // first view or create "Front"
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
     * Upload preview image for product (XHR)
     * POST /admin/products/{product}/preview
     */
    public function uploadPreview(Request $r, $productId)
{
    $r->validate(['view_image' => 'required|image|max:8192']); // 8MB
    $product = Product::findOrFail($productId);

    $file = $r->file('view_image');
    $filename = 'preview_' . $product->id . '_' . time() . '.' . $file->getClientOriginalExtension();

    // store into storage/app/public/product-previews
    $path = $file->storeAs('product-previews', $filename, 'public'); // disk 'public' => storage/app/public

    // public path for blade: /storage/product-previews/filename
    $publicPath = '/storage/' . ltrim($path, '/');

    // update DB
    $product->preview_src = $publicPath;
    $product->updated_at = now();
    $product->save();

    // optionally queue any image processing, thumbnails, or log
    \Log::info("admin: uploaded preview for product {$product->id} -> {$publicPath}");

    return back()->with('ok', 'Preview uploaded.');
}

    /**
     * Delete preview
     * DELETE /admin/products/{product}/preview
     */
    public function deletePreview(Product $product)
    {
        try {
            $path = $product->thumbnail;
            if ($path) {
                // normalize
                $p = str_replace('\\','/',$path);
                $p = preg_replace('~^/?(storage|public)/~','',$p);
                $p = ltrim($p, '/');

                if (Storage::disk('public')->exists($p)) {
                    Storage::disk('public')->delete($p);
                    Log::info('Deleted product preview file', ['product_id'=>$product->id, 'path'=>$p]);
                }
            }

            // clear db column
            $product->thumbnail = null;
            $product->save();

            return response()->json(['ok'=>true]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete product preview: '.$e->getMessage(), ['product'=>$product->id]);
            return response()->json(['ok'=>false,'message'=>'delete_failed'], 500);
        }
    }
}
