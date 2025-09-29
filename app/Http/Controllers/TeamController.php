<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    /**
     * Show the team create view (optionally prefilled from query string).
     */
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = null;
        if ($productId) {
            $product = Product::find($productId);
        }

        $prefill = [
            'name'   => $request->query('prefill_name', ''),
            'number' => $request->query('prefill_number', ''),
            'font'   => $request->query('prefill_font', ''),
            'color'  => $request->query('prefill_color', ''),
            'size'   => $request->query('prefill_size', ''),
        ];

        return view('team.create', compact('product', 'prefill'));
    }

    /**
     * Persist team + players to DB (teams table) and redirect back with a message.
     */
    public function store(Request $request)
{
    \Log::info('TEAM_STORE_CALLED', $request->all());
    dd($request->all()); // <- temporary debugging, shows input data in browser
        $data = $request->validate([
            'product_id'         => 'required|integer',
            'players'            => 'required|array|min:1',
            'players.*.name'     => 'required|string|max:12',
            'players.*.number'   => ['required','regex:/^\d{1,3}$/'],
            'players.*.size'     => 'nullable|string|max:10',
            'players.*.font'     => 'nullable|string|max:50',
            'players.*.color'    => 'nullable|string|max:20',
        ]);

        // Normalize / sanitize players
        foreach ($data['players'] as &$p) {
            $p['name'] = isset($p['name']) ? mb_strtoupper(trim($p['name'])) : null;
            $p['number'] = isset($p['number']) ? preg_replace('/\D/', '', $p['number']) : null;
            $p['size'] = $p['size'] ?? null;
            $p['font'] = $p['font'] ?? null;
            $p['color'] = $p['color'] ?? null;
        }
        unset($p);

        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players'    => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('team.save_failed', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $data,
            ]);

            return back()->withInput()
                         ->with('error', 'Could not save team. Please try again or contact support.');
        }

        // Success: redirect back to the create page (so admin remains on the same UI)
        return redirect()
            ->route('team.create', ['product_id' => $data['product_id']])
            ->with('success', 'Team saved successfully.');
    }
}
