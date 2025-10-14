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
        DB::raw("COALESCE(p.name, p.title) as product_name"),
        DB::raw("d.name_text as name"),
        DB::raw("d.number_text as number"),
        DB::raw("d.preview_src as preview_image"),
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

    // If you have team players linked by product_id or shopify_order_id, adapt accordingly.
    // Example: fetch team_players using shopify_order_id if present:
    $players = DB::table('team_players')
        ->when(!empty($order->shopify_order_id), function($q) use ($order) {
            return $q->where('shopify_order_id', $order->shopify_order_id);
        }, function($q) use ($order) {
            return $q->where('product_id', $order->product_id);
        })
        ->orderBy('id')
        ->get();

    return view('admin.design_orders.show', compact('order', 'players'));
}
}
