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
        // join with products table (if you have one) to get product name
        // adjust table/column names based on your schema (products table, name/title columns)
        $rows = DB::table('design_orders as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->select([
            'd.id',
            'd.shopify_order_id',
            'd.product_id',
            // Use only the columns that really exist in your products table
            DB::raw("p.name as product_name"),
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
        // Find first record
        $first = DB::table('team_players')->where('id', $id)->first();

        if (!$first) {
            abort(404, 'Design order not found.');
        }

        // Fetch all players in same Shopify order
        $players = DB::table('team_players')
            ->where('shopify_order_id', $first->shopify_order_id)
            ->orderBy('id', 'asc')
            ->get();

        return view('admin.design_orders.show', compact('first', 'players'));
    }
}
