<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Team;
use App\Http\Controllers\ShopifyCartController;
use Exception;

class TeamController extends Controller
{
    /**
     * Show the team create view (optionally prefilled from query string).
     */
    public function create(Request $request)
    {
        $productId = $request->query('product_id');
        $product = null;
        if ($productId) {
            $product = Product::find($productId);
        }

        $prefill = [
            'name'   => $request->query('prefill_name', ''),
            'number' => $request->query('prefill_number', ''),
            'font'   => $request->query('prefill_font', ''),
            'color'  => $request->query('prefill_color', ''),
            'size'   => $request->query('prefill_size', ''),
        ];

        return view('team.create', compact('product', 'prefill'));
    }

    /**
     * Persist team + players to DB (teams table) and redirect back with a message.
     */
   public function store(Request $request)
    {
        // 1) Validate input
        $data = $request->validate([
            'product_id' => 'required|integer',
            'players' => 'required|array|min:1',
            'players.*.name' => 'required|string|max:12',
            'players.*.number' => ['required','regex:/^\d{1,3}$/'],
            'players.*.size' => 'nullable|string|max:10',
            'players.*.font' => 'nullable|string|max:50',
            'players.*.color' => 'nullable|string|max:20',
        ]);

        // normalize players (uppercase name, sanitize number)
        foreach ($data['players'] as &$p) {
            $p['name'] = isset($p['name']) ? mb_strtoupper($p['name']) : null;
            $p['number'] = isset($p['number']) ? preg_replace('/\D/','',$p['number']) : null;
            $p['size'] = $p['size'] ?? null;
            $p['font'] = $p['font'] ?? null;
            $p['color'] = $p['color'] ?? null;
        }
        unset($p);

        // 2) Save team (optional) with try/catch
        try {
            $team = Team::create([
                'product_id' => $data['product_id'],
                'players'    => $data['players'],
                'created_by' => auth()->id() ?? null,
            ]);
            Log::info('team.store.saved', ['team_id' => $team->id ?? null, 'product_id' => $data['product_id']]);
        } catch (Exception $e) {
            Log::warning('team.store.save_failed', ['err' => $e->getMessage()]);
            // we continue — team save failing should not block checkout ideally
        }

        // 3) Prepare payload for Shopify addToCart
        $first = $data['players'][0] ?? [];
        $nameText = implode(', ', array_map(function($p){ return ($p['name'] ?? '') . '#' . ($p['number'] ?? ''); }, $data['players']));

        $internalRequest = new Request([
            'product_id'  => $data['product_id'],
            'quantity'    => 1,
            'name_text'   => $nameText,
            'number_text' => 'TEAM',
            'font'        => $first['font'] ?? '',
            'color'       => $first['color'] ?? '',
            'preview_data'=> null,
        ]);

        // 4) Call ShopifyCartController::addToCart and handle response
        try {
            $shopify = app(ShopifyCartController::class);
            $response = $shopify->addToCart($internalRequest);

            // If JsonResponse, get array
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $json = $response->getData(true);
            } elseif (method_exists($response, 'getContent')) {
                $json = json_decode($response->getContent(), true) ?? [];
            } else {
                $json = [];
            }

            Log::info('team.store.shopify_resp', ['json' => $json]);

            if (!empty($json['checkoutUrl'])) {
                // Redirect user to Shopify checkout
                return redirect()->away($json['checkoutUrl']);
            } else {
                // if there are userErrors or no url, show message
                $msg = $json['error'] ?? 'Could not create Shopify cart - no checkout url.';
                return back()->with('error', $msg);
            }

        } catch (Exception $e) {
            Log::error('team.store.shopify_exception', ['err' => $e->getMessage()]);
            return back()->with('error', 'Failed to add to Shopify cart. (see logs)');
        }
    }
}
