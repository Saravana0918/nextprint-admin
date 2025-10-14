<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DesignOrderController extends Controller
{
    public function store(Request $request)
    {
        // validate inputs (lenient so frontend won't fail)
        $data = $request->validate([
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

        // default values
        $now = Carbon::now()->toDateTimeString();

        // 1) handle preview_src if it's a data URL
        $previewStoredPath = null;
        if (!empty($data['preview_src'])) {
            $src = $data['preview_src'];
            if (preg_match('/^data:image\/(\w+);base64,/', $src, $m)) {
                $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                $imageData = base64_decode(substr($src, strpos($src, ',') + 1));
                if ($imageData !== false) {
                    $filename = 'design_previews/' . date('Ymd') . '/' . Str::random(12) . '.' . $ext;
                    Storage::disk('public')->put($filename, $imageData);
                    $previewStoredPath = '/storage/' . $filename; // public URL prefix used in views
                }
            } else {
                // if already a URL, store it directly
                $previewStoredPath = $src;
            }
        }

        // 2) prepare DB insert (match your columns)
        $insert = [
            'shopify_order_id'   => $data['shopify_order_id'] ?? null,
            'shopify_line_item_id'=> null,
            'product_id'         => $data['product_id'] ?? null,
            'variant_id'         => $data['variant_id'] ?? null,
            'customer_name'      => isset($data['name']) ? strtoupper(trim($data['name'])) : null,
            'customer_number'    => isset($data['number']) ? preg_replace('/\D/','', $data['number']) : null,
            'font'               => $data['font'] ?? null,
            'color'              => $data['color'] ?? null,
            'preview_src'        => $previewStoredPath,
            'download_url'       => null,
            'payload'            => json_encode($request->all()), // raw payload for debugging
            'status'             => 'new',
            'created_at'         => $now,
            'updated_at'         => $now
        ];

        $designOrderId = DB::table('design_orders')->insertGetId($insert);

        // 3) if players sent as JSON array, insert into team_players table (if desired)
        $players = $request->input('players');
        if (!empty($players)) {
            try {
                $playersArr = is_string($players) ? json_decode($players, true) : $players;
                if (is_array($playersArr)) {
                    foreach ($playersArr as $p) {
                        DB::table('team_players')->insert([
                            'shopify_order_id' => $data['shopify_order_id'] ?? null,
                            'product_id' => $data['product_id'] ?? null,
                            'name' => $p['name'] ?? null,
                            'number' => isset($p['number']) ? preg_replace('/\D/','',$p['number']) : null,
                            'size' => $p['size'] ?? null,
                            'font' => $p['font'] ?? $data['font'] ?? null,
                            'color' => $p['color'] ?? $data['color'] ?? null,
                            'preview_image' => $p['preview_image'] ?? $previewStoredPath ?? null,
                            'created_at' => $now
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Team players insert error: '.$e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'order_id' => $designOrderId,
            'preview_url' => $previewStoredPath
        ]);
    }
}
