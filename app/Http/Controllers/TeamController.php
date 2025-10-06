<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use App\Models\Team;
use App\Models\Product;
use App\Http\Controllers\ShopifyCartController;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    public function create(Request $request)
        {
            $product = Product::find($request->query('product_id'));
            $prefill = $request->only(['prefill_name','prefill_number','prefill_font','prefill_color','prefill_size']);

            $layoutSlots = [];
            // prefer DB saved slots
            if ($product && !empty($product->layout_slots)) {
                $layoutSlots = is_array($product->layout_slots) ? $product->layout_slots : json_decode($product->layout_slots, true);
            }
            // fallback to URL-passed slots (quick test)
            if (empty($layoutSlots) && $request->has('layoutSlots')) {
                $raw = urldecode($request->query('layoutSlots'));
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $layoutSlots = $decoded;
            }

            return view('team.create', compact('product','prefill','layoutSlots'));
        }

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

    // find product and build variant map (SIZE => shopify_variant_id)
    $product = Product::with('variants')->find($data['product_id']);
    if (!$product) {
        return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
    }

    $variantMap = [];
    if ($product->relationLoaded('variants') && $product->variants) {
        foreach ($product->variants as $v) {
            $k = trim((string) ($v->option_value ?? $v->option_name ?? ''));
            if ($k === '') continue;
            $variantMap[strtoupper($k)] = (string) ($v->shopify_variant_id ?? $v->variant_id ?? '');
        }
    }

    // attempt to fill missing variant_ids from provided size
    $playersProcessed = [];
    foreach ($data['players'] as $idx => $p) {
        $variantId = isset($p['variant_id']) && $p['variant_id'] ? (string)$p['variant_id'] : null;

        // if not provided, try to resolve using size => variantMap
        if (empty($variantId) && !empty($p['size'])) {
            $sizeKey = strtoupper(trim((string)$p['size']));
            $variantId = $variantMap[$sizeKey] ?? ($variantMap[strtoupper($sizeKey)] ?? null);
        }

        // still missing? try fallback: title-based mapping (sometimes option is in title)
        if (empty($variantId) && !empty($p['size']) && isset($product->variants)) {
            foreach ($product->variants as $v) {
                $title = strtoupper(trim((string)($v->title ?? '')));
                if ($title !== '' && strpos($title, strtoupper($p['size'])) !== false) {
                    $variantId = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? null);
                    break;
                }
            }
        }

        $playersProcessed[] = [
            'name' => $p['name'] ?? '',
            'number' => $p['number'] ?? '',
            'size' => $p['size'] ?? null,
            'font' => $p['font'] ?? '',
            'color' => $p['color'] ?? '',
            'variant_id' => $variantId,
        ];
    }

    // if any player still missing a valid variant id, reject and return a helpful error
    $missingVariants = array_filter($playersProcessed, function($pl) {
        return empty($pl['variant_id']) || !preg_match('/^\d+$/', (string)$pl['variant_id']);
    });
    if (!empty($missingVariants)) {
        $msg = 'One or more players do not have a valid size/variant selected. Please select size for each player.';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $msg], 422);
        }
        return back()->withInput()->with('error', $msg);
    }

    // Persist the team
    try {
        $team = Team::create([
            'product_id' => $data['product_id'],
            'players' => $playersProcessed,
            'created_by' => auth()->id() ?? null,
        ]);
    } catch (\Throwable $e) {
        Log::error('Team create failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Could not save team.'], 500);
        }
        return back()->with('error', 'Could not save team. Please try again.');
    }

    // Build cart pairs variant:qty (qty default 1)
    $pairs = [];
    foreach ($playersProcessed as $pl) {
        $vid = (string)$pl['variant_id'];
        $qty = isset($pl['quantity']) ? max(1, intval($pl['quantity'])) : 1;
        $pairs[] = $vid . ':' . $qty;
    }

    // Build the shopfront cart permalink
    $shopfront = rtrim(config('app.shopfront_url', env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in')), '/');
    $cartUrl = $shopfront . '/cart/' . implode(',', $pairs);

    // Optionally, you can attach preview URL or preview properties later by doing POST /cart/add before redirecting,
    // but permalink is simplest and works reliably with variant ids.

    // Return JSON or redirect
    if ($request->wantsJson() || $request->ajax()) {
        return response()->json([
            'success' => true,
            'team_id' => $team->id,
            'checkoutUrl' => $cartUrl,
        ], 200);
    }

    return redirect()->away($cartUrl);
}

}
