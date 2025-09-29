<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Team;
use App\Models\Product;

class TeamController extends Controller
{
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = $productId ? Product::find($productId) : null;

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
            'players.*.color' => 'nullable|string|max:30',
            'team_name' => 'nullable|string|max:100',
            'preview_data' => 'nullable|string', // optional base64 preview image
        ]);

        // sanitize players: uppercase names + numbers trimmed
        $players = array_map(function($p){
            return [
                'name' => isset($p['name']) ? strtoupper(substr($p['name'],0,12)) : '',
                'number' => isset($p['number']) ? preg_replace('/\D/','', substr($p['number'],0,3)) : '',
                'size' => $p['size'] ?? null,
                'font' => $p['font'] ?? null,
                'color' => $p['color'] ?? null,
            ];
        }, $data['players']);

        $team = Team::create([
            'product_id' => $data['product_id'],
            'created_by' => auth()->id() ?? null,
            'name' => $data['team_name'] ?? null,
            'players' => $players,
        ]);

        // optional: save preview image (data:image/png;base64,....)
        if (!empty($data['preview_data']) && preg_match('/^data:image\/(\w+);base64,/', $data['preview_data'])) {
            $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $data['preview_data']);
            $img = base64_decode($base64);
            $ext = 'png';
            $filename = "teams/previews/team-{$team->id}-".time().'.'.$ext;
            \Storage::disk('public')->put($filename, $img);
            $team->preview_path = $filename;
            $team->save();
        }

        // Add to session cart as single cart item with team meta
        $cartItem = [
            'product_id' => $team->product_id,
            'qty' => 1,
            'price' => 0, // optionally set price lookup from product
            'team_id' => $team->id,
            'players' => $team->players,
        ];

        // push to session cart array
        $cart = session()->get('cart', []);
        $cart[] = $cartItem;
        session(['cart' => $cart]);

        // redirect to public cart / checkout page
        return redirect()->route('cart.index')->with('success', 'Team saved and added to cart.');
    }
}
