<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\Team;
use App\Models\Product;

class TeamController extends Controller
{
    /**
     * Show team create page (prefill from designer query params)
     */
    public function create(Request $request)
    {
        $product = Product::with('variants')->find($request->query('product_id'));
        $prefill = $request->only([
            'prefill_name','prefill_number','prefill_font',
            'prefill_color','prefill_size','prefill_logo'
        ]);

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

    /**
     * AJAX endpoint used by designer to save design preview + players (JSON)
     * Returns team_id and preview_url on success.
     */
    public function saveDesign(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'product_id'   => 'required|integer|exists:products,id',
            'players'      => 'required|array|min:1',
            'players.*.name'   => 'required|string|max:60',
            'players.*.number' => ['required','regex:/^\d{1,3}$/'],
            'players.*.size'   => 'nullable|string|max:10',
            'players.*.font'   => 'nullable|string|max:50',
            'players.*.color'  => 'nullable|string|max:20',
            'team_logo_url'     => 'nullable|string',
            'preview_data'      => 'nullable|string', // data URL
        ]);

        // normalize players array
        $playersProcessed = [];
        foreach ($payload['players'] as $p) {
            $playersProcessed[] = [
                'name' => (string)($p['name'] ?? ''),
                'number' => (string)($p['number'] ?? ''),
                'size' => $p['size'] ?? null,
                'font' => $p['font'] ?? '',
                'color' => $p['color'] ?? '',
                'variant_id' => $p['variant_id'] ?? null,
            ];
        }

        // Save preview image if provided (data:image/...;base64,...)
        $previewUrl = null;
        if (!empty($payload['preview_data']) && preg_match('#^data:image\/(png|jpeg|jpg);base64,#i', $payload['preview_data'])) {
            try {
                $base64 = preg_replace('#^data:image\/[a-zA-Z]+;base64,#', '', $payload['preview_data']);
                $bytes = base64_decode($base64);
                if ($bytes === false) throw new \Exception('base64 decode failed');
                $fileName = 'team_previews/' . date('Ymd_His') . '_' . Str::random(8) . '.png';
                Storage::disk('public')->put($fileName, $bytes);
                $previewUrl = Storage::disk('public')->url($fileName);
            } catch (\Throwable $e) {
                Log::error('Failed saving preview image: ' . $e->getMessage());
                $previewUrl = null; // continue
            }
        }

        // Create Team model
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

        // Ensure admin list shows this design: insert row into design_orders using columns that exist
        try {
            $first = $playersProcessed[0] ?? null;
            $firstName = $first['name'] ?? null;
            $firstNumber = $first['number'] ?? null;

            // Build raw_payload that includes team_id so future lookups can find it
            $rawPayload = [
                'team_id' => $team->id,
                'players' => $playersProcessed,
                'team_logo_url' => $payload['team_logo_url'] ?? null,
            ];

            $insert = [
                'product_id'         => $payload['product_id'],
                'shopify_product_id' => null,
                'variant_id'         => $first['variant_id'] ?? null,
                'size'               => $first['size'] ?? null,
                'quantity'           => 1,
                'name_text'          => $firstName ? mb_strtoupper($firstName) : null,
                'number_text'        => $firstNumber ?: null,
                'font'               => $first['font'] ?? null,
                'color'              => $first['color'] ?? null,
                'uploaded_logo_url'  => $payload['team_logo_url'] ?? null,
                'preview_src'        => $previewUrl ?? null,
                'preview_path'       => $previewUrl ?? null,
                // store the raw payload as JSON string (so we can search text later)
                'raw_payload'        => json_encode($rawPayload),
                // also store in payload (some schemas expect longtext)
                'payload'            => json_encode(['players' => $playersProcessed]),
                'status'             => 'new',
                'created_at'         => Carbon::now(),
                'updated_at'         => Carbon::now(),
            ];

            // Find existing by searching raw_payload text for team_id (works even if column is TEXT)
            $found = DB::table('design_orders')->where(function($q) use ($team) {
                $q->where('raw_payload', 'like', '%"team_id":' . $team->id . '%')
                  ->orWhere('raw_payload', 'like', '%"team_id":"' . $team->id . '"%');
            })->first();

            if ($found) {
                DB::table('design_orders')->where('id', $found->id)->update($insert);
            } else {
                DB::table('design_orders')->insert($insert);
            }
        } catch (\Throwable $e) {
            Log::warning('Insert into design_orders failed: ' . $e->getMessage(), ['team_id' => $team->id ?? null]);
            // do not fail user flow
        }

        return response()->json([
            'success' => true,
            'team_id' => $team->id,
            'preview_url' => $previewUrl,
        ], 200);
    }

    /**
     * Classic form submit endpoint (Add To Cart / Team store)
     */
    public function store(Request $request)
    {
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

        $product = Product::with('variants')->find($data['product_id']);
        if (!$product) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Product not found.'], 404);
            }
            return back()->with('error', 'Product not found.');
        }

        $variantMap = [];
        if ($product->relationLoaded('variants') && $product->variants) {
            foreach ($product->variants as $v) {
                $k = trim((string) ($v->option_value ?? $v->option_name ?? ''));
                if ($k === '') continue;
                $variantMap[strtoupper($k)] = (string) ($v->shopify_variant_id ?? $v->variant_id ?? '');
            }
        }

        $playersProcessed = [];
        foreach ($data['players'] as $p) {
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

        try {
            if (!empty($data['team_id'])) {
                $team = Team::find($data['team_id']);
                if (!$team) {
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json(['success' => false, 'message' => 'Team not found.'], 404);
                    }
                    return back()->with('error', 'Team not found.');
                }
                $team->players = $playersProcessed;
                if (!empty($data['preview_url'])) $team->preview_url = $data['preview_url'];
                $team->save();
            } else {
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

        // ensure design_orders entry exists (insert or update) using raw_payload search
        try {
            $firstPlayer = $playersProcessed[0] ?? null;
            $orderData = [
                'product_id' => $data['product_id'],
                'product_name' => $product->name ?? null,
                'shopify_product_id' => $product->shopify_product_id ?? null,
                'variant_id' => $firstPlayer['variant_id'] ?? null,
                'size' => $firstPlayer['size'] ?? null,
                'name_text' => $firstPlayer['name'] ?? null,
                'number_text' => $firstPlayer['number'] ?? null,
                'preview_src' => $data['preview_url'] ?? $team->preview_url ?? null,
                'preview_path' => $data['preview_url'] ?? $team->preview_url ?? null,
                'raw_payload' => json_encode(['team_id' => $team->id, 'players' => $playersProcessed]),
                'payload' => json_encode(['players' => $playersProcessed]),
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $existing = DB::table('design_orders')->where(function($q) use ($team) {
                $q->where('raw_payload', 'like', '%"team_id":' . $team->id . '%')
                  ->orWhere('raw_payload', 'like', '%"team_id":"' . $team->id . '"%');
            })->first();

            if ($existing) {
                DB::table('design_orders')->where('id', $existing->id)->update($orderData);
            } else {
                DB::table('design_orders')->insert($orderData);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not ensure design_orders row exists: ' . $e->getMessage(), ['team_id' => $team->id ?? null]);
        }

        // Build shopfront cart pairs
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
