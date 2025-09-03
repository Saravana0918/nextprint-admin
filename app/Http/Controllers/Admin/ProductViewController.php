<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductViewController extends Controller
{
    public function uploadImage(Request $req, \App\Models\Product $product, \App\Models\ProductView $view)
{
    $req->validate(['view_image' => 'required|image']);
    $path = $req->file('view_image')->store('product_views', 'public'); // e.g. storage/app/public/product_views/...

    // Get actual pixel size from the saved file
    $abs = Storage::disk('public')->path($path);
    [$w, $h] = getimagesize($abs) ?: [0, 0];

    $view->image_url    = Storage::disk('public')->url($path); // /storage/product_views/...
    $view->image_width  = $w;
    $view->image_height = $h;
    $view->save();

    return back()->with('ok', 'View image uploaded and size saved.');
}
}
