<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PublicDesignerController extends Controller
{
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // Attempt to find product with minimal queries and safe fallbacks
        $product = null;

        if ($productId) {
            // try by numeric id first (cast to int)
            if (ctype_digit((string)$productId)) {
                $product = Product::with(['views','views.areas'])->find((int)$productId);
            }

            // If not found, try matching against common columns in one query
            if (!$product) {
                $query = Product::with(['views','views.areas']);
                $query->where(function($q) use ($productId) {
                    $q->where('shopify_product_id', $productId)
                      ->orWhere('name', $productId)
                      ->orWhere('sku', $productId);
                });

                // Only add columns that actually exist to avoid SQL errors
                // (Schema::hasColumn is safe but avoid excessive calls in heavy traffic)
                $validCols = Schema::hasColumn('products','shopify_product_id') ||
                             Schema::hasColumn('products','name') ||
                             Schema::hasColumn('products','sku');

                if ($validCols) {
                    $product = $query->first();
                }
            }
        }

        if (!$product) {
            // If you prefer graceful fallback, render view with placeholder instead of abort
            // return view('designer_placeholder');
            abort(404, 'Product not found');
        }

        // Resolve product view: direct viewId or fallback to first related view
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            // use loaded relation if available (because we eager-loaded views)
            if ($product->relationLoaded('views') && $product->views->count()) {
                $view = $product->views->first();
            } else {
                $view = $product->views()->with('areas')->first();
            }
        }

        // Load areas safely (collection)
        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // Return blade: ensure this matches your blade file path.
        // If file is resources/views/designer.blade.php -> use 'designer'
        // If file is resources/views/public/designer.blade.php -> use 'public.designer'
        return view('designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
        ]);
    }
}
