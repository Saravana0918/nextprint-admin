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

    foreach ($data['players'] as &$p) {
        $p['name'] = isset($p['name']) ? mb_strtoupper($p['name']) : null;
        $p['number'] = isset($p['number']) ? preg_replace('/\D/','',$p['number']) : null;
        $p['size'] = $p['size'] ?? null;
        $p['font'] = $p['font'] ?? null;
        $p['color'] = $p['color'] ?? null;
    }
    unset($p);

    $team = \App\Models\Team::create([
        'product_id' => $data['product_id'],
        'players' => $data['players'],
        'created_by' => auth()->id(),
    ]);

    return redirect()->route('team.create', ['product_id' => $data['product_id']])
                     ->with('success', 'Team saved successfully.');
}

}
