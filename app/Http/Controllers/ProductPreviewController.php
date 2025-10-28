<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductPreviewController extends Controller
{
    /**
     * Upload preview image for a product.
     * POST /admin/products/{product}/preview
     */
    public function upload(Request $request, Product $product)
    {
        try {
            if (!$request->hasFile('preview_image')) {
                return response()->json(['message' => 'No file uploaded'], 422);
            }

            $request->validate([
                'preview_image' => 'image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB
            ]);

            $file = $request->file('preview_image');

            // store in storage/app/public/product-previews
            $path = $file->store('public/product-previews');

            // Storage::url -> returns /storage/product-previews/xxx.jpg
            $publicUrl = Storage::url($path);

            // update product preview_src (store public URL or relative)
            $product->preview_src = $publicUrl;
            $product->touch(); // update updated_at so cache-bust works
            $product->save();

            Log::info('Product preview uploaded', ['product_id' => $product->id, 'path' => $path]);

            return response()->json(['url' => $publicUrl], 200);
        } catch (\Throwable $e) {
            Log::error('uploadPreview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Upload failed'], 500);
        }
    }

    /**
     * Delete preview - clears preview_src and deletes file if possible.
     * DELETE /admin/products/{product}/preview
     */
    public function destroy(Product $product)
    {
        try {
            if (!empty($product->preview_src) && preg_match('~^/storage/(.+)$~', $product->preview_src, $m)) {
                $relative = $m[1];
                if (Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                    Log::info('Deleted preview file', ['file' => $relative]);
                }
            }

            $product->preview_src = null;
            $product->touch();
            $product->save();

            return response()->json(['ok' => true], 200);
        } catch (\Throwable $e) {
            Log::error('deletePreview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Delete failed'], 500);
        }
    }
}
