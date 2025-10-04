<?php
// file: app/Http/Controllers/PublicDesignerController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublicDesignerController extends Controller
{
    /**
     * 🔹 Auto Fetch Variant Map from Shopify
     * Maps size → variant numeric ID
     */
    protected function buildVariantMapFromShopify($shopifyProductId)
    {
        $shop = env('SHOPIFY_STORE');            // e.g. yogireddy.myshopify.com
        $token = env('SHOPIFY_ADMIN_API_TOKEN'); // Shopify Admin API Token
        $apiVersion = '2025-07';                 // use latest stable version

        if (!$shop || !$token || !$shopifyProductId) {
            Log::warning("variantMap: missing shop/token/productId");
            return [];
        }

        $productGid = "gid://shopify/Product/{$shopifyProductId}";

        $query = <<<'GQL'
        query productVariants($id: ID!, $first: Int!) {
          product(id: $id) {
            id
            title
            variants(first: $first) {
              edges {
                node {
                  id
                  legacyResourceId
                  selectedOptions {
                    name
                    value
                  }
                }
              }
            }
          }
        }
        GQL;

        try {
            $resp = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ])->post("https://{$shop}/admin/api/{$apiVersion}/graphql.json", [
                'query' => $query,
                'variables' => ['id' => $productGid, 'first' => 250]
            ]);
        } catch (\Exception $e) {
            Log::error('variantMap: GraphQL request failed: ' . $e->getMessage());
            return [];
        }

        if (!$resp->successful()) {
            Log::error('variantMap: GraphQL not successful', [
                'status' => $resp->status(),
                'body' => $resp->body()
            ]);
            return [];
        }

        $data = $resp->json();
        if (isset($data['errors'])) {
            Log::warning('variantMap: GraphQL errors', $data['errors']);
            return [];
        }

        $product = $data['data']['product'] ?? null;
        if (!$product) {
            Log::warning('variantMap: product not found in GraphQL response');
            return [];
        }

        $map = [];
        foreach ($product['variants']['edges'] as $edge) {
            $node = $edge['node'];
            $sizeVal = null;
            foreach ($node['selectedOptions'] as $opt) {
                if (strtolower($opt['name']) === 'size') {
                    $sizeVal = $opt['value'];
                    break;
                }
            }
            if ($sizeVal === null && !empty($node['selectedOptions'])) {
                $sizeVal = $node['selectedOptions'][0]['value'];
            }
            if ($sizeVal !== null) {
                $map[$sizeVal] = (string)$node['legacyResourceId'];
            }
        }

        Log::info('✅ variantMap built', [
            'product' => $shopifyProductId,
            'count' => count($map)
        ]);
        return $map;
    }

    /**
     * 🔹 Main designer view
     */
    public function show(Request $request)
    {
        $productId = $request->query('product_id');
        $viewId    = $request->query('view_id');

        // ----------------------------
        // product lookup (robust)
        // ----------------------------
        $product = null;

        if ($productId) {
            if (ctype_digit((string)$productId) && strlen((string)$productId) >= 8) {
                $product = Product::with(['views', 'views.areas'])
                    ->where('shopify_product_id', $productId)
                    ->first();

                if (!$product) {
                    $product = Product::with(['views', 'views.areas'])->find((int)$productId);
                }
            } else {
                if (ctype_digit((string)$productId)) {
                    $product = Product::with(['views', 'views.areas'])->find((int)$productId);
                }
            }

            if (!$product) {
                $query = Product::with(['views', 'views.areas']);
                $cols = [];
                if (Schema::hasColumn('products', 'shopify_product_id')) $cols[] = 'shopify_product_id';
                if (Schema::hasColumn('products', 'name')) $cols[] = 'name';
                if (Schema::hasColumn('products', 'sku')) $cols[] = 'sku';

                if (count($cols)) {
                    $query->where(function ($q) use ($productId, $cols) {
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

        // ----------------------------
        // view selection
        // ----------------------------
        $view = null;
        if ($viewId) {
            $view = ProductView::with('areas')->find($viewId);
        }
        if (!$view) {
            $view = $product->views()->with('areas')->first();
        }

        $areas = $view ? ($view->areas ?? collect([])) : collect([]);

        // ----------------------------
        // build layoutSlots
        // ----------------------------
        $layoutSlots = [];

        foreach ($areas as $a) {
            $left = (float)($a->left_pct ?? 0);
            $top = (float)($a->top_pct ?? 0);
            $w = (float)($a->width_pct ?? 10);
            $h = (float)($a->height_pct ?? 10);

            if ($left <= 1) $left *= 100;
            if ($top <= 1) $top *= 100;
            if ($w <= 1) $w *= 100;
            if ($h <= 1) $h *= 100;

            $slotKey = strtolower($a->slot_key ?? '');
            if (!$slotKey && isset($a->name)) {
                $n = strtolower($a->name);
                if (strpos($n, 'name') !== false) $slotKey = 'name';
                elseif (strpos($n, 'num') !== false || strpos($n, 'number') !== false) $slotKey = 'number';
            }

            if (!$slotKey) {
                $slotKey = isset($layoutSlots['name']) ? 'number' : 'name';
            }

            $layoutSlots[$slotKey] = [
                'id' => $a->id,
                'left_pct' => round($left, 6),
                'top_pct' => round($top, 6),
                'width_pct' => round($w, 6),
                'height_pct' => round($h, 6),
                'rotation' => (int)($a->rotation ?? 0),
                'name' => $a->name ?? null,
                'slot_key' => $a->slot_key ?? null,
            ];
        }

        if (!isset($layoutSlots['name'])) {
            $layoutSlots['name'] = ['left_pct' => 10, 'top_pct' => 5, 'width_pct' => 60, 'height_pct' => 8, 'rotation' => 0];
        }
        if (!isset($layoutSlots['number'])) {
            $layoutSlots['number'] = ['left_pct' => 10, 'top_pct' => 75, 'width_pct' => 30, 'height_pct' => 10, 'rotation' => 0];
        }

        // ----------------------------
        // compute display price
        // ----------------------------
        $displayPrice = $product->min_price ?? $product->price ?? 0.00;

        // ----------------------------
        // build variantMap
        // ----------------------------
        $shopifyId = $product->shopify_product_id ?? $product->id ?? null;
        $variantMap = $shopifyId ? $this->buildVariantMapFromShopify($shopifyId) : [];

        // ----------------------------
        // return view
        // ----------------------------
        return view('public.designer', [
            'product' => $product,
            'view' => $view,
            'areas' => $areas,
            'layoutSlots' => $layoutSlots,
            'displayPrice' => (float)$displayPrice,
            'variantMap' => $variantMap,
        ]);
    }
}
