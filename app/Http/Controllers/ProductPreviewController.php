<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductPreviewController extends Controller
{
    // Upload preview image
    public function upload(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'preview_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:6144',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()->all()], 422);
        }

        $file = $request->file('preview_image');
        if (!$file) {
            return response()->json(['success' => false, 'message' => 'No file'], 400);
        }

        // optional: delete existing preview file if stored in public disk
        if ($product->preview_src) {
            try {
                $oldPath = $this->stripStoragePrefix($product->preview_src);
                if ($oldPath) Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {
                // ignore delete errors
            }
        }

        $filename = 'preview_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('previews', $filename, 'public'); // storage/app/public/previews/...

        $url = Storage::url($path); // /storage/previews/...

        $product->preview_src = $url;
        $product->save();

        return response()->json(['success' => true, 'url' => $url, 'message' => 'Uploaded']);
    }

    // Remove preview
    public function remove(Request $request, Product $product)
    {
        if ($product->preview_src) {
            try {
                $oldPath = $this->stripStoragePrefix($product->preview_src);
                if ($oldPath) Storage::disk('public')->delete($oldPath);
            } catch (\Throwable $e) {}
        }
        $product->preview_src = null;
        $product->save();

        return response()->json(['success' => true, 'message' => 'Removed']);
    }

    protected function stripStoragePrefix($url)
    {
        if (!$url) return null;
        // handles "/storage/..." or full URL "https://domain/storage/..."
        $path = $url;
        // if full URL, parse path
        if (parse_url($url, PHP_URL_PATH)) {
            $path = parse_url($url, PHP_URL_PATH);
        }
        // remove leading /storage/
        $path = preg_replace('#^/storage/#', '', $path);
        return $path ?: null;
    }
}
