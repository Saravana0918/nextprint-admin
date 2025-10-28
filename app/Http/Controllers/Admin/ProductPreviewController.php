<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductPreviewController extends Controller
{
    // POST /admin/products/{product}/preview
    public function upload(Request $request, Product $product)
    {
        if (!$request->hasFile('preview_image')) {
            return response()->json(['message' => 'No file uploaded'], 422);
        }

        $request->validate([
            'preview_image' => 'image|mimes:jpeg,jpg,png,webp|max:5120'
        ]);

        $file = $request->file('preview_image');
        $path = $file->store('public/product-previews'); // storage/app/public/product-previews/...
        $publicUrl = Storage::url($path); // -> /storage/product-previews/xxx.jpg

        $product->preview_src = $publicUrl;
        $product->touch();
        $product->save();

        Log::info('Product preview uploaded', ['product_id'=>$product->id, 'path'=>$path]);

        return response()->json(['url' => $publicUrl], 200);
    }

    // DELETE /admin/products/{product}/preview
    public function destroy(Product $product)
    {
        // try delete file if it exists under /storage
        if (!empty($product->preview_src)) {
            // expected format: /storage/your/path.jpg or https://...
            if (preg_match('~/storage/(.+)$~', $product->preview_src, $m)) {
                $rel = $m[1];
                if (Storage::disk('public')->exists($rel)) {
                    Storage::disk('public')->delete($rel);
                }
            }
        }

        $product->preview_src = null;
        $product->touch();
        $product->save();

        Log::info('Product preview deleted', ['product_id'=>$product->id]);

        return response()->json(['ok' => true], 200);
    }
}
