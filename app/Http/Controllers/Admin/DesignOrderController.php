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
        \Log::info("DOWNLOAD: order row found, preview_src=" . ($order->preview_src ?? $order->preview_path ?? $order->preview_url ?? 'NULL'));

        // ensure zip available
        if (! class_exists('\ZipArchive')) {
            \Log::error('DOWNLOAD: ZipArchive missing');
            abort(500, 'Server missing php-zip extension');
        }

        $tmpDir = storage_path('app/temp');
        if (! file_exists($tmpDir)) {
            mkdir($tmpDir, 0755, true);
            \Log::info("DOWNLOAD: created tmpDir {$tmpDir}");
        }
        \Log::info("DOWNLOAD: tmpDir exists, writable? " . (is_writable($tmpDir) ? 'yes' : 'no'));

        $zipName = 'design_order_' . $id . '_' . time() . '.zip';
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE|\ZipArchive::OVERWRITE) !== true) {
            \Log::error("DOWNLOAD: cannot open zip $zipPath");
            abort(500, 'Could not create zip');
        }
        \Log::info("DOWNLOAD: zip opened $zipPath");

        // info.txt
        $info = "Order ID: {$order->id}\nName: " . ($order->name_text ?? '—') . "\nNumber: " . ($order->number_text ?? '—') . "\n";
        $zip->addFromString('info.txt', $info);
        \Log::info("DOWNLOAD: added info.txt");

        // raw payload
        $raw = $order->raw_payload ?? $order->payload ?? null;
        if ($raw) {
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $dec = json_decode($rawStr, true);
            $pretty = (json_last_error()===JSON_ERROR_NONE && is_array($dec)) ? json_encode($dec, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) : $rawStr;
            $zip->addFromString('raw_payload.json', $pretty);
            \Log::info("DOWNLOAD: added raw_payload.json");
        }

        // include preview if present
        $previewSrc = $order->preview_src ?? $order->preview_path ?? $order->preview_url ?? null;
        \Log::info("DOWNLOAD: previewSrc={$previewSrc}");
        if ($previewSrc) {
            $localTemp = null;
            if (\Illuminate\Support\Str::startsWith($previewSrc, ['http://','https://'])) {
                \Log::info("DOWNLOAD: fetching remote preview via HTTP");
                $resp = \Illuminate\Support\Facades\Http::timeout(10)->get($previewSrc);
                if ($resp->ok()) {
                    $ext = 'png';
                    $ct = $resp->header('Content-Type');
                    if ($ct && preg_match('#image/(.+)#', $ct, $m)) $ext = $m[1];
                    $localTemp = $tmpDir . DIRECTORY_SEPARATOR . 'preview.' . $ext;
                    file_put_contents($localTemp, $resp->body());
                    \Log::info("DOWNLOAD: fetched remote preview to {$localTemp}");
                } else {
                    \Log::warning("DOWNLOAD: remote preview fetch failed, status=" . $resp->status());
                }
            } else {
                $rel = preg_replace('#^/storage/#', '', $previewSrc);
                $rel = preg_replace('#^storage/#', '', $rel);
                $storageFull = storage_path('app/public/' . $rel);
                if (file_exists($storageFull)) {
                    $localTemp = $storageFull;
                    \Log::info("DOWNLOAD: found preview in storage {$storageFull}");
                } elseif (file_exists($previewSrc)) {
                    $localTemp = $previewSrc;
                    \Log::info("DOWNLOAD: preview path exists {$previewSrc}");
                } else {
                    \Log::warning("DOWNLOAD: preview file not found at {$storageFull} or {$previewSrc}");
                }
            }

            if ($localTemp && file_exists($localTemp)) {
                $zip->addFile($localTemp, 'preview/' . basename($localTemp));
                \Log::info("DOWNLOAD: added preview file to zip");
            }
        }

        $zip->close();
        \Log::info("DOWNLOAD: zip closed at {$zipPath}");

        // show files in tmp dir for debug
        $tmpList = array_slice(scandir($tmpDir), -20);
        \Log::info('DOWNLOAD: tmpDir listing: ' . implode(',', $tmpList));

        // send and delete
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
