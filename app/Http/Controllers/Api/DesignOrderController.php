<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DesignOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DesignOrderController extends Controller
{
    // public endpoint used by Shopify webhook or designer to post order data
    public function store(Request $request)
    {
        // optional: verify a secret/token header if you want
        // $token = $request->header('X-My-Webhook-Token');
        // if($token !== config('services.design.webhook_token')) abort(403);

        $data = $request->all();

        // Typical payload can be either:
        // - Shopify order webhook (contains line_items with properties)
        // - or your designer app call containing download_url + parsed fields
        $shopifyOrderId = Arr::get($data, 'order_id') ?? Arr::get($data, 'id') ?? null;
        $downloadUrl = Arr::get($data, 'download_url') ?? Arr::get($data, 'download_url_full') ?? Arr::get($data, 'value') ?? null;

        // prefer explicit fields if provided
        $customerName = Arr::get($data, 'name') ?? Arr::get($data, 'customer_name') ?? null;
        $customerNumber = Arr::get($data, 'number') ?? Arr::get($data, 'customer_number') ?? null;
        $font = Arr::get($data, 'font') ?? null;
        $color = Arr::get($data, 'color') ?? null;
        $preview = Arr::get($data, 'preview_src') ?? Arr::get($data, 'preview') ?? null;

        // if we only have shopify webhook, try extract from line_items properties
        if (!$customerName && !empty($data['line_items']) && is_array($data['line_items'])) {
            foreach ($data['line_items'] as $li) {
                if (!empty($li['properties']) && is_array($li['properties'])) {
                    foreach ($li['properties'] as $prop) {
                        if (!empty($prop['name']) && !empty($prop['value'])) {
                            $n = strtolower(trim($prop['name']));
                            if (in_array($n, ['name','customer name'])) $customerName = $prop['value'];
                            if (in_array($n, ['number','no','num'])) $customerNumber = $prop['value'];
                            if (in_array($n, ['font'])) $font = $prop['value'];
                            if (in_array($n, ['color'])) $color = $prop['value'];
                        }
                    }
                }
                // use first line item ids
                $lineItemId = $li['id'] ?? $li['line_item_id'] ?? null;
                $productId = $li['product_id'] ?? null;
                $variantId = $li['variant_id'] ?? null;
            }
        }

        // If we have a download_url, try fetch it to get authoritative payload
        $payloadArray = null;
        if ($downloadUrl) {
            try {
                $resp = Http::timeout(10)->get($downloadUrl);
                if ($resp->ok()) {
                    // assume JSON; if ZIP then you might return an index JSON link instead
                    $payloadArray = $resp->json();
                    // prefer name/number inside response
                    $customerName = $customerName ?? Arr::get($payloadArray,'name') ?? Arr::get($payloadArray,'customer.name') ?? null;
                    $customerNumber = $customerNumber ?? Arr::get($payloadArray,'number') ?? Arr::get($payloadArray,'customer.number') ?? null;
                    $font = $font ?? Arr::get($payloadArray,'font');
                    $color = $color ?? Arr::get($payloadArray,'color');
                    $preview = $preview ?? Arr::get($payloadArray,'preview') ?? Arr::get($payloadArray,'image_url');
                }
            } catch (\Throwable $e) {
                Log::warning('DesignOrderController: failed to fetch download_url: ' . $e->getMessage());
            }
        }

        $order = DesignOrder::create([
            'shopify_order_id'    => $shopifyOrderId,
            'shopify_line_item_id'=> $lineItemId ?? null,
            'product_id'          => $productId ?? null,
            'variant_id'          => $variantId ?? null,
            'customer_name'       => $customerName,
            'customer_number'     => $customerNumber,
            'font'                => $font,
            'color'               => $color,
            'preview_src'         => $preview,
            'download_url'        => $downloadUrl,
            'payload'             => $payloadArray ?? $data,
            'status'              => 'new',
        ]);

        return response()->json(['success'=>true,'id'=>$order->id], 201);
    }
}
