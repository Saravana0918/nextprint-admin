<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\DesignOrder;

class DesignOrderController extends Controller
{
    public function index()
    {
        $rows = DesignOrder::orderBy('created_at','desc')->paginate(25);
        return view('admin.design_orders.index', compact('rows'));
    }

    public function show($id)
    {
        $row = DesignOrder::findOrFail($id);
        return view('admin.design_orders.show', compact('row'));
    }
}
