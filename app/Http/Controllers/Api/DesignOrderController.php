<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DesignOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DesignOrderController extends Controller
{
    public function store(Request $request)
    {
        // basic validation (adjust rules as needed)
        $data = $request->validate([
            'shopify_order_id'  => 'nullable|string',
            'line_item_id'      => 'nullable|string',
            'product_id'        => 'nullable|string',
            'variant_id'        => 'nullable|string',
            'download_url'      => 'nullable|url',
            'name'              => 'nullable|string|max:120',
            'number'            => 'nullable|string|max:10',
            'font'              => 'nullable|string|max:50',
            'color'             => 'nullable|string|max:20',
            'preview_src'       => 'nullable|string', // public URL or /storage/.. path
            'payload'           => 'nullable|array',
            'status'            => 'nullable|string',
        ]);

        // create record
        try {
            $order = DesignOrder::create([
                'shopify_order_id' => $data['shopify_order_id'] ?? null,
                'shopify_line_item_id' => $data['line_item_id'] ?? null,
                'product_id'       => $data['product_id'] ?? null,
                'variant_id'       => $data['variant_id'] ?? null,
                'download_url'     => $data['download_url'] ?? null,
                'customer_name'    => $data['name'] ?? null,
                'customer_number'  => $data['number'] ?? null,
                'font'             => $data['font'] ?? null,
                'color'            => $data['color'] ?? null,
                'preview_src'      => $data['preview_src'] ?? null,
                'payload'          => $data['payload'] ?? null,
                'status'           => $data['status'] ?? 'new',
            ]);

            return response()->json(['success'=>true,'order_id'=>$order->id], 201);
        } catch (\Throwable $e) {
            Log::error('DesignOrder store failed: '.$e->getMessage(), ['data'=>$data]);
            return response()->json(['success'=>false,'message'=>'Save failed'], 500);
        }
    }
}
