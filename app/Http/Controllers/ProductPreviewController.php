<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;

class ProductPreviewController extends Controller
{
    public function upload(Request $request, Product $product)
{
    // handle delete: _method=DELETE
    if ($request->method() === 'DELETE' || $request->input('_method') === 'DELETE') {
        // optionally unlink old file from storage if you stored it in our folder
        if ($product->preview_src) {
            $path = str_replace('/storage/', 'public/', $product->preview_src);
            try { Storage::delete($path); } catch(\Throwable $e) {}
        }
        $product->preview_src = null;
        $product->save();
        return response()->json(['success' => true, 'message' => 'Removed']);
    }

    // else handle upload — previous code
    $request->validate([
        'preview_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:6144'
    ]);
    // ... store file and save as earlier
}

}
