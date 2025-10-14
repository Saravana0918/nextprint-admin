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
        // Join with team_players (or your team table) and product table
        // Adjust the table names according to your DB structure
        $rows = DB::table('team_players')
            ->join('products', 'team_players.product_id', '=', 'products.id')
            ->select(
                'team_players.id',
                'team_players.shopify_order_id',
                'team_players.name',
                'team_players.number',
                'team_players.preview_image',
                'team_players.created_at',
                'products.name as product_name',
                'products.id as product_id'
            )
            ->orderBy('team_players.created_at', 'desc')
            ->get();

        return view('admin.design_orders.index', compact('rows'));
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
