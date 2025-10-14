<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductViewController;
use App\Http\Controllers\Admin\PrintAreaController;
use App\Http\Controllers\Admin\DecorationAreaController;
use App\Http\Controllers\Admin\PrintMethodController;
use Illuminate\Support\Facades\File;

use App\Http\Controllers\ShopifyWebhookController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ShopifyCartController;
use App\Http\Controllers\PublicDesignerController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ProductPreviewController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Api\DesignOrderController;

/*
|--------------------------------------------------------------------------
| Webhooks (CSRF-exempt)
| If using /webhooks/shopify here, add this path to
| App\Http\Middleware\VerifyCsrfToken::$except = ['webhooks/shopify'];
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/shopify', [ShopifyWebhookController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Public API endpoint for design orders (public - protect with HMAC/token if needed)
|--------------------------------------------------------------------------
| This endpoint receives payloads from your designer or Shopify webhook and
| stores them in design_orders table. It's intentionally outside admin group.
*/
Route::post('/design-order', [App\Http\Controllers\Api\DesignOrderController::class, 'store'])->name('design-order.store');

/*
|--------------------------------------------------------------------------
| Admin routes (prefixed with /admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {

    Route::view('/dashboard', 'admin.dashboard')->name('dashboard');

    Route::get('/products', [ProductController::class, 'index'])->name('products');

    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}',        [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}',     [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/products/{product}/views', [ProductController::class, 'goToDecoration'])->name('products.decoration');

    Route::get('/products/{product}/views/{view}/areas',      [PrintAreaController::class, 'edit'])->name('areas.edit');
    Route::post('/products/{product}/views/{view}/areas',     [PrintAreaController::class, 'update'])->name('areas.update');
    Route::post('/products/{product}/views/{view}/areas/bulk',[PrintAreaController::class, 'bulkSave'])->name('areas.bulk');

    Route::get('/api/products/{product}/methods', [ProductController::class, 'methodsJson'])->name('products.methods');

    Route::post('/products/{product}/views/{view}/image', [ProductViewController::class, 'uploadImage'])->name('views.uploadImage');

    Route::get('/decoration-areas',  [DecorationAreaController::class, 'index'])->name('decoration.index');
    Route::post('/decoration-areas',  [DecorationAreaController::class, 'store'])->name('decoration.store');
    Route::get('/api/decoration-areas', [DecorationAreaController::class, 'search'])->name('decoration.search');

    Route::get('/print-methods',               [PrintMethodController::class, 'index'])->name('print-methods.index');
    Route::get('/print-methods/create',        [PrintMethodController::class, 'create'])->name('print-methods.create');
    Route::post('/print-methods',              [PrintMethodController::class, 'store'])->name('print-methods.store');
    Route::get('/print-methods/{method}/edit', [PrintMethodController::class, 'edit'])->name('print-methods.edit');
    Route::put('/print-methods/{method}',      [PrintMethodController::class, 'update'])->name('print-methods.update');
    Route::delete('/print-methods/{method}',   [PrintMethodController::class, 'destroy'])->name('print-methods.destroy');
    Route::post('/print-methods/{method}/toggle', [PrintMethodController::class, 'toggle'])->name('print-methods.toggle');
    Route::post('/print-methods/{method}/clone',  [PrintMethodController::class, 'clone'])->name('print-methods.clone');
    Route::get('/api/print-methods',          [PrintMethodController::class, 'search'])->name('print-methods.search');

    Route::delete('/decoration-areas/{template}', [DecorationAreaController::class, 'destroy'])->name('decoration.destroy');

    // Product preview upload/delete (admin)
    Route::post('products/{product}/preview', [ProductPreviewController::class, 'upload'])
         ->name('products.preview.upload');

    Route::delete('products/{product}/preview', [ProductPreviewController::class, 'destroy'])
         ->name('products.preview.delete');

    /*
    | Admin: design orders listing & details
    | Note: route URIs are /admin/design-orders and /admin/design-orders/{id}
    */
    Route::get('/design-orders', [\App\Http\Controllers\Admin\DesignOrderController::class,'index'])
         ->name('design-orders.index');

    Route::get('/design-orders/{id}', [\App\Http\Controllers\Admin\DesignOrderController::class,'show'])
         ->name('design-orders.show');

}); // end admin

// Admin auth
Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');

/*
|--------------------------------------------------------------------------
| Public API + Storefront routes
|--------------------------------------------------------------------------
*/
Route::get('/api/public/products/{handle}/methods', [PublicProductController::class, 'methodsByHandle']);

Route::get('/designer', [PublicDesignerController::class, 'show'])->name('public.designer.show');
Route::post('/designer/upload-preview', [ShopifyCartController::class, 'uploadPreview'])->name('designer.upload_preview');
Route::post('/designer/add-to-cart', [ShopifyCartController::class, 'addToCart'])->name('designer.addtocart');
Route::post('/designer/upload-temp', [\App\Http\Controllers\DesignerUploadController::class, 'uploadTemp'])->name('designer.upload_temp');

/*
| Team routes
*/
Route::get('/team/create', [TeamController::class,'create'])->name('team.create');
Route::post('/team/store', [TeamController::class,'store'])->name('team.store');
// team.show (redirect after store)
Route::get('/team/{team}', [TeamController::class,'show'])->name('team.show');

Route::post('/save-preview', [App\Http\Controllers\PreviewController::class, 'store'])->name('preview.store');

Route::get('/p/{handle}', [StoreController::class, 'show'])->name('store.product');
Route::get('/api/public/products/{handle}/layout',  [PublicProductController::class, 'layout']);

/*
| Utility / admin actions
*/
Route::post('/admin/sync-now', function () {
    $synced = app(\App\Services\ShopifyService::class)->syncNextprintToLocal();
    return back()->with('ok', "Synced {$synced} products.");
})->name('admin.sync-now');

/*
| Serve stored files via /files/{path} (reads storage/app/public)
*/
Route::get('/files/{path}', function ($path) {
    $full = storage_path('app/public/'.$path);
    abort_unless(is_file($full), 404);
    $mime = \Illuminate\Support\Facades\File::mimeType($full) ?: 'application/octet-stream';
    return response()->file($full, ['Content-Type' => $mime]);
})->where('path', '.*');
