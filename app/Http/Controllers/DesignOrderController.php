<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\DesignOrder;

class DesignOrderController extends Controller
{
    public function store(Request $request)
    {
        // Basic validation (tweak as needed)
        $data = $request->only([
            'product_id','shopify_product_id','variant_id','size','quantity',
            'name_text','number_text','font','color','uploaded_logo_url','preview_data'
        ]);

        // Save preview (if a base64 data url)
        $previewPath = null;
        try {
            if (!empty($data['preview_data']) && preg_match('#^data:image/(png|jpeg);base64,#', $data['preview_data'], $m)) {
                $base64 = preg_replace('#^data:image/(png|jpeg);base64,#', '', $data['preview_data']);
                $bin = base64_decode($base64);
                if ($bin !== false) {
                    $filename = 'design_previews/' . date('Y/m') . '/' . Str::random(12) . '.png';
                    Storage::disk('public')->put($filename, $bin);
                    $previewPath = 'storage/' . $filename; // public URL
                }
            }
        } catch (\Exception $e) {
            // ignore preview save error, still continue
            \Log::warning('Preview save failed: ' . $e->getMessage());
        }

        // Create record
        $order = new DesignOrder();
        $order->product_id = $data['product_id'] ?? null;
        $order->shopify_product_id = $data['shopify_product_id'] ?? null;
        $order->variant_id = $data['variant_id'] ?? null;
        $order->size = $data['size'] ?? null;
        $order->quantity = intval($data['quantity'] ?? 1);
        $order->name_text = $data['name_text'] ?? null;
        $order->number_text = $data['number_text'] ?? null;
        $order->font = $data['font'] ?? null;
        $order->color = $data['color'] ?? null;
        $order->uploaded_logo_url = $data['uploaded_logo_url'] ?? null;
        $order->preview_path = $previewPath;
        $order->raw_payload = json_encode($request->all());
        $order->save();

        return response()->json(['id' => $order->id], 201);
    }
}
