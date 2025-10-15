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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    public function create(Request $request)
{
    // eager-load variants
    $product = Product::with('variants')->find($request->query('product_id'));
    $prefill = $request->only(['prefill_name','prefill_number','prefill_font','prefill_color','prefill_size']);

    $layoutSlots = [];
    if ($product && !empty($product->layout_slots)) {
        $layoutSlots = is_array($product->layout_slots) ? $product->layout_slots : json_decode($product->layout_slots, true);
    }
    if (empty($layoutSlots) && $request->has('layoutSlots')) {
        $raw = urldecode($request->query('layoutSlots'));
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $layoutSlots = $decoded;
    }

    return view('team.create', compact('product','prefill','layoutSlots'));
}

public function saveDesign(Request $request): JsonResponse
{
    // Accept JSON payload
    $payload = $request->validate([
        'product_id'   => 'required|integer|exists:products,id',
        'players'      => 'required|array|min:1',
        'players.*.name'   => 'required|string|max:60',
        'players.*.number' => ['required','regex:/^\d{1,3}$/'],
        'players.*.size'   => 'nullable|string|max:10',
        'players.*.font'   => 'nullable|string|max:50',
        'players.*.color'  => 'nullable|string|max:20',
        'team_logo_url'     => 'nullable|string',
        'preview_data'      => 'nullable|string', // data:image/png;base64,...
    ]);

    // normalize players similar to store()
    $playersProcessed = [];
    foreach ($payload['players'] as $p) {
        $playersProcessed[] = [
            'name' => (string)($p['name'] ?? ''),
            'number' => (string)($p['number'] ?? ''),
            'size' => $p['size'] ?? null,
            'font' => $p['font'] ?? '',
            'color' => $p['color'] ?? '',
            'variant_id' => $p['variant_id'] ?? null, // optional, store if provided
        ];
    }

    // Save preview image (if present)
    $previewUrl = null;
    if (!empty($payload['preview_data']) && preg_match('#^data:image\/(png|jpeg|jpg);base64,#i', $payload['preview_data'])) {
        try {
            // strip metadata
            $base64 = preg_replace('#^data:image\/[a-zA-Z]+;base64,#', '', $payload['preview_data']);
            $bytes = base64_decode($base64);
            if ($bytes === false) throw new \Exception('base64 decode failed');

            // file path
            $fileName = 'team_previews/' . date('Ymd_His') . '_' . Str::random(8) . '.png';
            Storage::disk('public')->put($fileName, $bytes);
            $previewUrl = Storage::disk('public')->url($fileName);
        } catch (\Throwable $e) {
            Log::error('Failed saving preview image: ' . $e->getMessage());
            // don't fail whole request — continue without preview
            $previewUrl = null;
        }
    }

    // Create team
    try {
        $team = Team::create([
            'product_id' => $payload['product_id'],
            'players' => $playersProcessed,
            'team_logo_url' => $payload['team_logo_url'] ?? null,
            'preview_url' => $previewUrl,
            'created_by' => auth()->id() ?? null,
        ]);
    } catch (\Throwable $e) {
        Log::error('Team saveDesign failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['success' => false, 'message' => 'Could not save design.'], 500);
    }

    return response()->json([
        'success' => true,
        'team_id' => $team->id,
        'preview_url' => $previewUrl,
    ], 200);
}

public function store(Request $request)
{
    // allow optional team_id (if provided we update that team instead of creating new)
    $data = $request->validate([
        'team_id'           => 'nullable|integer|exists:teams,id',
        'product_id'        => 'required|integer|exists:products,id',
        'players'           => 'required|array|min:1',
        'players.*.name'    => 'required|string|max:60',
        'players.*.number'  => ['required', 'regex:/^\d{1,3}$/'],
        'players.*.size'    => 'nullable|string|max:10',
        'players.*.font'    => 'nullable|string|max:50',
        'players.*.color'   => 'nullable|string|max:20',
        'players.*.variant_id' => 'nullable',
        'preview_url'       => 'nullable|string',
    ]);

    // find product and build variant map (same as before)
    $product = Product::with('variants')->find($data['product_id']);
    if (!$product) {
        return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
    }

    // same resolution logic as you had to resolve missing variant ids if needed
    $variantMap = [];
    if ($product->relationLoaded('variants') && $product->variants) {
        foreach ($product->variants as $v) {
            $k = trim((string) ($v->option_value ?? $v->option_name ?? ''));
            if ($k === '') continue;
            $variantMap[strtoupper($k)] = (string) ($v->shopify_variant_id ?? $v->variant_id ?? '');
        }
    }

    $playersProcessed = [];
    foreach ($data['players'] as $idx => $p) {
        $variantId = isset($p['variant_id']) && $p['variant_id'] ? (string)$p['variant_id'] : null;

        if (empty($variantId) && !empty($p['size'])) {
            $sizeKey = strtoupper(trim((string)$p['size']));
            $variantId = $variantMap[$sizeKey] ?? null;
        }

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

    // check for missing variant ids
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

    // If team_id provided -> update existing team record
    try {
        if (!empty($data['team_id'])) {
            $team = Team::find($data['team_id']);
            if (!$team) {
                return response()->json(['success' => false, 'message' => 'Team not found.'], 404);
            }
            $team->players = $playersProcessed;
            if (!empty($data['preview_url'])) $team->preview_url = $data['preview_url'];
            $team->save();
        } else {
            // create new team (as before)
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players' => $playersProcessed,
                'preview_url' => $data['preview_url'] ?? null,
                'created_by' => auth()->id() ?? null,
            ]);
        }
    } catch (\Throwable $e) {
        Log::error('Team create/update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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

    $shopfront = rtrim(config('app.shopfront_url', env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in')), '/');
    $cartUrl = $shopfront . '/cart/' . implode(',', $pairs);

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
