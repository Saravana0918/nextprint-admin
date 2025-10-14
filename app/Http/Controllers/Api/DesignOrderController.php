<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Log;

class DesignOrderController extends Controller
{
    public function store(Request $request)
    {
        // basic validation (lenient)
        $v = $request->validate([
            'product_id' => 'nullable|integer',
            'shopify_product_id' => 'nullable|string',
            'variant_id' => 'nullable|string',
            'name' => 'nullable|string|max:100',
            'number' => 'nullable|string|max:20',
            'font' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'size' => 'nullable|string|max:50',
            'quantity' => 'nullable|integer',
            'preview_src' => 'nullable|string',
            'uploaded_logo_url' => 'nullable|string',
            'players' => 'nullable',
            'shopify_order_id' => 'nullable|string'
        ]);

        $now = Carbon::now()->toDateTimeString();
        $previewStoredPath = null;

        // handle data:image base64
        if (!empty($v['preview_src'])) {
            $src = $v['preview_src'];
            if (preg_match('/^data:image\/(\w+);base64,/', $src, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $imageData = base64_decode(substr($src, strpos($src, ',') + 1));
                if ($imageData !== false) {
                    $filename = 'design_previews/' . date('Ymd') . '/' . Str::random(12) . '.' . $ext;
                    try {
                        Storage::disk('public')->put($filename, $imageData);
                        $previewStoredPath = '/storage/' . $filename;
                    } catch (Exception $e) {
                        Log::error('Preview save failed: ' . $e->getMessage());
                        $previewStoredPath = null;
                    }
                }
            } else {
                // already a URL
                $previewStoredPath = $src;
            }
        }

        // prepare insert (matching your columns)
        $insert = [
            'shopify_order_id'    => $v['shopify_order_id'] ?? null,
            'shopify_line_item_id'=> null,
            'product_id'          => $v['product_id'] ?? null,
            'variant_id'          => $v['variant_id'] ?? null,
            'customer_name'       => isset($v['name']) ? strtoupper(trim($v['name'])) : null,
            'customer_number'     => isset($v['number']) ? preg_replace('/\D/','', $v['number']) : null,
            'font'                => $v['font'] ?? null,
            'color'               => $v['color'] ?? null,
            'preview_src'         => $previewStoredPath,
            'download_url'        => null,
            'payload'             => json_encode($request->all()),
            'status'              => 'new',
            'created_at'          => $now,
            'updated_at'          => $now
        ];

        try {
            $designOrderId = DB::table('design_orders')->insertGetId($insert);
        } catch (Exception $e) {
            Log::error('Design order insert failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'DB insert failed'], 500);
        }

        // optional: insert team players (if provided)
        $players = $request->input('players');
        if (!empty($players)) {
            try {
                $playersArr = is_string($players) ? json_decode($players, true) : $players;
                if (is_array($playersArr)) {
                    foreach ($playersArr as $p) {
                        DB::table('team_players')->insert([
                            'shopify_order_id' => $v['shopify_order_id'] ?? null,
                            'product_id' => $v['product_id'] ?? null,
                            'name' => $p['name'] ?? null,
                            'number' => isset($p['number']) ? preg_replace('/\D/','',$p['number']) : null,
                            'size' => $p['size'] ?? null,
                            'font' => $p['font'] ?? $v['font'] ?? null,
                            'color' => $p['color'] ?? $v['color'] ?? null,
                            'preview_image' => $p['preview_image'] ?? $previewStoredPath ?? null,
                            'created_at' => $now
                        ]);
                    }
                }
            } catch (Exception $e) {
                Log::error('Players insert error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'order_id' => $designOrderId,
            'preview_url' => $previewStoredPath
        ]);
    }
}
