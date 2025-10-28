<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class PublicDesignerController extends Controller
{
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // Product lookup (your existing logic)...
        $product = null;

        if ($productId) {
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 8) {
                $product = Product::with(['views','views.areas','variants'])
                            ->where('shopify_product_id', $productId)
                            ->first();

                if (!$product) {
                    $product = Product::with(['views','views.areas','variants'])->find((int)$productId);
                }
            } else {
                if (ctype_digit((string)$productId)) {
                    $product = Product::with(['views','views.areas','variants'])->find((int)$productId);
                }
            }

            if (!$product) {
                $query = Product::with(['views','views.areas','variants']);
                $cols = [];
                if (Schema::hasColumn('products','shopify_product_id')) $cols[] = 'shopify_product_id';
                if (Schema::hasColumn('products','name')) $cols[] = 'name';
                if (Schema::hasColumn('products','sku')) $cols[] = 'sku';

                if (count($cols)) {
                    $query->where(function($q) use ($productId, $cols) {
                        foreach ($cols as $c) {
                            $q->orWhere($c, $productId);
                        }
                    });
                    $product = $query->first();
                }
            }
        }

        if (!$product) {
            Log::warning("designer: product not found for product_id={$productId}");
            abort(404, 'Product not found');
        }

        // --- NEW: If no variants loaded or variants count 0, try fetch from Shopify and save locally
        if (!$product->relationLoaded('variants') || $product->variants->count() === 0) {
            try {
                $this->fetchShopifyVariantsAndStore($product);
                // reload relation after upsert
                $product->load('variants');
            } catch (\Throwable $e) {
                Log::warning("designer: fetchShopifyVariants failed product_id={$product->id} error=".$e->getMessage());
            }
        }

        // ----------------------------
        // Resolve view (same as before)
        // ----------------------------
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            if ($product->relationLoaded('views') && $product->views->count()) {
                $view = $product->views->first();
            } else {
                $view = $product->views()->with('areas')->first();
            }
        }

        $areas = $view ? ($view->relationLoaded('areas') ? $view->areas : $view->areas()->get()) : collect([]);

        // ----------------------------
        // Build layout slots (same as your code)
        // ----------------------------
        $layoutSlots = [];
        foreach ($areas as $a) {
            $left  = (float)($a->left_pct ?? $a->x_mm ?? 0);
            $top   = (float)($a->top_pct ?? $a->y_mm ?? 0);
            $w     = (float)($a->width_pct ?? $a->width_mm ?? 10);
            $h     = (float)($a->height_pct ?? $a->height_mm ?? 10);

            if ($left <= 1) $left *= 100;
            if ($top  <= 1) $top  *= 100;
            if ($w    <= 1) $w    *= 100;
            if ($h    <= 1) $h    *= 100;

            $mask = null;
            if (!empty($a->mask_svg_path)) {
                $mask = strpos($a->mask_svg_path, '/files/') === 0 ? $a->mask_svg_path : ('/files/' . ltrim($a->mask_svg_path, '/'));
            }

            $slotKey = null;
            if (!empty($a->slot_key)) $slotKey = strtolower(trim($a->slot_key));
            if (!$slotKey && !empty($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                if (strpos($n, 'num') !== false || strpos($n,'no') !== false || strpos($n,'number') !== false) $slotKey = 'number';
            }
            if (!$slotKey && isset($a->template_id)) {
                if ((int)$a->template_id === 1) $slotKey = 'name';
                if ((int)$a->template_id === 2) $slotKey = 'number';
            }
            if (!$slotKey) {
                $slotKey = 'slot_' . ($a->id ?? uniqid());
            }

            $layoutSlots[$slotKey] = [
                'id' => $a->id,
                'left_pct'  => round($left, 6),
                'top_pct'   => round($top, 6),
                'width_pct' => round($w, 6),
                'height_pct'=> round($h, 6),
                'rotation'  => (int)($a->rotation ?? 0),
                'name'      => $a->name ?? null,
                'slot_key'  => $a->slot_key ?? null,
                'template_id' => $a->template_id ?? null,
                'mask'      => $mask,
            ];
        }

        $originalLayoutSlots = $layoutSlots;

        // artwork detection and filtering --- keep your existing logic
        $hasArtworkSlot = false;
        foreach ($originalLayoutSlots as $slotKey => $slot) {
            $k = strtolower((string)$slotKey);
            if (in_array($k, ['logo','artwork','team_logo','graphic','image','art','badge','patch','patches'])) {
                $hasArtworkSlot = true; break;
            }
            if (!empty($slot['mask']) || !empty($slot['mask_url'])) {
                $hasArtworkSlot = true; break;
            }
            if (!in_array($k, ['name','number']) && !empty($slot['width_pct']) && !empty($slot['height_pct'])) {
                if ($slot['width_pct'] > 2 || $slot['height_pct'] > 2) {
                    $hasArtworkSlot = true; break;
                }
            }
        }
        $showUpload = (bool)$hasArtworkSlot;
        \Log::info("designer: product_id={$product->id} showUpload=" . (int)$showUpload . " hasArtworkSlot=" . (int)$hasArtworkSlot);

        // filteredLayoutSlots (same as your code)...
        $filteredLayoutSlots = [];
        if (!empty($layoutSlots) && is_array($layoutSlots)) {
            foreach (['name', 'number'] as $k) {
                if (isset($layoutSlots[$k]) && !empty($layoutSlots[$k]['id'])) {
                    $filteredLayoutSlots[$k] = $layoutSlots[$k];
                }
            }

            if (empty($filteredLayoutSlots['name'])) {
                $candidates = [];
                foreach ($layoutSlots as $key => $slot) {
                    $kl = strtolower((string)$key);
                    if ($kl === 'number' || $kl === 'name') continue;
                    if (!empty($slot['id'])) {
                        $w = isset($slot['width_pct']) ? (float)$slot['width_pct'] : 0;
                        $h = isset($slot['height_pct']) ? (float)$slot['height_pct'] : 0;
                        if ($w <= 1) $w *= 100;
                        if ($h <= 1) $h *= 100;
                        if ($w > 2 || $h > 2) {
                            $candidates[$key] = $slot;
                        }
                    }
                }
                if (count($candidates) === 1) {
                    $firstKey = array_keys($candidates)[0];
                    $filteredLayoutSlots['name'] = $candidates[$firstKey];
                    \Log::info("designer: mapped single generic slot '{$firstKey}' -> name for product_id={$product->id}");
                }
            }
        }

        // displayPrice compute (same as your code)
        $displayPrice = 0.00;
        try {
            if (isset($product->min_price) && is_numeric($product->min_price) && (float)$product->min_price > 0) {
                $displayPrice = (float)$product->min_price;
            } elseif (isset($product->price) && is_numeric($product->price) && (float)$product->price > 0) {
                $displayPrice = (float)$product->price;
            } else {
                if ($product->relationLoaded('variants')) {
                    $variantPrices = [];
                    foreach ($product->variants as $v) {
                        if (!empty($v->price) && (float)$v->price > 0) {
                            $variantPrices[] = (float)$v->price;
                        }
                    }
                    if (count($variantPrices)) $displayPrice = min($variantPrices);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('designer: price compute failed: ' . $e->getMessage());
        }

        // Build sizeOptions + variantMap for blade
        $sizeOptions = [];
        $variantMap = [];
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $v) {
                // prefer explicit option columns; fall back to title
                $label = trim((string)($v->option_value ?? $v->option_name ?? $v->title ?? ''));
                $variantId = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? '');
                if ($label === '' || $variantId === '') continue;
                $sizeOptions[] = ['label' => $label, 'variant_id' => $variantId];
                // map uppercase label to variant id for convenience (used maybe in JS)
                $variantMap[strtoupper($label)] = $variantId;
            }
        }

        return view('public.designer', [
            'product' => $product,
            'view'    => $view,
            'areas'   => $areas,
            'layoutSlots' => $filteredLayoutSlots,
            'originalLayoutSlots' => $originalLayoutSlots,
            'showUpload' => $showUpload,
            'hasArtworkSlot' => $hasArtworkSlot,
            'displayPrice' => (float)$displayPrice,
            'sizeOptions' => $sizeOptions,
            'variantMap' => $variantMap,
        ]);
    }

    /**
     * Fetch variants for a given product from Shopify Admin API and store into local DB
     * Supports multiple env var names for compatibility:
     * SHOPIFY_SHOP or SHOPIFY_STORE for shop domain (e.g. yourshop.myshopify.com)
     * SHOPIFY_ADMIN_TOKEN or SHOPIFY_ADMIN_API_TOKEN for admin API token
     */
    protected function fetchShopifyVariantsAndStore(Product $product)
    {
        $shopifyProductId = $product->shopify_product_id ?? null;
        if (!$shopifyProductId) {
            Log::info("designer: no shopify_product_id for product {$product->id}");
            return;
        }

        // Support common env names
        $shop = env('SHOPIFY_SHOP') ?: env('SHOPIFY_STORE') ?: env('SHOPIFY_SHOP_DOMAIN');
        $token = env('SHOPIFY_ADMIN_TOKEN') ?: env('SHOPIFY_ADMIN_API_TOKEN') ?: env('SHOPIFY_ADMIN_SECRET');

        if (!$shop || !$token) {
            throw new \RuntimeException("Shopify credentials not configured (expected SHOPIFY_SHOP/SHOPIFY_STORE and SHOPIFY_ADMIN_TOKEN/SHOPIFY_ADMIN_API_TOKEN).");
        }

        // sanitize product id if it's the global id (gid://...) or numeric, Shopify API expects numeric id in REST path
        $cleanId = $shopifyProductId;
        if (is_string($cleanId) && strpos($cleanId, 'gid://') === 0) {
            // extract numeric id from gid if present
            if (preg_match('/\/(\d+)$/', $cleanId, $m)) {
                $cleanId = $m[1];
            }
        }

        $client = new Client([
            'base_uri' => "https://{$shop}/admin/api/2024-10/",
            'timeout' => 20,
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Accept' => 'application/json',
            ],
        ]);

        $resp = $client->get("products/{$cleanId}.json");
        if ($resp->getStatusCode() !== 200) {
            throw new \RuntimeException("Shopify product fetch failed: {$resp->getStatusCode()}");
        }
        $json = json_decode((string)$resp->getBody(), true);
        $shopifyProduct = $json['product'] ?? null;
        if (!$shopifyProduct) {
            throw new \RuntimeException("Shopify returned no product for id={$cleanId}");
        }

        $variants = $shopifyProduct['variants'] ?? [];
        if (!is_array($variants) || count($variants) === 0) {
            Log::info("designer: shopify product has no variants shopify_id={$cleanId}");
            return;
        }

        // store each variant into product->variants() relation
        foreach ($variants as $v) {
            $variantShopifyId = (string)($v['id'] ?? '');
            if (!$variantShopifyId) continue;

            $title = trim((string)($v['title'] ?? ''));
            $optionLabel = '';
            // prefer option1 / option2 / option3 if present
            if (!empty($v['option1'])) $optionLabel = $v['option1'];
            elseif (!empty($v['option2'])) $optionLabel = $v['option2'];
            elseif (!empty($v['option3'])) $optionLabel = $v['option3'];

            $price = isset($v['price']) ? $v['price'] : null;
            $sku = isset($v['sku']) ? $v['sku'] : null;

            // Use updateOrCreate to upsert. Adjust column names to match your variants table.
            try {
                $product->variants()->updateOrCreate(
                    ['shopify_variant_id' => $variantShopifyId],
                    [
                        'title' => $title,
                        'option_value' => $optionLabel,
                        // if your table has 'variant_id' or 'id' needs mapping, adjust here.
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning("designer: variant upsert failed for shopify_variant_id={$variantShopifyId} error=" . $e->getMessage());
            }
        }

        Log::info("designer: fetched and stored " . count($variants) . " variants for product {$product->id} (shopify_id={$cleanId})");
    }
}
