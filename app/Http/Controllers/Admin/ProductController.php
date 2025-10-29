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
    /**
     * Product listing for admin (with preview_src build)
     */
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
                'p.preview_src' // include direct preview_src from products table
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

        // build preview_src absolute URL for each row
        foreach ($rows as $r) {
            $raw = $r->preview_image;
            if (!$raw) { $r->preview_src = null; continue; }
            $raw = str_replace('\\','/',$raw);
            if (preg_match('~^https?://~i',$raw)) {
                $r->preview_src = $raw;
                continue;
            }
            // remove leading storage/public
            $raw = preg_replace('~^/?(storage|public)/~','', $raw);
            $raw = ltrim($raw,'/');
            $r->preview_src = Storage::disk('public')->url($raw);
        }

        return view('admin.products.index', compact('rows'));
    }

    /**
     * Edit product page
     */
    public function edit(Product $product)
    {
        $methods = PrintMethod::all();  // fetch all print methods
        return view('admin.products.edit', compact('product', 'methods'));
    }

    /**
     * Update product (basic fields + print methods)
     */
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

        // write print methods to pivot
        $ids = $request->input('print_method_ids', []);
        $product->printMethods()->sync($ids);

        return redirect()->route('admin.products')->with('success','Product updated.');
    }

    /**
     * Delete product (hard delete here)
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('admin.products')
            ->with('success', 'Product deleted.');
    }

    /**
     * Navigate to Decoration (areas) editor for a product
     * Ensures the view has a sensible bg image (preview_src preferred)
     */
    public function goToDecoration(Product $product)
    {
        // find existing first view or create default "Front"
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

        // prefer preview_src, then thumbnail
        $bg = $product->preview_src ?? $product->thumbnail ?? null;

        if (!empty($bg)) {
            // normalize stored value to RELATIVE path (strip leading /storage/ or full url)
            if (preg_match('~^/storage/(.+)$~', $bg, $m)) {
                $bgRel = $m[1];
            } elseif (preg_match('~^https?://.+/storage/(.+)$~', $bg, $m)) {
                $bgRel = $m[1];
            } elseif (preg_match('~^public/(.+)$~', $bg, $m)) {
                $bgRel = $m[1];
            } else {
                $bgRel = ltrim($bg, '/');
            }

            // If view doesn't have image_path or bg_image_url, set them now
            if (empty($view->image_path) || is_null($view->image_path)) {
                $view->image_path = $bgRel;
            }
            if (empty($view->bg_image_url) || is_null($view->bg_image_url)) {
                $view->bg_image_url = $bgRel;
            }
            $view->save();
        }

        // redirect to areas editor
        return redirect()->route('admin.areas.edit', [$product->id, $view->id]);
    }

    /**
     * Return product print methods JSON (used elsewhere)
     */
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
     * Upload preview image for a product (AJAX)
     * POST /admin/products/{product}/preview
     */
    public function uploadPreview(Request $request, $productId)
    {
        try {
            $product = Product::findOrFail($productId);

            if (!$request->hasFile('preview_image')) {
                return response()->json(['message' => 'No file uploaded'], 422);
            }

            $request->validate([
                'preview_image' => 'image|max:5120' // 5MB
            ]);

            $file = $request->file('preview_image');

            // STORE correctly on the "public" disk. This returns "product-previews/filename.jpg"
            $relative = $file->store('product-previews', 'public');

            // delete old file if exists (handle old value formats)
            $old = $product->preview_src;
            if (!empty($old)) {
                if (preg_match('~^/storage/(.+)$~', $old, $m)) {
                    $oldRel = $m[1];
                } elseif (preg_match('~^https?://.+/storage/(.+)$~', $old, $m)) {
                    $oldRel = $m[1];
                } elseif (preg_match('~^public/(.+)$~', $old, $m)) {
                    $oldRel = $m[1];
                } else {
                    $oldRel = $old;
                }

                if (!empty($oldRel) && Storage::disk('public')->exists($oldRel)) {
                    Storage::disk('public')->delete($oldRel);
                    Log::info('Deleted old preview during upload', ['file' => $oldRel, 'product_id' => $product->id]);
                }
            }

            // Save RELATIVE path to DB (no leading "public/" or "/storage/")
            $product->preview_src = $relative;
            $product->touch();
            $product->save();

            // LOG
            Log::info('Product preview uploaded', ['product_id' => $product->id, 'path' => $relative]);

            // Also insert into product_previews table for history (optional, safe-guard)
            try {
                DB::table('product_previews')->insert([
                    'product_id' => $product->id,
                    'path'       => $relative,
                    'url'        => Storage::disk('public')->url($relative),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (\Throwable $e) {
                Log::warning('Could not insert product_previews row: ' . $e->getMessage());
            }

            // Update the product_views record (first/front view) so Decoration Area sees it immediately
            try {
                $view = $product->views()->first();
                if (!$view) {
                    $view = $product->views()->create([
                        'name' => 'Front',
                        'dpi' => 300,
                        'rotation' => 0,
                        'image_path' => null,
                        'bg_image_url' => null
                    ]);
                }

                // store relative path for image_path & bg_image_url
                $view->image_path = $relative;
                $view->bg_image_url = $relative;
                // also optional fields some code expects (candidate/relative/bgUrl)
                if (Schema::hasColumn('product_views', 'candidate')) {
                    $view->candidate = '/storage/' . $relative;
                }
                if (Schema::hasColumn('product_views', 'relative')) {
                    $view->relative = $relative;
                }
                $view->save();

                Log::info('Updated product_view with preview', ['product_id' => $product->id, 'view_id' => $view->id, 'image_path' => $relative]);
            } catch (\Throwable $e) {
                Log::warning('Could not update product_views row: ' . $e->getMessage());
            }

            // return public URL for client usage
            $publicUrl = Storage::disk('public')->url($relative); // /storage/product-previews/xxx.jpg

            return response()->json(['url' => $publicUrl, 'path' => $relative], 200);
        } catch (\Throwable $e) {
            Log::error('uploadPreview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Upload failed'], 500);
        }
    }

    /**
     * Delete preview image for a product (AJAX)
     * DELETE /admin/products/{product}/preview
     */
    public function deletePreview($productId)
    {
        try {
            $product = Product::findOrFail($productId);

            $deleted = false;
            $old = $product->preview_src;

            if (!empty($old)) {
                if (preg_match('~^/storage/(.+)$~', $old, $m)) {
                    $relative = $m[1];
                } elseif (preg_match('~^https?://.+/storage/(.+)$~', $old, $m)) {
                    $relative = $m[1];
                } elseif (preg_match('~^public/(.+)$~', $old, $m)) {
                    $relative = $m[1];
                } else {
                    $relative = $old;
                }

                if ($relative && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                    Log::info('Deleted preview file', ['file' => $relative, 'product_id' => $product->id]);
                    $deleted = true;
                }
            }

            // unset preview on product
            $product->preview_src = null;
            $product->touch();
            $product->save();

            // Also clear view image if present
            try {
                $view = $product->views()->first();
                if ($view) {
                    $view->image_path = null;
                    $view->bg_image_url = null;
                    if (Schema::hasColumn('product_views', 'candidate')) {
                        $view->candidate = null;
                    }
                    if (Schema::hasColumn('product_views', 'relative')) {
                        $view->relative = null;
                    }
                    $view->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Could not clear product_views row on delete: ' . $e->getMessage());
            }

            return response()->json(['ok' => true, 'deleted' => $deleted], 200);
        } catch (\Throwable $e) {
            Log::error('deletePreview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Delete failed'], 500);
        }
    }
}
