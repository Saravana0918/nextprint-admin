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
    // server-side validation (repeat client rules)
    $data = $request->validate([
        'product_id' => 'required|integer',
        'players' => 'required|array|min:1',
        'players.*.name' => 'required|string|max:12',
        'players.*.number' => 'required|digits_between:1,3',
        'players.*.size' => 'nullable|string|max:10',
        'players.*.font' => 'nullable|string|max:50',
        'players.*.color' => 'nullable|string|max:20',
        'preview_data' => 'nullable|string',
    ]);

    // persist as a Team JSON (simple normalized approach)
    $team = \App\Models\Team::create([
        'product_id' => $data['product_id'],
        'players' => $data['players'],
        'created_by' => auth()->id() ?? null,
    ]);

    // optional: if client sent a base64 preview image in preview_data, save it
    if (!empty($data['preview_data']) && preg_match('/^data:image\/(png|jpeg);base64,/', $data['preview_data'])) {
        $base64 = substr($data['preview_data'], strpos($data['preview_data'], ',') + 1);
        $img = base64_decode($base64);
        $filename = 'teams/previews/team-'.$team->id.'-'.time().'.png';
        \Storage::disk('public')->put($filename, $img);
        $team->preview_path = $filename;
        $team->save();
    }

    // add to session cart or return success depending on flow
    // Example: redirect back to create page with success
    return redirect()->route('team.create', ['product_id'=>$data['product_id']])->with('success', 'Team saved.');
}

}
