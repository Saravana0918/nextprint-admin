<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Models\Team;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Http\Controllers\ShopifyCartController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class TeamController extends Controller
{
    /**
     * Show create team page.
     */
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        if (!$productId) {
            abort(404, 'product_id missing');
        }

        // EAGER LOAD variants so view always gets them
        $product = Product::with('variants')->find($productId);
        if (!$product) {
            abort(404, 'Product not found: ' . $productId);
        }

        $prefill = $request->only(['prefill_name','prefill_number','prefill_font','prefill_color','prefill_size']);

        $layoutSlots = [];
        if (!empty($product->layout_slots)) {
            $layoutSlots = is_array($product->layout_slots) ? $product->layout_slots : json_decode($product->layout_slots, true);
        }

        if (empty($layoutSlots) && $request->has('layoutSlots')) {
            $raw = urldecode($request->query('layoutSlots'));
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $layoutSlots = $decoded;
        }

        // Build server-side variantMap and pass to view
        $variantMap = [];
        foreach ($product->variants as $v) {
            $key = trim((string)($v->option_value ?? $v->option_name ?? ''));
            if ($key === '') continue;
            $variantMap[strtoupper($key)] = (string)($v->shopify_variant_id ?? $v->variant_id ?? '');
        }

        return view('team.create', compact('product','prefill','layoutSlots','variantMap'));
    }

    /**
     * Store team and attempt to add players to Shopify cart / checkout.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'        => 'required|integer|exists:products,id',
            'players'           => 'required|array|min:1',
            'players.*.name'    => 'required|string|max:60',
            'players.*.number'  => ['required', 'regex:/^\d{1,3}$/'],
            'players.*.size'    => 'nullable|string|max:10',
            'players.*.font'    => 'nullable|string|max:50',
            'players.*.color'   => 'nullable|string|max:20',
            'players.*.variant_id' => 'nullable',
        ]);

        // Normalize players, ensure variant_id exists if possible
        $normalizedPlayers = [];
        $missingVariantRows = [];

        foreach ($data['players'] as $index => $p) {
            $name = $p['name'] ?? '';
            $number = $p['number'] ?? '';
            $size = isset($p['size']) ? trim((string)$p['size']) : null;
            $variantId = isset($p['variant_id']) ? trim((string)$p['variant_id']) : null;

            // If variant_id missing but size present, try to lookup in DB (case-insensitive)
            if (empty($variantId) && $size) {
                $pv = ProductVariant::where('product_id', $data['product_id'])
                    ->whereRaw('UPPER(option_value) = ?', [strtoupper($size)])
                    ->first();
                if ($pv) {
                    $variantId = (string)$pv->shopify_variant_id;
                }
            }

            $normalizedPlayers[] = [
                'name' => $name,
                'number' => $number,
                'size' => $size,
                'font' => $p['font'] ?? '',
                'color' => $p['color'] ?? '',
                'variant_id' => $variantId ?: null,
            ];

            if (empty($variantId)) {
                $missingVariantRows[] = [
                    'index' => $index,
                    'name' => $name,
                    'number' => $number,
                    'size' => $size,
                ];
            }
        }

        // If any players are missing variant_id, fail with helpful message
        if (!empty($missingVariantRows)) {
            Log::warning('Team store missing variant ids', ['product_id' => $data['product_id'], 'missing' => $missingVariantRows]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more players are missing size → variant mapping. Please select a valid size for each player.',
                    'missing' => $missingVariantRows
                ], 422);
            }

            return back()->withInput()->with('error', 'One or more players are missing size → variant mapping. Please ensure each player has a valid size selected.');
        }

        // Save Team
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players' => $normalizedPlayers,
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
            }
            return back()->with('error', 'Could not save team. Please try again.');
        }

        // Build payload for ShopifyCartController
        $playersForShopify = [];
        foreach ($normalizedPlayers as $p) {
            $playersForShopify[] = [
                'name' => $p['name'],
                'number' => $p['number'],
                'size' => $p['size'],
                'font' => $p['font'],
                'color' => $p['color'],
                'variant_id' => $p['variant_id'],
            ];
        }

        $product = Product::find($data['product_id']);
        $shopifyProductId = $product->shopify_product_id ?? null;

        $shopifyPayload = [
            'product_id' => $data['product_id'],
            'players' => $playersForShopify,
            'shopify_product_id' => $shopifyProductId ? (string)$shopifyProductId : null,
            'team_id' => $team->id,
        ];

        Log::info('TeamController: calling ShopifyCartController->addToCart', ['payload' => $shopifyPayload]);

        try {
            $shopifyController = app(ShopifyCartController::class);
            $resp = $shopifyController->addToCart(new Request($shopifyPayload));

            Log::info('Shopify addToCart resp type: ' . (is_object($resp) ? get_class($resp) : gettype($resp)));

            // try to determine checkout URL
            $checkoutUrl = null;
            if ($resp instanceof JsonResponse) {
                $json = $resp->getData(true);
                $checkoutUrl = $json['checkoutUrl'] ?? $json['checkout_url'] ?? null;
            } elseif ($resp instanceof Response) {
                $content = $resp->getContent();
                $maybe = @json_decode($content, true);
                if (is_array($maybe)) $checkoutUrl = $maybe['checkoutUrl'] ?? $maybe['checkout_url'] ?? null;
            } elseif (is_array($resp)) {
                $checkoutUrl = $resp['checkoutUrl'] ?? $resp['checkout_url'] ?? null;
            } elseif (is_string($resp) && filter_var($resp, FILTER_VALIDATE_URL)) {
                $checkoutUrl = $resp;
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'team_id' => $team->id,
                    'checkoutUrl' => $checkoutUrl,
                ], 200);
            }

            if (!empty($checkoutUrl)) {
                return redirect()->away($checkoutUrl);
            }

            // If Shopify controller didn't give a checkout URL, redirect to team page
            return redirect()->route('team.show', $team->id)->with('success', 'Team saved. Proceed to cart manually.');
        } catch (\Throwable $e) {
            // Log the error and payload for debugging
            Log::error('Shopify addToCart failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $shopifyPayload
            ]);

            // If GraphQL/Shopify admin failed with "merchandise does not exist" we can fallback
            // to returning an auto-submitting HTML that posts to the storefront /cart/add for each line.
            // This is a browser fallback so admin pages still redirect the user to storefront cart.
            $message = $e->getMessage();

            // If request expects JSON, return error info
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not add to Shopify cart (admin API). Falling back to client posting method.',
                    'error' => $message,
                ], 500);
            }

            // Prepare fallback HTML form (auto-submit) to post multiple /cart/add lines to the storefront
            // Use shopfront URL from env
            $shopfront = rtrim(env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in'), '/');

            // Build multiple forms (one per variant) — properties: Name & Number & Size
            $formsHtml = '';
            foreach ($playersForShopify as $p) {
                $variant = $p['variant_id'] ?? null;
                if (!$variant) continue;
                $props = [
                    'properties[Name]' => $p['name'],
                    'properties[Number]' => $p['number'],
                    'properties[Size]' => $p['size'],
                    'properties[Font]' => $p['font'],
                    'properties[Color]' => $p['color'],
                    // optionally include team_id
                    'properties[Team ID]' => (string)$team->id,
                ];
                $inputs = '<input type="hidden" name="id" value="' . e($variant) . '"/>' . "\n";
                $inputs .= '<input type="hidden" name="quantity" value="1"/>' . "\n";
                foreach ($props as $k => $v) {
                    $inputs .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '"/>' . "\n";
                }

                $formsHtml .= <<<HTML
<form method="POST" action="{$shopfront}/cart/add" class="np-fallback-form" style="display:none">
  {$inputs}
  <noscript><button type="submit">Add</button></noscript>
</form>

HTML;
            }

            // If no fallback forms (no variants), just redirect back with error
            if (trim($formsHtml) === '') {
                return back()->with('error', 'Could not add to Shopify cart and fallback unavailable. ' . $message);
            }

            // Auto-submit script and simple message
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Redirecting to cart...</title></head><body style="font-family:Arial,Helvetica,sans-serif;padding:24px;">';
            $html .= '<h2>Redirecting to storefront cart...</h2>';
            $html .= '<p>If your browser does not redirect automatically, click the button below.</p>';
            $html .= $formsHtml;
            $html .= '<button id="fallbackContinue" style="display:block;margin-top:16px;padding:10px 16px;font-size:16px">Continue to cart</button>';
            $html .= '<script>';
            $html .= '(async function(){ try { const forms = Array.from(document.querySelectorAll(".np-fallback-form")); for (const f of forms) { await fetch(f.action, { method:"POST", body:new URLSearchParams(new FormData(f)), credentials:"include" }); } window.location.href = "' . addslashes($shopfront) . '/cart"; } catch(e) { console.error(e); document.getElementById("fallbackContinue").style.display="block"; } })();';
            $html .= 'document.getElementById("fallbackContinue").addEventListener("click", function(){ const forms = Array.from(document.querySelectorAll(".np-fallback-form")); for (const f of forms) { f.submit(); } });';
            $html .= '</script>';
            $html .= '</body></html>';

            return response($html, 200)->header('Content-Type', 'text/html');
        }
    }
}
