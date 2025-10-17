<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class DesignTempUploadController extends Controller
{
    public function upload(Request $request)
    {
        try {
            if (!$request->hasFile('file')) {
                return response()->json(['ok' => false, 'message' => 'No file uploaded'], 400);
            }
            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'preview_base_' . time() . '_' . Str::random(6) . '.' . $ext;
            $path = $file->storeAs('designer_temp', $filename, 'public'); // storage/app/public/designer_temp/...

            $url = '/storage/' . $path;
            Log::info("Designer temp uploaded: {$path}");
            return response()->json(['ok' => true, 'url' => $url, 'path' => $path]);
        } catch (\Throwable $e) {
            Log::error('upload-temp failed: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
