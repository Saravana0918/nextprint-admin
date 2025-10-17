<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
 

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
    $order = DB::table('design_orders')->where('id', $id)->first();
    if (! $order) abort(404);

    // players: prefer team -> meta -> payload
    $players = [];
    if (!empty($order->team_id)) {
        $team = DB::table('teams')->where('id', $order->team_id)->first();
        if ($team && !empty($team->players)) $players = json_decode($team->players, true) ?: [];
    }
    if (empty($players)) {
        // try raw_payload or payload fields
        $raw = $order->raw_payload ?? $order->payload ?? null;
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['players'])) $players = $decoded['players'];
        }
    }

    // create a temp directory inside storage/app/public/tmp
    $tmpDirRel = 'design_order_' . $order->id . '_' . time();
    $tmpDirFull = storage_path('app/public/' . $tmpDirRel);
    if (!is_dir($tmpDirFull)) mkdir($tmpDirFull, 0775, true);

    // 1) Save CSV of players
    $csvPathRel = $tmpDirRel . '/players.csv';
    $csvFull = $tmpDirFull . '/players.csv';
    $fh = fopen($csvFull, 'w');
    fputcsv($fh, ['id','name','number','size','font','variant_id','preview_src']);
    foreach ($players as $idx => $p) {
        $row = [
            $p['id'] ?? ($idx+1),
            $p['name'] ?? '',
            $p['number'] ?? '',
            $p['size'] ?? '',
            $p['font'] ?? '',
            $p['variant_id'] ?? '',
            $p['preview_src'] ?? '',
        ];
        fputcsv($fh, $row);
    }
    fclose($fh);

    // 2) Copy preview image(s) into preview folder (if preview_src points to storage)
    $previewDirFull = $tmpDirFull . '/preview';
    mkdir($previewDirFull, 0755, true);
    // if order->preview_src holds /storage/team_previews/...
    $previewFiles = [];
    if (!empty($order->preview_src)) {
        // normalize: could be asset(...) or full url
        $preview = $order->preview_src;
        // handle storage path that starts with '/storage/'
        if (strpos($preview, '/storage/') === 0) {
            $rel = substr($preview, strlen('/storage/')); // team_previews/...
            $src = storage_path('app/public/'.$rel);
            if (is_file($src)) {
                $dest = $previewDirFull . '/' . basename($src);
                copy($src, $dest);
                $previewFiles[] = $dest;
            }
        } else {
            // try stripping domain
            $parsed = parse_url($preview);
            if (!empty($parsed['path']) && strpos($parsed['path'],'/storage/') !== false) {
                $rel = substr($parsed['path'], strpos($parsed['path'],'/storage/') + 9);
                $src = storage_path('app/public/'.$rel);
                if (is_file($src)) {
                    $dest = $previewDirFull . '/' . basename($src);
                    copy($src, $dest);
                    $previewFiles[] = $dest;
                }
            }
        }
    }

    // 3) Generate PDF (use a blade view)
    $pdfViewData = [
        'order' => $order,
        'players' => $players,
    ];

    $pdfFileName = 'design_order_' . $order->id . '.pdf';
    $pdfFullPath = $tmpDirFull . '/' . $pdfFileName;

    // Use absolute file path for images in the view (dompdf needs file:// or absolute)
    $pdf = PDF::loadView('admin.design_orders.pdf', $pdfViewData)
              ->setPaper('A4', 'portrait');
    $pdf->save($pdfFullPath);

    // 4) write raw_payload / info text
    file_put_contents($tmpDirFull . '/raw_payload.json', json_encode(['order'=> $order, 'players' => $players], JSON_PRETTY_PRINT));
    file_put_contents($tmpDirFull . '/info.txt', "Design order {$order->id}\nCustomer: " . ($order->name_text ?? '') . " / " . ($order->number_text ?? '') . "\n");

    // 5) Zip everything
    $zipName = 'design_order_' . $order->id . '.zip';
    $zipFull = storage_path('app/public/' . $tmpDirRel . '.zip');
    $zip = new ZipArchive;
    if ($zip->open($zipFull, ZipArchive::CREATE) === true) {
        // add pdf
        $zip->addFile($pdfFullPath, $pdfFileName);
        // add csv
        $zip->addFile($csvFull, 'players.csv');
        // add raw payload
        $zip->addFile($tmpDirFull . '/raw_payload.json', 'raw_payload.json');
        $zip->addFile($tmpDirFull . '/info.txt', 'info.txt');
        // add preview files if any
        foreach (glob($previewDirFull . '/*') as $pf) {
            $zip->addFile($pf, 'preview/' . basename($pf));
        }
        $zip->close();
    } else {
        // zip failed
        abort(500, 'Could not create zip file');
    }

    // 6) send zip for download and optionally delete after send
    return response()->download($zipFull)->deleteFileAfterSend(true);
}

// helper in same controller (private) for cleanup
private function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
    $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) {
        if ($file->isDir()) rmdir($file->getRealPath());
        else unlink($file->getRealPath());
    }
    @rmdir($dir);
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
