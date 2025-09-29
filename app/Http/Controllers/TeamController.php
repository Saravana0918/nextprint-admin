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
        // server-side validation (strict)
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'players' => 'required|array|min:1',
            'players.*.name' => 'required|string|max:12',
            'players.*.number' => ['required','regex:/^\d{1,3}$/'],
            'players.*.size' => 'nullable|string|max:10',
            'players.*.font' => 'nullable|string|max:50',
            'players.*.color' => 'nullable|string|max:20',
        ]);

        // normalize players
        foreach ($data['players'] as &$p) {
            $p['name'] = isset($p['name']) ? mb_strtoupper($p['name']) : null;
            $p['number'] = isset($p['number']) ? preg_replace('/\D/','',$p['number']) : null;
            $p['size'] = $p['size'] ?? null;
            $p['font'] = $p['font'] ?? null;
            $p['color'] = $p['color'] ?? null;
        }
        unset($p);

        DB::beginTransaction();
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players'    => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);

            DB::commit();

            // If you later want to support "save_and_cart", check $request->input('submit_action')
            return redirect()->route('team.create', ['product_id' => $data['product_id']])
                             ->with('success', 'Team saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            // Log full exception to storage/logs/laravel.log
            Log::error('Team store failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);

            // For debugging on dev server only: you can return error message (or just redirect)
            if (config('app.debug')) {
                // show message to browser (helpful while debugging)
                abort(500, 'Team save error: '.$e->getMessage());
            }

            return redirect()->back()->withInput()->with('error', 'Unable to save team. Try again or check logs.');
        }
    }
}
