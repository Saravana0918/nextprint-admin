<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;      // make sure Product model correct
use App\Models\ProductView;  // make sure ProductView model correct
use Illuminate\Support\Facades\Schema;

class PublicDesignerController extends Controller
{
    // Public designer page (no auth)
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // --------------------------
        // Find Product
        // --------------------------
        $product = null;

        if ($productId) {
            // check numeric id first
            if (is_numeric($productId)) {
                $product = Product::where('id', $productId)->first();
            }

            // try shopify_product_id if not found
            if (!$product && Schema::hasColumn('products', 'shopify_product_id')) {
                $product = Product::where('shopify_product_id', $productId)->first();
            }

            // optional fallback: try name
            if (!$product && Schema::hasColumn('products', 'name')) {
                $product = Product::where('name', $productId)->first();
            }

            // optional fallback: try sku
            if (!$product && Schema::hasColumn('products', 'sku')) {
                $product = Product::where('sku', $productId)->first();
            }
        }

        if (!$product) {
            abort(404, 'Product not found');
        }

        // --------------------------
        // Find Product View
        // --------------------------
        $view = null;
        if ($viewId) {
            $view = ProductView::find($viewId);
        }
        if (!$view) {
            $view = $product->views()->first(); // adjust relation if name differs
        }

        // --------------------------
        // Load Areas
        // --------------------------
        $areas = $view ? $view->areas()->get() : collect([]);

        // --------------------------
        // Return Designer Blade
        // --------------------------
        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
        ]);
    }
}
