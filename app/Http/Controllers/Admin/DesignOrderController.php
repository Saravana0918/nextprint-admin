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
            'd.shopify_order_id',
            'd.product_id',
            DB::raw("p.name as product_name"),     // <- use p.name (exists in your table)
            DB::raw("d.name_text as name"),
            DB::raw("d.number_text as number"),
            'd.preview_image',
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
        ->select([
            'd.*',
            DB::raw("p.name as product_name")
        ])
        ->where('d.id', $id)
        ->first();

    if (!$order) {
        abort(404, 'Design order not found');
    }

    // load players linked by product_id or shopify_order_id (adjust as per your linking)
    $players = DB::table('team_players')
        ->where('product_id', $order->product_id)
        ->where(function($q) use ($order) {
            if (!empty($order->shopify_order_id)) {
                $q->orWhere('shopify_order_id', $order->shopify_order_id);
            }
            $q->orWhere('created_at', '>=', now()->subDays(30)); // fallback - optional
        })
        ->orderBy('id')
        ->get();

    return view('admin.design_orders.show', compact('order', 'players'));
}
}
