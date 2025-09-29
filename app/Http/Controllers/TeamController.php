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
            $product = Product::find($productId);
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

    // save team in DB
    try {
        Team::create([
            'product_id' => $data['product_id'],
            'players'    => $data['players'],
            'created_by' => auth()->id() ?? null,
        ]);
    } catch (\Throwable $e) {
        \Log::warning('team.save_failed', ['err'=>$e->getMessage()]);
    }

    // Call Shopify
    $first = $data['players'][0] ?? [];
    $req = new Request([
        'product_id'   => $data['product_id'],
        'quantity'     => 1,
        'name_text'    => implode(', ', array_map(fn($p) => $p['name'].'#'.$p['number'], $data['players'])),
        'number_text'  => 'TEAM',
        'font'         => $first['font'] ?? '',
        'color'        => $first['color'] ?? '',
        'preview_data' => null,
    ]);

    $shopify = app(ShopifyCartController::class);
    $resp = $shopify->addToCart($req);

    // 🔴 Fix: force redirect to checkout url
    if ($resp instanceof \Illuminate\Http\JsonResponse) {
        $json = $resp->getData(true);
        if (!empty($json['checkoutUrl'])) {
            return redirect()->away($json['checkoutUrl']); // ✅ direct to checkout
        }
    }

    return back()->with('error', 'Could not add to Shopify cart.');
}

}
