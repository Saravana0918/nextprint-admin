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
    public function saveDesign(Request $request)
{
    // validation (same as before)
    $data = $request->validate([
        'product_id' => 'nullable|integer',
        'order_id' => 'nullable|integer',
        'players' => 'required|array|min:1',
        'players.*.name' => 'nullable|string|max:255',
        'players.*.number' => 'nullable|string|max:20',
        'players.*.size' => 'nullable|string|max:20',
        'players.*.font' => 'nullable|string|max:100',
        'preview_src' => 'nullable|string', // can be path or dataURL
    ]);

    DB::beginTransaction();
    try {
        // 1) Save preview image if dataURL
        $previewPath = null;
        if (!empty($data['preview_src']) && Str::startsWith($data['preview_src'], 'data:')) {
            $matches = [];
            if (preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $data['preview_src'], $matches)) {
                $mime = $matches[1];
                $base64 = $matches[2];
                $ext = explode('/', $mime)[1] ?? 'png';
                $binary = base64_decode($base64);
                $filename = 'team_preview_' . time() . '_' . Str::random(6) . '.' . $ext;
                $storagePath = 'team_previews/' . $filename;
                Storage::disk('public')->put($storagePath, $binary);
                $previewPath = 'storage/' . $storagePath; // asset path
            }
        } elseif (!empty($data['preview_src'])) {
            $previewPath = $data['preview_src'];
        }

        // 2) Normalize players array
        $players = array_map(function($p) {
            $p = (array)$p;
            return [
                'id' => $p['id'] ?? null,
                'name' => $p['name'] ?? null,
                'number' => $p['number'] ?? null,
                'size' => $p['size'] ?? null,
                'font' => $p['font'] ?? null,
                'preview_src' => $p['preview_src'] ?? null,
                'variant_id' => $p['variant_id'] ?? null,
            ];
        }, $data['players']);

        // 3) Insert team row
        $teamId = DB::table('teams')->insertGetId([
            'product_id' => $data['product_id'] ?? null,
            'players' => json_encode($players, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'preview_url' => $previewPath,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---------- REPLACE START (order upsert block) ----------
$first = $players[0] ?? null;

// Build orderData using ONLY existing design_orders columns
$orderData = [
    'team_id'       => $teamId,
    'product_id'    => $data['product_id'] ?? null,
    'shopify_product_id' => $shopifyProductId ?? null,
    'variant_id'    => $first['variant_id'] ?? null,
    'size'          => $first['size'] ?? null,
    'name_text'     => $first['name'] ?? ($data['name_text'] ?? null),
    'number_text'   => $first['number'] ?? ($data['number_text'] ?? null),
    'preview_src'   => $previewPath ?? null,
    'preview_path'  => $previewPath ?? null,
    'raw_payload'   => json_encode(['team_id' => $teamId, 'players' => $players], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    'payload'       => json_encode(['players' => $players], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    'status'        => 'new',
    'created_at'    => now(),
    'updated_at'    => now(),
];

$linkedOrderId = null;

// 1) If frontend provided explicit order_id -> update that
if (!empty($data['order_id'])) {
    DB::table('design_orders')->where('id', $data['order_id'])->update($orderData);
    $linkedOrderId = $data['order_id'];
} else {
    // 2) Try find existing design_order that already references this team in raw_payload
    $existing = DB::table('design_orders')->where(function($q) use ($teamId) {
        $q->where('raw_payload', 'like', '%"team_id":' . $teamId . '%')
          ->orWhere('raw_payload', 'like', '%"team_id":"' . $teamId . '"%');
    })->orderBy('created_at', 'desc')->first();

    if ($existing) {
        DB::table('design_orders')->where('id', $existing->id)->update($orderData);
        $linkedOrderId = $existing->id;
    } else {
        // 3) If not found, try best-effort match by product_id + name/number
        $candidateQuery = DB::table('design_orders')->orderBy('created_at', 'desc');
        if (!empty($data['product_id'])) {
            $candidateQuery->where('product_id', $data['product_id']);
        }
        $candidateQuery->where(function($q) use ($first) {
            if (!empty($first['number'])) {
                $q->orWhere('number_text', $first['number']);
            }
            if (!empty($first['name'])) {
                $q->orWhere('name_text', 'like', '%' . substr($first['name'], 0, 50) . '%');
            }
        });
        $candidate = $candidateQuery->first();

        if ($candidate) {
            DB::table('design_orders')->where('id', $candidate->id)->update($orderData);
            $linkedOrderId = $candidate->id;
        } else {
            // 4) Nothing found -> create a minimal design_orders row (safe columns only)
            $linkedOrderId = DB::table('design_orders')->insertGetId($orderData);
        }
    }
}
// ---------- REPLACE END ----------


        DB::commit();

        // Return success with team_id and linked order id
        return response()->json([
            'success' => true,
            'team_id' => $teamId,
            'order_id' => $linkedOrderId,
            'preview_url' => $previewPath ? asset($previewPath) : null,
            'message' => 'Design saved ✅ and linked to order #' . ($linkedOrderId ?? 'N/A'),
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('TeamController::saveDesign failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json([
            'success' => false,
            'error' => 'Could not save team. Check logs.',
            'details' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Classic form submit endpoint (Add To Cart / Team store)
     */
    public function store(Request $request)
{
    // load product early so we can detect layout slots
    $product = Product::with('variants')->find($request->input('product_id'));
    $hasNumberSlot = false;
    if (!empty($data['product_id'])) {
        $p = Product::find($data['product_id']);
        if ($p && !empty($p->layout_slots)) {
            $ls = is_array($p->layout_slots) ? $p->layout_slots : @json_decode($p->layout_slots, true);
            $hasNumberSlot = !empty($ls) && !empty($ls['number']);
        }
    }

    // when building $orderData use number only if hasNumberSlot
    $orderData['number_text'] = $hasNumberSlot ? ($first['number'] ?? null) : null;

    // build validation rules dynamically
    $rules = [
        'team_id'           => 'nullable|integer|exists:teams,id',
        'product_id'        => 'required|integer|exists:products,id',
        'players'           => 'required|array|min:1',
        'players.*.name'    => 'required|string|max:60',
        'players.*.size'    => 'nullable|string|max:10',
        'players.*.font'    => 'nullable|string|max:50',
        'players.*.color'   => 'nullable|string|max:20',
        'players.*.variant_id' => 'nullable',
        'preview_url'       => 'nullable|string',
    ];

    if ($hasNumberSlot) {
        // require a numeric number when product supports numbers
        $rules['players.*.number'] = ['required', 'regex:/^\d{1,3}$/'];
    } else {
        // no number slot — accept nullable and will be ignored
        $rules['players.*.number'] = 'nullable|string|max:10';
    }

    $data = $request->validate($rules);

    // now process players
    $variantMap = [];
    if ($product && $product->relationLoaded('variants') && $product->variants) {
        foreach ($product->variants as $v) {
            $k = trim((string) ($v->option_value ?? $v->option_name ?? ''));
            if ($k === '') continue;
            $variantMap[strtoupper($k)] = (string) ($v->shopify_variant_id ?? $v->variant_id ?? '');
        }
    }

    $playersProcessed = [];
    foreach ($data['players'] as $p) {
        // normalize fields
        $name = $p['name'] ?? '';
        $num  = $p['number'] ?? '';
        // if product doesn't support numbers, ensure we clear number
        if (!$hasNumberSlot) $num = '';

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
            'name' => $name ?? '',
            'number' => $num ?? '',
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
                'preview_url' => $previewPath ? asset($previewPath) : null,
                'checkoutUrl' => $cartUrl,
            ], 200);
        }

        return redirect()->away($cartUrl);
    }
}
