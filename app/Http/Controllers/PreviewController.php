<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PreviewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->json()->all();
        $img = $data['image'] ?? null;
        if (!$img || !preg_match('/^data:image\/(png|jpeg);base64,/', $img, $m)) {
            return response()->json(['error'=>'invalid_image'], 422);
        }
        try {
            $base64 = preg_replace('/^data:image\/(png|jpeg);base64,/', '', $img);
            $decoded = base64_decode($base64);
            $ext = ($m[1] === 'jpeg') ? 'jpg' : 'png';
            $filename = 'previews/' . date('Ymd') . '/' . Str::random(12) . '.' . $ext;
            Storage::disk('public')->put($filename, $decoded);
            $url = Storage::disk('public')->url($filename);
            return response()->json(['url' => $url], 200);
        } catch (\Throwable $e) {
            \Log::error('preview_store_failed: ' . $e->getMessage());
            return response()->json(['error'=>'server_error'], 500);
        }
    }
}
