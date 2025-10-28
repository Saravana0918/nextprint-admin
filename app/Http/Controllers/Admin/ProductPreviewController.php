<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductPreviewController extends Controller
{
    // POST /admin/products/{product}/preview
    public function upload(Request $request, Product $product)
    {
        $request->validate([
            'preview_image' => ['required','image','max:5120'], // 5MB
        ]);

        $file = $request->file('preview_image');
        $name = time().'_'.Str::random(12).'.'.$file->getClientOriginalExtension();

        // store under storage/app/public/product-previews/...
        $path = $file->storeAs('product-previews', $name, 'public'); // returns "product-previews/xxx.jpg"

        // persist only the relative path in DB
        $product->thumbnail = $path;
        $product->save();

        // Always return a /files/... url (avoids /storage symlink issues)
        $url = url('/files/'.ltrim($path,'/'));

        return response()->json([
            'ok'   => true,
            'path' => $path,
            'url'  => $url,
        ]);
    }

    // DELETE /admin/products/{product}/preview
    public function destroy(Product $product)
    {
        if ($product->thumbnail) {
            $old = $product->thumbnail;
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $product->thumbnail = null;
            $product->save();
        }
        return response()->json(['ok' => true]);
    }
}
