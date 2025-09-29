<?php

use Illuminate\Support\Facades\Route;

// Admin controllers
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductViewController;
use App\Http\Controllers\Admin\PrintAreaController;
use App\Http\Controllers\Admin\DecorationAreaController;
use App\Http\Controllers\Admin\PrintMethodController;
use Illuminate\Support\Facades\File;

// Other controllers
use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ShopifyCartController;
use App\Http\Controllers\PublicDesignerController;
use App\Http\Controllers\TeamController;

/*
|--------------------------------------------------------------------------
| Webhooks (CSRF-exempt)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle']);
Route::post('/api/shopify/cart/add', [ShopifyCartController::class, 'addToCart'])->name('shopify.cart.add');
/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {

    // Dashboard (optional)
    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

    // Products list
    Route::get('/products', [ProductController::class, 'index'])->name('products');

    // Product CRUD
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}',        [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}',     [ProductController::class, 'destroy'])->name('products.destroy');

    // Jump to decoration editor
    Route::get('/products/{product}/views', [ProductController::class, 'goToDecoration'])->name('products.decoration');

    // Fabric.js editor (print areas)
    Route::get ('/products/{product}/views/{view}/areas',      [PrintAreaController::class, 'edit'])->name('areas.edit');
    Route::post('/products/{product}/views/{view}/areas',      [PrintAreaController::class, 'update'])->name('areas.update');
    Route::post('/products/{product}/views/{view}/areas/bulk',
        [\App\Http\Controllers\Admin\PrintAreaController::class, 'bulkSave']
    )->name('areas.bulk');


    // Product → methods (for admin JS)
    Route::get('/api/products/{product}/methods', [ProductController::class, 'methodsJson'])
        ->name('products.methods');

    // View image upload (BG image)
    Route::post('/products/{product}/views/{view}/image', [ProductViewController::class, 'uploadImage'])
        ->name('views.uploadImage');

    // Decoration templates manage
    Route::get ('/decoration-areas',  [DecorationAreaController::class, 'index'])->name('decoration.index');
    Route::post('/decoration-areas',  [DecorationAreaController::class, 'store'])->name('decoration.store');
    Route::get ('/api/decoration-areas', [DecorationAreaController::class, 'search'])->name('decoration.search');

    // Print methods manage
    Route::get('/print-methods',               [PrintMethodController::class, 'index'])->name('print-methods.index');
    Route::get('/print-methods/create',        [PrintMethodController::class, 'create'])->name('print-methods.create');
    Route::post('/print-methods',              [PrintMethodController::class, 'store'])->name('print-methods.store');
    Route::get('/print-methods/{method}/edit', [PrintMethodController::class, 'edit'])->name('print-methods.edit');
    Route::put('/print-methods/{method}',      [PrintMethodController::class, 'update'])->name('print-methods.update');
    Route::delete('/print-methods/{method}',   [PrintMethodController::class, 'destroy'])->name('print-methods.destroy');
    Route::post('/print-methods/{method}/toggle', [PrintMethodController::class, 'toggle'])->name('print-methods.toggle');
    Route::post('/print-methods/{method}/clone',  [PrintMethodController::class, 'clone'])->name('print-methods.clone');
    Route::get('/api/print-methods',          [PrintMethodController::class, 'search'])->name('print-methods.search');
    Route::delete('/decoration-areas/{template}', [DecorationAreaController::class, 'destroy'])
    ->name('decoration.destroy');

}); // <-- end admin group

// Simple fallback named login route — change target if your admin login URL differs
Route::get('/login', function () {
    // if you have an admin login URI like /admin/login, change to that:
    return redirect('/admin/login'); 
})->name('login');

// optional: also define logout route to avoid other route not defined issues
Route::post('/logout', function(){
    // you might want to implement actual logout logic
    auth()->logout();
    return redirect('/');
})->name('logout');
/*
|--------------------------------------------------------------------------
| Public API + Storefront routes
|--------------------------------------------------------------------------
*/

// Public API: methods by Shopify handle (used by PDP JS)
Route::get('/api/public/products/{handle}/methods', [PublicProductController::class, 'methodsByHandle']);
Route::get('/designer', [PublicDesignerController::class, 'show'])->name('public.designer.show');
Route::post('/designer/upload-preview', [\App\Http\Controllers\ShopifyCartController::class, 'uploadPreview'])
    ->name('designer.upload_preview');

Route::post('/designer/add-to-cart', [\App\Http\Controllers\ShopifyCartController::class, 'addToCart'])
    ->name('designer.addtocart');
Route::get('/team/create', [TeamController::class,'create'])->name('team.create');
Route::post('/team/store', [TeamController::class,'store'])->name('team.store')->middleware('auth');

// Simple PDP (preview/test page)
Route::get('/p/{handle}', [StoreController::class, 'show'])->name('store.product');
Route::get('/api/public/products/{handle}/layout',  [PublicProductController::class, 'layout']);
/*
|--------------------------------------------------------------------------
| Manual sync trigger (admin side convenience)
|--------------------------------------------------------------------------
*/
Route::post('/admin/sync-now', function () {
    $synced = app(\App\Services\ShopifyService::class)->syncNextprintToLocal();
    return back()->with('ok', "Synced {$synced} products.");
})->name('admin.sync-now');
Route::post('/api/shopify/cart/add', [ShopifyCartController::class, 'addToCart']);

Route::get('/files/{path}', function ($path) {
    $full = storage_path('app/public/'.$path);
    abort_unless(is_file($full), 404);

    $mime = \Illuminate\Support\Facades\File::mimeType($full) ?: 'application/octet-stream';
    return response()->file($full, ['Content-Type' => $mime]);
})->where('path', '.*');