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

        $product = null;

        if ($productId) {
            // 1) if numeric PK
            if (ctype_digit((string)$productId)) {
                $product = Product::with(['views','views.areas'])->find((int)$productId);
            }

            // 2) fallback: shopify_product_id (exact or partial), or name, or sku
            if (!$product) {
                $cols = [
                    'shopify_product_id' => Schema::hasColumn('products','shopify_product_id'),
                    'name'               => Schema::hasColumn('products','name'),
                    'sku'                => Schema::hasColumn('products','sku'),
                ];

                // build query only if at least one relevant column exists
                if ($cols['shopify_product_id'] || $cols['name'] || $cols['sku']) {
                    $query = Product::with(['views','views.areas']);
                    $query->where(function($q) use ($productId, $cols) {
                        if ($cols['shopify_product_id']) {
                            $q->where('shopify_product_id', $productId)
                              ->orWhere('shopify_product_id', 'like', '%' . $productId . '%');
                        }
                        if ($cols['name']) {
                            $q->orWhere('name', $productId);
                        }
                        if ($cols['sku']) {
                            $q->orWhere('sku', $productId);
                        }
                    });

                    $product = $query->first();
                }
            }
        }

        if (!$product) {
            // Not found: abort 404 (production). If you want to debug UI, temporarily replace with a placeholder render.
            abort(404, 'Product not found');
        }

        // Resolve product view (explicit view_id or first related view)
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            if ($product->relationLoaded('views') && $product->views->count()) {
                $view = $product->views->first();
            } else {
                $view = $product->views()->with('areas')->first();
            }
        }

        // Areas collection safe
        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // Render the blade that lives at resources/views/public/designer.blade.php
        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
        ]);
    }
}
