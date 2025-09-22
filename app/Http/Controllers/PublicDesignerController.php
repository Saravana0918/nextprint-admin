<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\ProductView;

class PublicDesignerController extends Controller
{
    /**
     * Show public designer page for a product/view.
     * If product not found in local DB, try fetching minimal product info from Shopify (fallback).
     */
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        $product = null;

        // 1) Try find product in local DB
        if ($productId) {
            // if numeric primary id (local)
            if (ctype_digit((string)$productId)) {
                $product = Product::with(['views','views.areas'])->find((int)$productId);
            }

            // fallback: try shopify_product_id (exact/like), name, sku if columns exist
            if (!$product) {
                $hasShopify = Schema::hasColumn('products','shopify_product_id');
                $hasName    = Schema::hasColumn('products','name');
                $hasSku     = Schema::hasColumn('products','sku');

                if ($hasShopify || $hasName || $hasSku) {
                    $query = Product::with(['views','views.areas']);
                    $query->where(function($q) use ($productId, $hasShopify, $hasName, $hasSku) {
                        if ($hasShopify) {
                            $q->where('shopify_product_id', $productId)
                              ->orWhere('shopify_product_id', 'like', '%' . $productId . '%');
                        }
                        if ($hasName) {
                            $q->orWhere('name', $productId);
                        }
                        if ($hasSku) {
                            $q->orWhere('sku', $productId);
                        }
                    });
                    $product = $query->first();
                }
            }
        }

        // 2) If not found in DB -> try Shopify API fallback (non-blocking, best-effort)
        if (!$product && $productId) {
            try {
                $shop = env('SHOPIFY_DOMAIN');      // e.g. your-store.myshopify.com
                $token = env('SHOPIFY_API_TOKEN');  // private/custom app access token (read-only recommended)

                if ($shop && $token) {
                    // extract numeric id if gid form provided (e.g. gid://shopify/Product/12345)
                    $pid = $productId;
                    if (preg_match('/(\d+)$/', $productId, $m)) {
                        $pid = $m[1];
                    }

                    // GET product JSON from Shopify REST Admin API
                    $url = "https://{$shop}/admin/api/2024-10/products/{$pid}.json";
                    $res = Http::withHeaders([
                        'X-Shopify-Access-Token' => $token,
                        'Accept' => 'application/json',
                    ])->timeout(10)->get($url);

                    if ($res->ok()) {
                        $data = $res->json('product');
                        if ($data) {
                            // Build lightweight object with fields used by blade
                            $imageUrl = null;
                            if (!empty($data['image']['src'])) $imageUrl = $data['image']['src'];
                            elseif (!empty($data['images'][0]['src'])) $imageUrl = $data['images'][0]['src'];

                            $product = (object)[
                                'id' => $data['id'] ?? $pid,
                                'name' => $data['title'] ?? ($data['handle'] ?? 'Product'),
                                'title' => $data['title'] ?? ($data['handle'] ?? 'Product'),
                                'vendor' => $data['vendor'] ?? null,
                                'min_price' => null,
                                'shopify_product_id' => $data['id'] ?? $pid,
                                'image_url' => $imageUrl ?? asset('images/placeholder.png'),
                            ];

                            // Note: we do NOT persist this to DB here — it's transient for view rendering.
                        }
                    } else {
                        Log::warning("Shopify fallback failed (non-OK) for pid={$pid}, status=" . $res->status());
                    }
                } else {
                    Log::info("Shopify fallback skipped: SHOPIFY_DOMAIN or SHOPIFY_API_TOKEN not configured.");
                }
            } catch (\Throwable $e) {
                Log::warning("Shopify fallback error: " . $e->getMessage());
            }
        }

        // 3) If still no product -> abort 404
        if (!$product) {
            abort(404, 'Product not found');
        }

        // 4) Resolve view and areas (prefer DB relations if product is Eloquent model)
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            if ($product instanceof Product) {
                if ($product->relationLoaded('views') && $product->views->count()) {
                    $view = $product->views->first();
                } else {
                    $view = $product->views()->with('areas')->first();
                }
            } else {
                $view = null; // Shopify fallback -> no DB view
            }
        }

        // 5) Load areas collection safely (if view exists)
        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // 6) Return blade (resources/views/public/designer.blade.php)
        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
        ]);
    }
}
