<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DesignOrderController extends Controller
{
    /**
     * List all design orders
     */
    public function index()
    {
        $rows = DB::table('design_orders as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->select([
                'd.id',
                'd.shopify_product_id',
                'd.product_id',
                DB::raw("p.name as product_name"),
                'd.name_text',
                'd.number_text',
                'd.preview_src',
                'd.created_at'
            ])
            ->orderBy('d.created_at', 'desc')
            ->paginate(25);

        return view('admin.design_orders.index', ['rows' => $rows]);
    }

    /**
     * Show single design order with team players & metadata
     */
    public function show($id)
    {
        $order = DB::table('design_orders as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->select(['d.*', DB::raw('p.name as product_name')])
            ->where('d.id', $id)
            ->first();

        if (! $order) {
            abort(404, 'Design order not found');
        }

        $players = collect();

        try {
            /** STEP 1: Direct team_id on design_orders */
            if (!empty($order->team_id)) {
                $team = DB::table('teams')->where('id', (int)$order->team_id)->first();
                if ($team && !empty($team->players)) {
                    $decoded = json_decode($team->players, true);
                    if (is_array($decoded)) {
                        $players = collect($decoded)->map(function ($p, $i) {
                            $p = (array)$p;
                            return (object)[
                                'id' => $p['id'] ?? $i + 1,
                                'name' => $p['name'] ?? '',
                                'number' => $p['number'] ?? '',
                                'size' => $p['size'] ?? '',
                                'font' => $p['font'] ?? '',
                                'preview_src' => $p['preview_src'] ?? null,
                                'created_at' => $p['created_at'] ?? now(),
                            ];
                        });
                    }
                }

                if ($players->isEmpty()) {
                    $players = DB::table('team_players')
                        ->where('team_id', $order->team_id)
                        ->orderBy('id')
                        ->get();
                }
            }

            /** STEP 2: META fallback */
            if ($players->isEmpty() && !empty($order->meta)) {
                $metaDecoded = json_decode($order->meta, true);
                if (is_string($metaDecoded)) {
                    $metaDecoded = json_decode($metaDecoded, true);
                }

                if (!empty($metaDecoded['players']) && is_array($metaDecoded['players'])) {
                    $players = collect($metaDecoded['players'])->map(function ($p, $i) use ($order) {
                        $p = (array)$p;
                        return (object)[
                            'id' => $p['id'] ?? $i + 1,
                            'name' => $p['name'] ?? '',
                            'number' => $p['number'] ?? '',
                            'size' => $p['size'] ?? '',
                            'font' => $p['font'] ?? $order->font ?? '',
                            'preview_src' => $p['preview_src'] ?? null,
                            'created_at' => $p['created_at'] ?? $order->created_at ?? now(),
                        ];
                    });
                } elseif (!empty($metaDecoded['team_id'])) {
                    $players = DB::table('team_players')
                        ->where('team_id', (int)$metaDecoded['team_id'])
                        ->orderBy('id')
                        ->get();
                }
            }

            /** STEP 3: Last fallback — team_players by product_id */
            if ($players->isEmpty() && !empty($order->product_id)) {
                $players = DB::table('team_players')
                    ->where('product_id', $order->product_id)
                    ->orderBy('id')
                    ->get();
            }

            /** STEP 4: Normalize */
            $players = collect($players)->map(function ($p) {
                if (is_array($p)) return (object)$p;
                return $p;
            });
        } catch (\Throwable $e) {
            Log::warning('DesignOrderController::show players fetch failed: ' . $e->getMessage());
            $players = collect();
        }

        return view('admin.design_orders.show', compact('order', 'players'));
    }

public function download($id)
{
    \Log::info("DOWNLOAD START: order_id={$id}");
    try {
        $order = DB::table('design_orders')->where('id', $id)->first();
        if (! $order) {
            \Log::warning("DOWNLOAD: order not found {$id}");
            abort(404, 'Design order not found');
        }

        // try to find players: prefer raw_payload/payload, fallback to teams table if team_id present
        $players = [];
        $raw = $order->raw_payload ?? $order->payload ?? null;
        if ($raw) {
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $decoded = json_decode($rawStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!empty($decoded['players']) && is_array($decoded['players'])) {
                    $players = $decoded['players'];
                } elseif (!empty($decoded['team_id'])) {
                    // try teams table
                    $team = DB::table('teams')->where('id', (int)$decoded['team_id'])->first();
                    if ($team && !empty($team->players)) {
                        $tp = is_string($team->players) ? json_decode($team->players, true) : (array)$team->players;
                        if (is_array($tp)) $players = $tp;
                    }
                }
            }
        }

        // fallback: if order has team_id directly
        if (empty($players) && !empty($order->team_id)) {
            $team = DB::table('teams')->where('id', (int)$order->team_id)->first();
            if ($team && !empty($team->players)) {
                $tp = is_string($team->players) ? json_decode($team->players, true) : (array)$team->players;
                if (is_array($tp)) $players = $tp;
            }
        }

        // ensure ZipArchive exists
        if (! class_exists('\ZipArchive')) {
            \Log::error('DOWNLOAD: ZipArchive missing');
            abort(500, 'Server missing php-zip extension');
        }

        // tmp dir
        $tmpDir = storage_path('app/temp');
        if (! file_exists($tmpDir)) mkdir($tmpDir, 0755, true);
        if (! is_writable($tmpDir)) {
            \Log::error("DOWNLOAD: tmpDir not writable: {$tmpDir}");
            abort(500, 'Server temp folder not writable');
        }

        $zipName = 'design_order_' . $id . '_' . time() . '.zip';
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE|\ZipArchive::OVERWRITE) !== true) {
            \Log::error("DOWNLOAD: cannot open zip $zipPath");
            abort(500, 'Could not create zip');
        }
        \Log::info("DOWNLOAD: zip opened $zipPath");

        // 1) info.txt
        $infoLines = [
            'Design Order ID: ' . $order->id,
            'Product ID: ' . ($order->product_id ?? '—'),
            'Customer Name: ' . ($order->name_text ?? '—'),
            'Customer Number: ' . ($order->number_text ?? '—'),
            'Stored at: ' . ($order->created_at ?? now()),
            '',
            'Preview Src: ' . ($order->preview_src ?? $order->preview_path ?? $order->preview_url ?? '—'),
        ];
        $zip->addFromString('info.txt', implode(PHP_EOL, $infoLines));
        \Log::info("DOWNLOAD: added info.txt");

        // 2) raw_payload.json pretty
        if ($raw) {
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $dec = json_decode($rawStr, true);
            $rawPretty = (json_last_error()===JSON_ERROR_NONE && is_array($dec)) ? json_encode($dec, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : $rawStr;
            $zip->addFromString('raw_payload.json', $rawPretty);
            \Log::info("DOWNLOAD: added raw_payload.json");
        }

        // 3) main preview (if present)
        $previewSrc = $order->preview_src ?? $order->preview_path ?? $order->preview_url ?? null;
        if ($previewSrc) {
            try {
                $localPreview = null;
                if (\Illuminate\Support\Str::startsWith($previewSrc, ['http://','https://'])) {
                    $resp = \Illuminate\Support\Facades\Http::timeout(10)->get($previewSrc);
                    if ($resp->ok()) {
                        $ct = $resp->header('Content-Type');
                        $ext = 'png';
                        if ($ct && preg_match('#image/(.+)#', $ct, $m)) $ext = $m[1];
                        $localPreview = $tmpDir . DIRECTORY_SEPARATOR . 'preview_main.' . $ext;
                        file_put_contents($localPreview, $resp->body());
                    }
                } else {
                    $rel = preg_replace('#^/storage/#', '', $previewSrc);
                    $rel = preg_replace('#^storage/#', '', $rel);
                    $storageFull = storage_path('app/public/' . $rel);
                    if (file_exists($storageFull)) $localPreview = $storageFull;
                    elseif (file_exists($previewSrc)) $localPreview = $previewSrc;
                }

                if ($localPreview && file_exists($localPreview)) {
                    $zip->addFile($localPreview, 'preview/' . basename($localPreview));
                    \Log::info("DOWNLOAD: added main preview {$localPreview}");
                } else {
                    \Log::warning("DOWNLOAD: main preview not found/resolved: {$previewSrc}");
                }
            } catch (\Throwable $e) {
                \Log::warning('DOWNLOAD: main preview include failed: '.$e->getMessage());
            }
        } else {
            \Log::info("DOWNLOAD: no main preview for order {$id}");
        }

        // 4) players JSON + CSV + per-player previews
        if (!empty($players) && is_array($players)) {
            // players.json
            $zip->addFromString('players.json', json_encode($players, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            \Log::info("DOWNLOAD: added players.json");

            // players.csv
            $csvLines = [];
            // header
            $csvHeader = ['id','name','number','size','font','variant_id','preview_src'];
            $csvLines[] = implode(',', $csvHeader);
            foreach ($players as $idx => $p) {
                $pArr = is_array($p) ? $p : (array)$p;
                $row = [
                    $pArr['id'] ?? ($idx+1),
                    '"'.str_replace('"','""',($pArr['name'] ?? '')).'"',
                    '"'.str_replace('"','""',($pArr['number'] ?? '')).'"',
                    '"'.str_replace('"','""',($pArr['size'] ?? '')).'"',
                    '"'.str_replace('"','""',($pArr['font'] ?? '')).'"',
                    '"'.str_replace('"','""',($pArr['variant_id'] ?? '')).'"',
                    '"'.str_replace('"','""',($pArr['preview_src'] ?? '')).'"',
                ];
                $csvLines[] = implode(',', $row);
            }
            $zip->addFromString('players.csv', implode(PHP_EOL, $csvLines));
            \Log::info("DOWNLOAD: added players.csv");

            // per-player preview images (if preview_src exists)
            $countAdded = 0;
            foreach ($players as $idx => $p) {
                $pArr = is_array($p) ? $p : (array)$p;
                $psrc = $pArr['preview_src'] ?? null;
                if (empty($psrc)) continue;

                try {
                    $local = null;
                    if (\Illuminate\Support\Str::startsWith($psrc, ['http://','https://'])) {
                        $resp = \Illuminate\Support\Facades\Http::timeout(10)->get($psrc);
                        if ($resp->ok()) {
                            $ct = $resp->header('Content-Type');
                            $ext = 'png';
                            if ($ct && preg_match('#image/(.+)#', $ct, $m)) $ext = $m[1];
                            $local = $tmpDir . DIRECTORY_SEPARATOR . 'player_' . ($idx+1) . '.' . $ext;
                            file_put_contents($local, $resp->body());
                        }
                    } else {
                        $rel = preg_replace('#^/storage/#', '', $psrc);
                        $rel = preg_replace('#^storage/#', '', $rel);
                        $storageFull = storage_path('app/public/' . $rel);
                        if (file_exists($storageFull)) $local = $storageFull;
                        elseif (file_exists($psrc)) $local = $psrc;
                    }

                    if ($local && file_exists($local)) {
                        $zip->addFile($local, 'players/previews/' . ($idx+1) . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/','_',($pArr['name'] ?? 'player')) . '.' . pathinfo($local, PATHINFO_EXTENSION));
                        $countAdded++;
                    }
                } catch (\Throwable $e) {
                    \Log::warning("DOWNLOAD: could not fetch player preview for index {$idx}: ".$e->getMessage());
                }
            }
            \Log::info("DOWNLOAD: per-player previews added: {$countAdded}");
        } else {
            \Log::info("DOWNLOAD: no players found to include for order {$id}");
        }

        // finalize
        $zip->close();
        \Log::info("DOWNLOAD: zip closed at {$zipPath}");

        // optionally log tmp listing
        $tmpList = array_slice(scandir($tmpDir), -50);
        \Log::info('DOWNLOAD: tmpDir listing: ' . implode(',', $tmpList));

        return response()->download($zipPath, 'design_order_' . $order->id . '.zip')->deleteFileAfterSend(true);

    } catch (\Throwable $e) {
        \Log::error('DOWNLOAD ERROR: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        abort(500, 'Download failed. Check logs.');
    }
}

    /**
     * Delete order (and preview file)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $row = DB::table('design_orders')->where('id', $id)->first();
            if (!$row) {
                return redirect()->back()->with('error', 'Design order not found.');
            }

            if (!empty($row->preview_src) && strpos($row->preview_src, '/storage/') !== false) {
                $path = substr($row->preview_src, strpos($row->preview_src, '/storage/') + 9);
                if ($path) {
                    try {
                        Storage::disk('public')->delete($path);
                    } catch (\Throwable $e) {
                        Log::warning('Could not delete preview file: ' . $e->getMessage());
                    }
                }
            }

            DB::table('design_orders')->where('id', $id)->delete();
            return redirect()->route('admin.design-orders.index')->with('success', 'Design order deleted.');
        } catch (\Throwable $e) {
            Log::error('Design order delete failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Could not delete design order. Check logs.');
        }
    }
}
