<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\DesignOrder;

class DesignOrderController extends Controller
{
    public function index()
    {
        // fetch all design orders and group them by shopify_order_id
        // fallback: orders without shopify_order_id will be grouped by their id
        $orders = \App\Models\DesignOrder::orderBy('created_at', 'desc')->get();

        $groups = $orders->groupBy(function ($item) {
            return $item->shopify_order_id ?: 'single_'.$item->id;
        });

        // pass grouped collection to view
        return view('admin.design_orders.index', compact('groups'));
    }

    public function show($id)
    {
        // Attempt to treat $id as shopify_order_id first:
        $orders = \App\Models\DesignOrder::where('shopify_order_id', $id)
                ->orderBy('id')
                ->get();

        if ($orders->isEmpty()) {
            // fallback: if no group found, try numeric id (single row)
            if (is_numeric($id)) {
                $order = \App\Models\DesignOrder::findOrFail($id);
                $orders = collect([$order]);
            } else {
                abort(404);
            }
        }

        $first = $orders->first();

        return view('admin.design_orders.show', compact('orders','first'));
    }

}
