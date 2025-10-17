<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DesignerSaveController extends Controller
{
    public function save(Request $request)
    {
        $payload = $request->all();

        // mapping for incoming font keys to registered family names
        $fontMap = [
            'bebas' => 'Bebas Neue',
            'bebas neue' => 'Bebas Neue',
            'bebas-neue' => 'Bebas Neue',
            'bebas_neue' => 'Bebas Neue',
            'oswald' => 'Oswald',
            'anton' => 'Anton',
            // add other mappings as needed
        ];

        $fontRaw = trim((string)($payload['font'] ?? ''));
        $fontKey = strtolower(preg_replace('/[^a-z0-9]+/', ' ', $fontRaw));
        $fontFamily = $fontMap[$fontKey] ?? ($payload['font'] ?? null);
        if (!$fontFamily) $fontFamily = 'DejaVu Sans';

        // normalize color
        $colorRaw = trim((string)($payload['color'] ?? ''));
        if ($colorRaw === '') {
            $color = '#000000';
        } else {
            $color = (strpos($colorRaw, '#') === 0) ? $colorRaw : '#' . ltrim($colorRaw, '#');
        }

        // Build DB row (adjust fields to match your schema)
        $row = [
            'product_id' => $payload['product_id'] ?? null,
            'name_text' => $payload['name_text'] ?? null,
            'number_text' => $payload['number_text'] ?? null,
            'preview_src' => $payload['preview_src'] ?? null,
            'preview_base' => $payload['preview_base'] ?? null,
            'font' => $fontFamily,
            'color' => $color,
            'payload' => json_encode($payload),
            'raw_payload' => json_encode($payload), // keep raw too if you want
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            $id = DB::table('design_orders')->insertGetId($row);
            return response()->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            Log::error('Designer save failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
