<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product; 
use App\Models\Team;     
use App\Http\Controllers\ShopifyCartController;

class TeamController extends Controller
{
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = null;
        if ($productId) {
            $product = \App\Models\Product::find($productId);
        }

        $prefill = [
        'name'  => $request->query('prefill_name', ''),
        'number'=> $request->query('prefill_number', ''),
        'font'  => $request->query('prefill_font', ''),
        'color' => $request->query('prefill_color', ''),
        'size'  => $request->query('prefill_size', ''),
        ];

        return view('team.create', compact('product','prefill'));
    }

public function store(Request $request)
{
    $data = $request->validate([
        'product_id' => 'required|integer',
        'players' => 'required|array|min:1',
        'players.*.name' => 'required|string|max:12',
        'players.*.number' => ['required','regex:/^\d{1,3}$/'],
        'players.*.size' => 'nullable|string|max:10',
        'players.*.font' => 'nullable|string|max:50',
        'players.*.color' => 'nullable|string|max:20',
    ]);

    // optional: save to DB
    $team = \App\Models\Team::create([
        'product_id' => $data['product_id'],
        'players' => $data['players'],
        'created_by' => auth()->id(),
    ]);

    // 🟢 Call ShopifyCartController addToCart
    $shopify = new \App\Http\Controllers\ShopifyCartController();
    $req = new Request([
        'product_id' => $data['product_id'],
        'quantity' => 1, // team jersey as 1 line item
        'name_text' => 'TEAM ORDER',
        'number_text' => 'MULTI',
        'font' => $data['players'][0]['font'] ?? '',
        'color' => $data['players'][0]['color'] ?? '',
        'preview_data' => null,
    ]);
    $resp = $shopify->addToCart($req);

    $json = $resp->getData(true);
    if (!empty($json['checkoutUrl'])) {
        return redirect()->away($json['checkoutUrl']); // 🟢 direct to checkout
    }

    return back()->with('error', 'Could not add to Shopify cart.');
}


}
