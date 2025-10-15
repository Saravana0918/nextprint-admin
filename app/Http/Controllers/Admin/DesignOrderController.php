<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DesignOrderController extends Controller
{
    /**
     * Show all design orders (paginated)
     */
    public function index()
    {
        $rows = DB::table('design_orders as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->select([
                'd.id',
                'd.shopify_product_id',
                'd.product_id',
                DB::raw("p.name as product_name"),
                'd.name_text',
                'd.number_text',
                'd.preview_src',
                'd.created_at'
            ])
            ->orderBy('d.created_at', 'desc')
            ->paginate(25);

        return view('admin.design_orders.index', ['rows' => $rows]);
    }

    /**
     * Show details for a specific design order
     */
    public function show($id)
{
    // fetch design order + product name
    $order = DB::table('design_orders as d')
        ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
        ->select(['d.*', DB::raw("p.name as product_name")])
        ->where('d.id', $id)
        ->first();

    if (! $order) {
        abort(404, 'Design order not found');
    }

    // ensure $players ends up as a Collection of objects
    $players = collect();

    try {
        // prefer players embedded in design_orders.meta if available (JSON)
        if (!empty($order->meta)) {
            $meta = $order->meta;
            // meta may be JSON string or already array/object
            if (is_string($meta)) {
                $metaDecoded = json_decode($meta, true);
            } elseif (is_object($meta)) {
                $metaDecoded = json_decode(json_encode($meta), true);
            } else {
                $metaDecoded = (array)$meta;
            }

            if (!empty($metaDecoded['players']) && is_array($metaDecoded['players'])) {
                $players = collect($metaDecoded['players'])->map(function($p, $i) use ($order) {
                    // ensure each player is an object with consistent fields for the view
                    $p = is_array($p) ? $p : (array)$p;
                    $obj = (object) array_merge([
                        'id' => $p['id'] ?? ($i + 1),
                        'name' => $p['name'] ?? null,
                        'number' => $p['number'] ?? null,
                        'size' => $p['size'] ?? null,
                        'font' => $p['font'] ?? null,
                        'preview_src' => $p['preview_src'] ?? null,
                        'created_at' => $p['created_at'] ?? $order->created_at ?? null,
                    ], $p);
                    return $obj;
                });
            } elseif (!empty($metaDecoded['team_id'])) {
                // meta had a team_id -> try read real rows from team_players
                $players = DB::table('team_players')
                    ->where('team_id', (int)$metaDecoded['team_id'])
                    ->orderBy('id')
                    ->get();
            }
        }

        // fallback: if still empty, try reading team_players by product_id
        if ($players->isEmpty()) {
            $players = DB::table('team_players')
                ->when(!empty($order->product_id), function ($q) use ($order) {
                    return $q->where('product_id', $order->product_id);
                })
                ->orderBy('id')
                ->get();
        }
    } catch (\Throwable $e) {
        // on any parsing/db error: log and keep $players as empty collection (view will show "No players")
        \Log::warning('DesignOrderController::show players fetch failed: '.$e->getMessage());
        $players = collect();
    }

    return view('admin.design_orders.show', compact('order', 'players'));
}

    /**
     * Delete a design order row (and optionally its preview file)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $row = DB::table('design_orders')->where('id', $id)->first();
            if (!$row) {
                return redirect()->back()->with('error', 'Design order not found.');
            }

            // Attempt to delete preview file if it's a storage URL
            if (!empty($row->preview_src) && Str::startsWith($row->preview_src, Storage::disk('public')->url(''))) {
                // derive relative path
                $publicUrl = Storage::disk('public')->url('');
                $relative = str_replace($publicUrl, '', $row->preview_src);
                if ($relative) {
                    try { Storage::disk('public')->delete($relative); } catch (\Throwable $e) { Log::warning('Could not delete preview file: '.$e->getMessage()); }
                }
            }

            DB::table('design_orders')->where('id', $id)->delete();

            return redirect()->route('admin.design-orders.index')->with('success', 'Design order deleted.');
        } catch (\Throwable $e) {
            Log::error('Design order delete failed: '.$e->getMessage(), ['id'=>$id, 'trace'=>$e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Could not delete design order. Check logs.');
        }
    }
}
