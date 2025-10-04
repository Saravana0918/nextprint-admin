<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Http\Request;
use App\Models\PrintMethod;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller {

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

        // <-- filter by Shopify "Show in NextPrint" collection/tag
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
        $r->preview_src = Storage::disk('public')->url($raw);
    }

    return view('admin.products.index', compact('rows'));
}

public function show($id)
{
    $product = Product::findOrFail($id);

    $variantMap = [];
    $shopifyId = $product->shopify_product_id ?? $product->shopify_id ?? null;

    if ($shopifyId) {
        try {
            $shop = env('SHOPIFY_STORE');
            $adminToken = env('SHOPIFY_ADMIN_API_TOKEN');
            if ($shop && $adminToken) {
                $resp = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $adminToken,
                    'Content-Type' => 'application/json',
                ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyId}.json");

                if ($resp->successful() && ($pd = $resp->json('product'))) {
                    foreach ($pd['variants'] ?? [] as $v) {
                        $opt = trim($v['option1'] ?? $v['title'] ?? '');
                        if ($opt) {
                            // normalize typical labels
                            $variantMap[$opt] = (int)$v['id'];
                            $variantMap[strtoupper($opt)] = (int)$v['id'];
                            $variantMap[strtolower($opt)] = (int)$v['id'];
                        }
                    }
                } else {
                    \Log::warning('product fetch failed', ['status'=>$resp->status(), 'body'=>substr($resp->body(),0,500)]);
                }
            } else {
                \Log::warning('shop or admin token missing');
            }
        } catch (\Throwable $e) {
            \Log::warning('variant fetch failed', ['err'=>$e->getMessage()]);
        }
    }

    return view('designer', compact('product','variantMap'));
}


public function edit(Product $product)
{
    $methods = PrintMethod::all();  // fetch all print methods
    return view('admin.products.edit', compact('product', 'methods'));
}

public function update(Request $request, Product $product)
{
    $data = $request->validate([
        'name'   => ['required','string','max:255'],
        'price'  => ['nullable','numeric'],
        'sku'    => ['nullable','string','max:255'],
        'status' => ['nullable','in:ACTIVE,INACTIVE'],
        // this line optional, but helps debugging if it’s missing
        'print_method_ids'   => ['array'],
        'print_method_ids.*' => ['integer','exists:print_methods,id'],
    ]);

    $product->update($data);

    // IMPORTANT: write print methods to pivot
    $ids = $request->input('print_method_ids', []);   // array or []
    $product->printMethods()->sync($ids);

    return redirect()->route('admin.products')->with('success','Product updated.');
}


public function destroy(Product $product)
{
    // If you prefer soft delete, see note below.
    $product->delete();

    return redirect()
        ->route('admin.products')
        ->with('success', 'Product deleted.');
}

public function goToDecoration(Product $product)
{
    // 1) Find existing first view or create a default “Front”
    $view = $product->views()->first();   // if you have hasMany relation

    if (!$view) {
        $view = $product->views()->create([
            'name'         => 'Front',
            'dpi'          => 300,
            'rotation'     => 0,
            'image_path'   => null,        
            'bg_image_url' => $product->thumbnail ?? null,
        ]);
    }

    // 2) If view bg is empty but product has image, set it
   if ((empty($view->bg_image_url) || is_null($view->bg_image_url)) && !empty($product->thumbnail)) {
    $view->bg_image_url = $product->thumbnail;
    $view->save();
    }

    // 3) Redirect to areas editor
    return redirect()->route('admin.areas.edit', [$product->id, $view->id]);
}

public function methodsJson(\App\Models\Product $product)
{
    // eager load to avoid N+1
    $methods = $product->printMethods()->select('id','name','code','status')->orderBy('sort')->get();
    return response()->json([
        'product_id' => $product->id,
        'method_codes' => $methods->pluck('code')->values(), // ["ATC","SUB",...]
        'methods' => $methods,
    ]);
}
}
