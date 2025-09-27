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
        // pass any defaults you want to second page
        return view('team.create', compact('product'));
    }

    public function store(Request $request)
    {
        // $request->players will be an array of players
        // validate:
        $data = $request->validate([
            'product_id' => 'required|integer',
            'players' => 'required|array|min:1',
            'players.*.number' => 'required|numeric',
            'players.*.name' => 'required|string',
            'players.*.size' => 'nullable|string'
        ]);

        // Example: save to DB or session — depends on your flow
        // For demo, I will save to session and redirect back to product or checkout
        session(['team_players_' . $data['product_id'] => $data['players']]);

        return redirect()->route('team.create', ['product_id' => $data['product_id']])
                         ->with('success', 'Team saved (in session).');
    }
}
