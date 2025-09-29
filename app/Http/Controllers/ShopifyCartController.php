<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Models\ProductVariant;

class ShopifyCartController extends Controller
{
    public function addToCart(Request $request)
    {
        // Validate incoming (players OR product_id)
        $validated = $request->validate([
            'product_id'           => 'required|integer',
            'shopify_product_id'   => 'nullable|string',
            'players'              => 'nullable|array',
            'players.*.name'       => 'nullable|string|max:60',
            'players.*.number'     => 'nullable|string|max:20',
            'players.*.font'       => 'nullable|string|max:100',
            'players.*.color'      => 'nullable|string|max:20',
            'players.*.size'       => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable',
            'quantity'             => 'nullable|integer|min:1', // fallback quantity
            'preview_url'          => 'nullable|url',
            'preview_data'         => 'nullable|string',
        ]);

        $productId = $validated['product_id'];
        $shopifyProductId = $validated['shopify_product_id'] ?? null;
        $players = $validated['players'] ?? null;
        $fallbackQuantity = $validated['quantity'] ?? 1;

        Log::info('designer: addToCart_called', [
            'product_id' => $productId,
            'players_count' => is_array($players) ? count($players) : 0,
            'shopify_product_id' => $shopifyProductId,
        ]);

        // helper to resolve a variant id for a product+size
        $resolveVariant = function($productId, $size = null, $incomingVariant = null, $shopifyProductId = null) {
            // if incoming variant present, use it
            if (!empty($incomingVariant)) return (string)$incomingVariant;

            // check DB product_variants
            if (!empty($size) && Schema::hasTable('product_variants')) {
                try {
                    $pv = ProductVariant::where('product_id', $productId)
                        ->where(function($q) use ($size) {
                            $q->where('option_value', $size)
                              ->orWhere('option_value', strtoupper($size))
                              ->orWhere('option_value', strtolower($size));
                        })
                        ->whereNotNull('shopify_variant_id')
                        ->first();
                    if ($pv && !empty($pv->shopify_variant_id)) return (string)$pv->shopify_variant_id;
                } catch (\Throwable $e) {
                    Log::warning('designer: product_variants_lookup_failed', ['err'=>$e->getMessage()]);
                }
            }

            // fallback: fetch product from Shopify Admin API and pick a variant
            if (!empty($shopifyProductId)) {
                try {
                    $shop = env('SHOPIFY_STORE');
                    $adminToken = env('SHOPIFY_ADMIN_API_TOKEN');
                    if ($shop && $adminToken) {
                        $resp = Http::withHeaders([
                            'X-Shopify-Access-Token' => $adminToken,
                            'Content-Type' => 'application/json'
                        ])->get("https://{$shop}/admin/api/2025-01/products/{$shopifyProductId}.json");

                        if ($resp->successful() && !empty($resp->json('product'))) {
                            $productData = $resp->json('product');
                            $variants = $productData['variants'] ?? [];
                            foreach ($variants as $v) {
                                $opt1 = $v['option1'] ?? '';
                                $title = $v['title'] ?? '';
                                if ($size && (strcasecmp(trim($opt1), trim($size)) === 0 || stripos($title, $size) !== false)) {
                                    return (string)$v['id'];
                                }
                            }
                            if (!empty($variants)) return (string)$variants[0]['id'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('designer: admin_fetch_failed', ['err'=>$e->getMessage()]);
                }
            }

            return null;
        };

        // build line items array for cartCreate
        $lines = [];

        if (is_array($players) && count($players) > 0) {
            foreach ($players as $pl) {
                $size = $pl['size'] ?? null;
                $incomingVariant = $pl['variant_id'] ?? null;

                $variantId = $resolveVariant($productId, $size, $incomingVariant, $shopifyProductId);

                if (empty($variantId)) {
                    Log::warning('designer: no_variant_for_player', ['player'=>$pl, 'product_id'=>$productId]);
                    continue; // skip this player if no variant found
                }

                $variantGid = 'gid://shopify/ProductVariant/' . (string)$variantId;

                $attrs = [
                    ['key'=>'Name', 'value'=>($pl['name'] ?? '')],
                    ['key'=>'Number','value'=>($pl['number'] ?? '')],
                    ['key'=>'Font','value'=>($pl['font'] ?? '')],
                    ['key'=>'Color','value'=>($pl['color'] ?? '')],
                ];

                $lines[] = [
                    'merchandiseId' => $variantGid,
                    'quantity' => 1,
                    'attributes' => array_map(function($a){ return ['key'=>$a['key'],'value'=>$a['value']]; }, $attrs)
                ];
            }
        }

        // if no players resolved, fallback to single line (quantity fallbackQuantity)
        if (empty($lines)) {
            // try to resolve a single variant
            $singleVariant = $resolveVariant($productId, $request->input('size', null), null, $shopifyProductId);
            if (empty($singleVariant)) {
                Log::error('designer: no_variant_any', ['product_id'=>$productId]);
                return response()->json(['error'=>'Could not determine any product variant (size).'], 422);
            }
            $lines[] = [
                'merchandiseId' => 'gid://shopify/ProductVariant/' . (string)$singleVariant,
                'quantity' => (int)$fallbackQuantity,
                'attributes' => [
                    ['key'=>'Name','value'=> $request->input('name_text','')],
                    ['key'=>'Number','value'=> $request->input('number_text','')],
                    ['key'=>'Font','value'=> $request->input('font','')],
                    ['key'=>'Color','value'=> $request->input('color','')],
                ]
            ];
        }

        // prepare GraphQL mutation
        $mutation = <<<'GRAPHQL'
mutation cartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
      createdAt
      updatedAt
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $variables = ['input' => ['lines' => $lines]];

        // call Storefront API
        $shop = env('SHOPIFY_STORE');
        $storefrontToken = env('SHOPIFY_STOREFRONT_TOKEN');
        if (empty($shop) || empty($storefrontToken)) {
            Log::error('designer: storefront_token_missing', ['shop'=>$shop]);
            return response()->json(['error'=>'Storefront token or shop missing'], 500);
        }

        try {
            $endpoint = "https://{$shop}/api/2024-10/graphql.json";
            $resp = Http::withHeaders([
                'X-Shopify-Storefront-Access-Token' => $storefrontToken,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'query' => $mutation,
                'variables' => $variables,
            ]);

            Log::info('designer: cartCreate_response', ['status'=>$resp->status(), 'body'=>strval($resp->body())]);

            if (!$resp->successful()) {
                return response()->json(['error'=>'cartCreate_failed','status'=>$resp->status(),'body'=>$resp->body()], 500);
            }

            $data = $resp->json();
            $userErrors = data_get($data, 'data.cartCreate.userErrors', []);
            if (!empty($userErrors)) {
                Log::error('designer: cartCreate_userErrors', ['errors'=>$userErrors,'body'=>$resp->body()]);
                return response()->json(['error'=>'cartCreate_userErrors','details'=>$userErrors,'body'=>$resp->body()], 500);
            }

            $cart = data_get($data, 'data.cartCreate.cart');
            $checkoutUrl = data_get($cart, 'checkoutUrl') ?: null;

            if (!empty($checkoutUrl)) {
                return response()->json(['checkoutUrl' => $checkoutUrl]);
            }

            // fallback to /cart/{variantId}:{qty} (take first line)
            $firstLine = $lines[0] ?? null;
            if ($firstLine) {
                // extract numeric id
                $gid = $firstLine['merchandiseId'];
                $numeric = $gid;
                if (is_string($numeric) && str_contains($numeric, '/')) {
                    $parts = explode('/', $numeric);
                    $numeric = end($parts);
                }
                if (!empty($numeric)) {
                    $fallback = "https://{$shop}/cart/{$numeric}:{$firstLine['quantity']}";
                    return response()->json(['checkoutUrl'=>$fallback]);
                }
            }

            return response()->json(['error'=>'no_checkout_url','body'=>$resp->body()], 500);

        } catch (\Throwable $e) {
            Log::error('designer: cartCreate_exception', ['err'=>$e->getMessage()]);
            return response()->json(['error'=>'exception','msg'=>$e->getMessage()], 500);
        }
    }
}
