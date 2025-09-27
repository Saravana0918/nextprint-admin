<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product; // if needed
use App\Models\Team;    // optional - if you persist

class TeamController extends Controller
{
    public function create(Request $request)
    {
        // read optional prefill params
        $productId = $request->query('product_id');
        $product = null;
        if ($productId) {
            // adjust if Product::find(...) is the correct model
            $product = \App\Models\Product::find($productId);
        }

        $prefill = [
            'name' => $request->query('prefill_name', ''),
            'number' => $request->query('prefill_number', ''),
            'font' => $request->query('prefill_font', ''),
            'color' => $request->query('prefill_color', ''),
            'size' => $request->query('prefill_size', ''),
        ];

        return view('team.create', compact('product', 'prefill'));
    }

    public function store(Request $request)
        {
            $data = $request->validate([
            'product_id' => 'required|integer',
            'players' => 'required|array|min:1',
            'players.*.name' => 'required|string|max:50',
            'players.*.number' => 'required|numeric',
            'players.*.size' => 'nullable|string|max:10',
            'preview_data' => 'nullable|string',
            ]);

            // optional: create Team
            $team = \App\Models\Team::create(['product_id'=>$data['product_id'],'name'=>'Team for product '.$data['product_id']]);

            foreach($data['players'] as $p){
            \App\Models\Player::create([
                'team_id' => $team->id,
                'name' => $p['name'],
                'number' => $p['number'],
                'size' => $p['size'] ?? null,
            ]);
            }

            // if preview_data present (data:image/png;base64,...)
            if(!empty($data['preview_data'])){
            if(preg_match('/^data:image\/png;base64,/', $data['preview_data'])) {
                $base64 = substr($data['preview_data'], strpos($data['preview_data'], ',') + 1);
                $img = base64_decode($base64);
                $filename = 'teams/previews/team-'.$team->id.'-'.time().'.png';
                \Storage::disk('public')->put($filename, $img);
                $team->preview_path = $filename; $team->save();
            }
            }

            return redirect()->route('team.create', ['product_id'=>$data['product_id']])->with('success','Team saved.');
        }

}
