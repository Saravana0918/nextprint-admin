<?php

namespace App\Http\Controllers;

use App\Models\ShopifyProduct;

class StoreController extends Controller
{
    public function show(string $handle)
    {
        $p = ShopifyProduct::where('handle', $handle)->firstOrFail();

        // Pass minimal fields to the view
        return view('store.product', [
            'product' => $p,
        ]);
    }
}
