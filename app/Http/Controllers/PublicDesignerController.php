<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;      // change if your Product model namespace different
use App\Models\ProductView;  // change if you use ProductView model

class PublicDesignerController extends Controller
{
    // Public designer page (no auth)
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // Try to find product by Shopify id, internal id or handle
        $product = null;
        if ($productId) {
            $product = Product::where('shopify_product_id', $productId)
                        ->orWhere('id', $productId)
                        ->orWhere('handle', $productId)
                        ->first();
        }

        if (!$product) {
            // optional: return a simple error view
            abort(404, 'Product not found');
        }

        // Find view (decoration view). Try provided viewId or fallback to first view
        $view = null;
        if ($viewId) {
            $view = ProductView::find($viewId);
        }
        if (!$view) {
            $view = $product->views()->first(); // adjust relation name if different
        }

        // load areas (if any)
        $areas = $view ? $view->areas()->get() : collect([]);

        // Render a public designer blade (we'll create it next)
        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
        ]);
    }
}
