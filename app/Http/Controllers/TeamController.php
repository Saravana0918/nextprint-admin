<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product; // if needed
use App\Models\Team;    // optional - if you persist

class TeamController extends Controller
{
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = Product::find($productId);
        return view('team.create', compact('product'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'players' => 'required|array|min:1',
            'players.*.number' => 'required|numeric',
            'players.*.name' => 'required|string',
            'players.*.size' => 'nullable|string',
        ]);

        // save your team logic - e.g. Team model or just return
        // Team::create([...]) or loop players

        return redirect()->route('team.create', ['product_id'=>$request->product_id])
                        ->with('success','Team saved');
    }
}
