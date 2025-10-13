<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;

class ProductPreviewController extends Controller
{
    // upload or replace preview for a product
    public function upload(Request $request, Product $product)
    {
        $request->validate([
            'preview_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:6144',
        ]);

        // remove old file if stored in /storage/app/public/...
        if ($product->preview_src) {
            // if preview_src starts with '/storage/' we can remove storage path
            $url = $product->preview_src;
            // transform url -> storage path
            if (Str::startsWith($url, '/storage/')) {
                $path = str_replace('/storage/', '', $url);
                try { Storage::disk('public')->delete($path); } catch (\Throwable $e) {}
            }
        }

        $file = $request->file('preview_image');
        $filename = 'preview_' . $product->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('previews', $filename, 'public'); // stored in storage/app/public/previews

        if (!$path) {
            return response()->json(['success'=>false,'message'=>'Upload failed'], 500);
        }

        // set public URL
        $product->preview_src = '/storage/' . $path;
        $product->save();

        return response()->json(['success'=>true,'url'=>$product->preview_src]);
    }

    // delete existing preview
    public function destroy(Request $request, Product $product)
    {
        if ($product->preview_src && Str::startsWith($product->preview_src, '/storage/')) {
            $path = str_replace('/storage/', '', $product->preview_src);
            try { Storage::disk('public')->delete($path); } catch (\Throwable $e) {}
        }
        $product->preview_src = null;
        $product->save();
        return response()->json(['success'=>true]);
    }
}
