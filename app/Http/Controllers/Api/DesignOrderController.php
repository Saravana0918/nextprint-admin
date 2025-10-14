<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DesignOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DesignOrderController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        // Simple validation-ish
        if (!empty($data['players']) && is_array($data['players'])) {
            $createdIds = [];

            foreach ($data['players'] as $p) {
                // sanitize fields
                $name = Arr::get($p, 'name') ? substr(trim(Arr::get($p, 'name')), 0, 64) : null;
                $number = Arr::get($p, 'number') ? substr(preg_replace('/\D+/', '', Arr::get($p, 'number')), 0, 6) : null;
                $font = Arr::get($p, 'font') ?? Arr::get($data, 'prefill_font') ?? null;
                $color = Arr::get($p, 'color') ?? Arr::get($data, 'prefill_color') ?? null;
                $variantId = Arr::get($p, 'variant_id') ?? null;

                $order = new DesignOrder();
                $order->shopify_order_id = Arr::get($data, 'shopify_order_id') ?? null;
                $order->shopify_line_item_id = Arr::get($data, 'shopify_line_item_id') ?? null;
                $order->product_id = Arr::get($data, 'product_id') ?? null;
                $order->variant_id = $variantId;
                $order->customer_name = $name;
                $order->customer_number = $number;
                $order->font = $font;
                $order->color = $color;
                $order->preview_src = Arr::get($data, 'preview_src') ?? null;
                $order->download_url = Arr::get($data, 'download_url') ?? null;
                $order->payload = $data; // store full payload for debugging / retrieval
                $order->status = 'new';
                $order->save();

                $createdIds[] = $order->id;
            }

            return response()->json(['success'=>true, 'order_ids'=>$createdIds], 201);
        }

        // fallback for single (older) format. Keep compatibility
        // read single fields
        $shopifyOrderId = Arr::get($data, 'order_id') ?? Arr::get($data, 'shopify_order_id') ?? null;
        $name = Arr::get($data, 'name') ?? Arr::get($data, 'customer_name') ?? null;
        $number = Arr::get($data, 'number') ?? Arr::get($data, 'customer_number') ?? null;

        $order = DesignOrder::create([
            'shopify_order_id' => $shopifyOrderId,
            'shopify_line_item_id' => Arr::get($data, 'line_item_id') ?? null,
            'product_id' => Arr::get($data, 'product_id') ?? null,
            'variant_id' => Arr::get($data, 'variant_id') ?? null,
            'customer_name' => $name,
            'customer_number' => $number,
            'font' => Arr::get($data, 'font') ?? null,
            'color' => Arr::get($data, 'color') ?? null,
            'preview_src' => Arr::get($data, 'preview_src') ?? null,
            'download_url' => Arr::get($data, 'download_url') ?? null,
            'payload' => $data,
            'status' => 'new'
        ]);

        return response()->json(['success'=>true, 'order_id'=>$order->id], 201);
    }
}
