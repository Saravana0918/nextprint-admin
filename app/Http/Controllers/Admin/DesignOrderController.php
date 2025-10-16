<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
 

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
    // check Zip extension
    if (!class_exists('ZipArchive')) {
        abort(500, "Zip extension (php-zip) not installed on server. Install php-zip and restart PHP-FPM.");
    }

    // fetch order + product name
    $order = DB::table('design_orders as d')
        ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
        ->select('d.*', DB::raw('p.name as product_name'))
        ->where('d.id', $id)
        ->first();

    if (! $order) {
        abort(404, 'Design order not found');
    }

    // prepare temp directory (inside storage/app/temp)
    $baseTmp = storage_path('app/temp');
    if (!is_dir($baseTmp)) {
        mkdir($baseTmp, 0775, true);
    }

    $uniq = 'design_order_' . $order->id . '_' . time();
    $tmpDir = $baseTmp . '/' . $uniq;
    mkdir($tmpDir, 0775, true);

    $previewDir = $tmpDir . '/preview';
    mkdir($previewDir, 0775, true);

    try {
        // --- info.txt ---
        $infoLines = [];
        $infoLines[] = "Order ID: " . ($order->id ?? '');
        $infoLines[] = "Product ID: " . ($order->product_id ?? '');
        $infoLines[] = "Product name: " . ($order->product_name ?? '');
        $infoLines[] = "Customer name / number: " . ($order->name_text ?? '') . ' / ' . ($order->number_text ?? '');
        $infoLines[] = "Status: " . ($order->status ?? '');
        $infoLines[] = "Created at: " . ($order->created_at ?? '');
        $infoText = implode(PHP_EOL, $infoLines);
        file_put_contents($tmpDir . '/info.txt', $infoText);

        // --- raw_payload.json (if any) ---
        if (!empty($order->raw_payload)) {
            $raw = $order->raw_payload;
            // ensure pretty json
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if ($decoded !== null) {
                    file_put_contents($tmpDir . '/raw_payload.json', json_encode($decoded, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
                } else {
                    // save as-is
                    file_put_contents($tmpDir . '/raw_payload.txt', $raw);
                }
            } else {
                file_put_contents($tmpDir . '/raw_payload.json', json_encode($raw, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            }
        }

        // --- players CSV: try get players via team -> teams table or from payload/payload field ---
        $playersArray = [];

        // priority: team_id on design_orders -> teams table
        if (!empty($order->team_id)) {
            $team = DB::table('teams')->where('id', (int)$order->team_id)->first();
            if ($team && !empty($team->players)) {
                $decoded = is_string($team->players) ? json_decode($team->players, true) : (array)$team->players;
                if (is_array($decoded)) $playersArray = $decoded;
            }
        }

        // fallback: design_orders.payload or raw_payload players
        if (empty($playersArray)) {
            if (!empty($order->payload)) {
                $decoded = is_string($order->payload) ? json_decode($order->payload, true) : (array)$order->payload;
                if (!empty($decoded['players']) && is_array($decoded['players'])) $playersArray = $decoded['players'];
            }
        }
        if (empty($playersArray) && !empty($order->raw_payload)) {
            $decoded = is_string($order->raw_payload) ? json_decode($order->raw_payload, true) : (array)$order->raw_payload;
            if (!empty($decoded['players']) && is_array($decoded['players'])) $playersArray = $decoded['players'];
        }

        // if still empty -> maybe team_players rows (legacy)
        if (empty($playersArray)) {
            $tp = DB::table('team_players')->where('team_id', $order->team_id ?? 0)->orderBy('id')->get();
            if ($tp->count()) {
                $playersArray = $tp->map(function($r){ return (array)$r; })->toArray();
            }
        }

        // write players.csv
        $csvPath = $tmpDir . '/players.csv';
        $fh = fopen($csvPath, 'w');
        if ($fh) {
            // headers
            fputcsv($fh, ['id','name','number','size','font','variant_id','preview_src']);
            foreach ($playersArray as $i => $praw) {
                $p = is_array($praw) ? $praw : (array)$praw;
                fputcsv($fh, [
                    $p['id'] ?? ($i+1),
                    $p['name'] ?? '',
                    $p['number'] ?? '',
                    $p['size'] ?? '',
                    $p['font'] ?? '',
                    $p['variant_id'] ?? '',
                    $p['preview_src'] ?? '',
                ]);
            }
            fclose($fh);
        }

        // --- handle preview(s): collect candidate URLs/paths from order ---
        $previewCandidates = [];
        foreach (['preview_src','preview_url','preview_path','uploaded_logo_url'] as $key) {
            if (!empty($order->{$key})) $previewCandidates[] = $order->{$key};
        }

        // also if team preview exists
        if (!empty($order->team_id)) {
            $team = DB::table('teams')->where('id', $order->team_id)->first();
            if ($team) {
                if (!empty($team->preview_url)) $previewCandidates[] = $team->preview_url;
                if (!empty($team->preview_path)) $previewCandidates[] = $team->preview_path;
            }
        }

        // normalize & fetch remote ones to previewDir
        $addedPreviewFiles = [];
        foreach ($previewCandidates as $cand) {
            if (!$cand) continue;
            $cand = (string)$cand;

            // candidate might be a storage path like "/storage/team_previews/xxx.png" or "storage/team_previews/xxx.png"
            $localPath = null;
            $filename = basename(parse_url($cand, PHP_URL_PATH) ?? 'preview.png');

            if (Str::startsWith($cand, '/storage')) {
                // remove leading /storage/ and map to storage/app/public/...
                $rel = ltrim(substr($cand, strlen('/storage')), '/');
                $possible = storage_path('app/public/' . $rel);
                if (is_file($possible)) $localPath = $possible;
            } elseif (Str::startsWith($cand, 'storage/')) {
                $rel = ltrim($cand, '/');
                $possible = storage_path('app/public/' . $rel);
                if (is_file($possible)) $localPath = $possible;
            } elseif (Str::startsWith($cand, 'http://') || Str::startsWith($cand, 'https://')) {
                // download remote image into previewDir
                try {
                    $contents = @file_get_contents($cand);
                    if ($contents !== false) {
                        $saveTo = $previewDir . '/' . $filename;
                        file_put_contents($saveTo, $contents);
                        $localPath = $saveTo;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            } else {
                // maybe it's already a local absolute path in storage/app/...
                if (is_file($cand)) {
                    $localPath = $cand;
                } else {
                    // maybe relative to storage/app/public
                    $possible = storage_path('app/public/' . ltrim($cand, '/'));
                    if (is_file($possible)) $localPath = $possible;
                    else {
                        // maybe relative to public path
                        $possible2 = public_path(ltrim($cand, '/'));
                        if (is_file($possible2)) $localPath = $possible2;
                    }
                }
            }

            if ($localPath && is_file($localPath)) {
                // copy into previewDir with unique name
                $dest = $previewDir . '/' . uniqid('preview_') . '_' . $filename;
                copy($localPath, $dest);
                $addedPreviewFiles[] = $dest;
            }
        }

        // Also add per-player preview_srcs if any
        foreach ($playersArray as $i => $p) {
            $p = (array)$p;
            if (!empty($p['preview_src'])) {
                $cand = $p['preview_src'];
                $filename = basename(parse_url($cand, PHP_URL_PATH) ?? 'player_'.$i.'.png');
                $localPath = null;
                if (Str::startsWith($cand, '/storage')) {
                    $rel = ltrim(substr($cand, strlen('/storage')), '/');
                    $possible = storage_path('app/public/' . $rel);
                    if (is_file($possible)) $localPath = $possible;
                } elseif (Str::startsWith($cand, 'storage/')) {
                    $rel = ltrim($cand, '/');
                    $possible = storage_path('app/public/' . $rel);
                    if (is_file($possible)) $localPath = $possible;
                } elseif (Str::startsWith($cand, 'http://') || Str::startsWith($cand, 'https://')) {
                    $contents = @file_get_contents($cand);
                    if ($contents !== false) {
                        $saveTo = $previewDir . '/' . $filename;
                        file_put_contents($saveTo, $contents);
                        $localPath = $saveTo;
                    }
                } else {
                    if (is_file($cand)) $localPath = $cand;
                    else {
                        $possible = storage_path('app/public/' . ltrim($cand, '/'));
                        if (is_file($possible)) $localPath = $possible;
                    }
                }

                if ($localPath && is_file($localPath)) {
                    $dest = $previewDir . '/' . uniqid('player_') . '_' . $filename;
                    copy($localPath, $dest);
                    $addedPreviewFiles[] = $dest;
                }
            }
        }

        // --- Create ZIP ---
        $zipFile = $baseTmp . '/' . $uniq . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Could not create zip at: $zipFile");
        }

        // add info and raw & players
        if (is_file($tmpDir . '/info.txt')) $zip->addFile($tmpDir . '/info.txt', 'info.txt');
        if (is_file($csvPath)) $zip->addFile($csvPath, 'players.csv');

        if (is_file($tmpDir . '/raw_payload.json')) $zip->addFile($tmpDir . '/raw_payload.json', 'raw_payload.json');
        if (is_file($tmpDir . '/raw_payload.txt')) $zip->addFile($tmpDir . '/raw_payload.txt', 'raw_payload.txt');

        // add preview files into preview/ folder inside zip
        foreach ($addedPreviewFiles as $pfile) {
            if (is_file($pfile)) {
                $zip->addFile($pfile, 'preview/' . basename($pfile));
            }
        }

        // finalize
        $zip->close();

        // stream download and delete file after send
        return response()->download($zipFile, 'design_order_' . $order->id . '.zip', [
            'Content-Type' => 'application/zip'
        ])->deleteFileAfterSend(true);

    } catch (\Throwable $e) {
        // cleanup temp dir if created
        // (do best-effort)
        try { 
            if (is_dir($tmpDir)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) {
                    if ($fileinfo->isDir()) rmdir($fileinfo->getRealPath()); else unlink($fileinfo->getRealPath());
                }
                @rmdir($tmpDir);
            }
        } catch (\Throwable $_) {}
        \Log::error('DesignOrderController::download failed: ' . $e->getMessage(), ['exception' => $e]);
        abort(500, 'Could not create download package. Check logs.');
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
