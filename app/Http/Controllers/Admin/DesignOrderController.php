<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignOrderController extends Controller
{
    /**
     * Show all design orders (team player entries)
     */
public function index()
{
    $rows = DB::table('design_orders as d')
        ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
        ->select([
            'd.id',
            'd.shopify_product_id',
            'd.product_id',
            DB::raw("p.name as product_name"),   // ✅ only use p.name
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
     * Show details for a specific order (all players under same shopify_order_id)
     */
public function show($id)
{
    $order = DB::table('design_orders as d')
        ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
        ->select(['d.*', DB::raw("p.name as product_name")])
        ->where('d.id', $id)
        ->first();

    if (!$order) {
        abort(404, 'Design order not found');
    }

    $players = DB::table('team_players')
        ->when(!empty($order->shopify_product_id), function ($q) use ($order) {
            return $q->where('product_id', $order->product_id);
        })
        ->orderBy('id')
        ->get();

    return view('admin.design_orders.show', compact('order', 'players'));
}
public function destroy(Request $request, $id)
    {
        try {
            // Optional: check permission manually if not using can:admin in route
            // $this->authorize('delete', DesignOrder::class);

            // If you have a model:
            // $row = DesignOrder::find($id);
            // if (!$row) abort(404);

            // if you want to delete associated preview file from disk (optional)
            // if ($row->preview_src && Str::startsWith($row->preview_src, '/storage')) {
            //     $path = str_replace('/storage/', '', $row->preview_src);
            //     Storage::disk('public')->delete($path);
            // }

            // delete using query builder to avoid model issues
            $deleted = DB::table('design_orders')->where('id', $id)->delete();

            if (!$deleted) {
                return redirect()->back()->with('error', 'Design order not found or could not be deleted.');
            }

            return redirect()->route('admin.design-orders.index')->with('success', 'Design order deleted.');
        } catch (\Throwable $e) {
            Log::error('Design order delete failed: '.$e->getMessage(), ['id'=>$id, 'trace'=>$e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Could not delete design order. Check logs.');
        }
    }
}
