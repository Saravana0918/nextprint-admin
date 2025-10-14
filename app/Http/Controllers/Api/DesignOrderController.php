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
        // Basic validation (adjust rules as needed)
        $validated = $request->validate([
            'product_id' => 'nullable|integer',
            'shopify_product_id' => 'nullable|string',
            'variant_id' => 'nullable|string',
            'name' => 'nullable|string|max:50',
            'number' => 'nullable|string|max:10',
            'font' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'size' => 'nullable|string|max:50',
            'quantity' => 'nullable|integer|min:1',
            'preview_src' => 'nullable|string', // dataURL or public URL
            'uploaded_logo_url' => 'nullable|string',
            'players' => 'nullable', // JSON array expected
            'shopify_order_id' => 'nullable|string',
        ]);

        // 1) handle preview image: if data:image/*;base64, decode and store
        $previewUrl = null;
        if (!empty($validated['preview_src'])) {
            $src = $validated['preview_src'];
            if (preg_match('/^data:image\/(\w+);base64,/', $src, $type)) {
                $imageData = substr($src, strpos($src, ',') + 1);
                $imageData = base64_decode($imageData);
                if ($imageData === false) {
                    // ignore, will store null
                } else {
                    $ext = strtolower($type[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    $filename = 'design_previews/'. date('Ymd') . '/' . Str::random(12) . '.' . $ext;
                    Storage::disk('public')->put($filename, $imageData);
                    $previewUrl = Storage::url($filename); // /storage/design_previews/...
                }
            } else {
                // preview_src may already be a public URL
                $previewUrl = $src;
            }
        }

        // 2) create design_orders row
        $now = Carbon::now()->toDateTimeString();
        $data = [
            'shopify_order_id' => $validated['shopify_order_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'shopify_product_id' => $validated['shopify_product_id'] ?? null,
            'variant_id' => $validated['variant_id'] ?? null,
            'name_text' => isset($validated['name']) ? strtoupper(trim($validated['name'])) : null,
            'number_text' => isset($validated['number']) ? preg_replace('/\D+/', '', $validated['number']) : null,
            'font' => $validated['font'] ?? null,
            'color' => $validated['color'] ?? null,
            'size' => $validated['size'] ?? null,
            'quantity' => $validated['quantity'] ?? 1,
            'preview_image' => $previewUrl,
            'uploaded_logo_url' => $validated['uploaded_logo_url'] ?? null,
            'players' => is_string($request->players) ? json_decode($request->players, true) : ($request->players ?? null),
            'properties' => null,
            'created_at' => $now,
            'updated_at' => $now
        ];

        $id = DB::table('design_orders')->insertGetId($data);

        // 3) if players array present, also insert into team_players (one row per player)
        try {
            $players = $data['players'] ?? null;
            if ($players && is_array($players)) {
                foreach ($players as $p) {
                    // Expect each player item: name, number, size, preview_image (optional)
                    DB::table('team_players')->insert([
                        'shopify_order_id' => $validated['shopify_order_id'] ?? null,
                        'product_id' => $validated['product_id'] ?? null,
                        'name' => $p['name'] ?? null,
                        'number' => isset($p['number']) ? preg_replace('/\D+/', '', $p['number']) : null,
                        'size' => $p['size'] ?? null,
                        'font' => $p['font'] ?? $validated['font'] ?? null,
                        'color' => $p['color'] ?? $validated['color'] ?? null,
                        'preview_image' => $p['preview_image'] ?? $previewUrl ?? null,
                        'created_at' => $now,
                    ]);
                }
            }
        } catch (\Exception $e) {
            // don't break main save — just log error
            \Log::error('Unable to save team players: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'order_id' => $id,
            'preview_url' => $previewUrl,
        ]);
    }
}
