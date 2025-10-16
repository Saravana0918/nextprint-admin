<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Dompdf\Dompdf;

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
    try {
        $order = DB::table('design_orders as d')
        ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
        ->select('d.*', DB::raw('p.name as product_name'))
        ->where('d.id', $id)
        ->first();
        if (!$order) {
            abort(404, 'Design order not found');
        }

        // prepare temp dir
        $tmpBase = storage_path('app/temp/design_order_' . $order->id . '_' . time());
        if (!File::exists($tmpBase)) File::makeDirectory($tmpBase, 0755, true);

        // 1) Save raw_payload (if exists) as raw_payload.json
        $rawPayload = $order->raw_payload ?? $order->payload ?? null;
        $rawPath = $tmpBase . '/raw_payload.json';
        if ($rawPayload) {
            // if the column already JSON string, pretty print
            $pretty = null;
            try {
                $decoded = json_decode($rawPayload, true);
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                $pretty = $rawPayload;
            }
            file_put_contents($rawPath, $pretty);
        } else {
            file_put_contents($rawPath, json_encode(['note'=>'no raw payload present'], JSON_PRETTY_PRINT));
        }

        // 2) Create players.csv (try to get players list)
        $players = [];
        // Prefer raw_payload players, then payload, then team record
        if (!empty($order->raw_payload)) {
            $rp = json_decode($order->raw_payload, true);
            if (is_array($rp) && !empty($rp['players'])) $players = $rp['players'];
        }
        if (empty($players) && !empty($order->payload)) {
            $p = json_decode($order->payload, true);
            if (is_array($p) && !empty($p['players'])) $players = $p['players'];
        }
        // fallback: if there's team_id and teams.players json
        if (empty($players) && !empty($order->team_id)) {
            $t = DB::table('teams')->where('id', $order->team_id)->first();
            if ($t && !empty($t->players)) {
                $dec = json_decode($t->players, true);
                if (is_array($dec)) $players = $dec;
            }
        }

        // write CSV
        $csvPath = $tmpBase . '/players.csv';
        $fh = fopen($csvPath, 'w');
        // header
        fputcsv($fh, ['id','name','number','size','font','variant_id','preview_src']);
        $i = 1;
        foreach ($players as $pl) {
            $plArr = is_array($pl) ? $pl : (array)$pl;
            fputcsv($fh, [
                $plArr['id'] ?? $i,
                $plArr['name'] ?? '',
                $plArr['number'] ?? '',
                $plArr['size'] ?? '',
                $plArr['font'] ?? '',
                $plArr['variant_id'] ?? '',
                $plArr['preview_src'] ?? ''
            ]);
            $i++;
        }
        fclose($fh);

        // 3) Save preview image(s) into preview/ folder
        $previewDir = $tmpBase . '/preview';
        if (!File::exists($previewDir)) File::makeDirectory($previewDir, 0755, true);

        // order->preview_src or preview_url or preview_path
       $previewCandidates = [];
        if (!empty($order->preview_src)) $previewCandidates[] = $order->preview_src;
        if (!empty($order->preview_url)) $previewCandidates[] = $order->preview_url;
        if (!empty($order->preview_path)) $previewCandidates[] = $order->preview_path;

        // also collect any player preview_srcs
        foreach ($players as $pl) {
            $plArr = is_array($pl) ? $pl : (array)$pl;
            if (!empty($plArr['preview_src'])) $previewCandidates[] = $plArr['preview_src'];
        }

        $downloadedPreviews = [];
        $idx = 1;
        foreach ($previewCandidates as $pc) {
            if (empty($pc)) continue;
            $pcTrim = trim($pc);
            $localPath = null;
            // if starts with /storage or storage/ => map to storage/app/public/...
            if (Str::startsWith($pcTrim, '/storage') || Str::startsWith($pcTrim, 'storage/')) {
                $relative = preg_replace('#^/storage#', '', $pcTrim);
                $relative = ltrim($relative, '/');
                $candidate = storage_path('app/public/' . $relative);
                if (File::exists($candidate)) $localPath = $candidate;
            } elseif (filter_var($pcTrim, FILTER_VALIDATE_URL)) {
                // remote url -> download
                try {
                    $contents = @file_get_contents($pcTrim);
                    if ($contents !== false) {
                        $ext = pathinfo(parse_url($pcTrim, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
                        $fname = 'preview_' . $idx . '.' . $ext;
                        file_put_contents($previewDir . '/' . $fname, $contents);
                        $downloadedPreviews[] = $previewDir . '/' . $fname;
                        $idx++;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // skip
                }
            }
            if ($localPath && File::exists($localPath)) {
                $ext = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'png';
                $fname = 'preview_' . $idx . '.' . $ext;
                copy($localPath, $previewDir . '/' . $fname);
                $downloadedPreviews[] = $previewDir . '/' . $fname;
                $idx++;
            }
        }

        // if no preview images downloaded, create one from dataURL if preview_src is data:
        if (empty($downloadedPreviews) && !empty($order->preview_src) && Str::startsWith($order->preview_src, 'data:')) {
            preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $order->preview_src, $m);
            if (!empty($m[2])) {
                $bin = base64_decode($m[2]);
                $fname = 'preview_1.png';
                file_put_contents($previewDir . '/' . $fname, $bin);
                $downloadedPreviews[] = $previewDir . '/' . $fname;
            }
        }

        // 4) Create a simple info.txt
        $info = "Order ID: " . ($order->id ?? '') . PHP_EOL
        . "Product ID: " . ($order->product_id ?? '') . PHP_EOL
        . "Product name: " . ($order->product_name ?? ($order->product_name ?? '')) . PHP_EOL
        . "Customer name / number: " . ($order->name_text ?? '') . " / " . ($order->number_text ?? '') . PHP_EOL
        . "Status: " . ($order->status ?? '') . PHP_EOL
        . "Created: " . ($order->created_at ?? '') . PHP_EOL;

        // 5) Create a PDF with name/number + preview image (use Dompdf)
        $pdfPath = $tmpBase . '/design_order_' . $order->id . '.pdf';

        // Build a small HTML document
        $previewForPdf = $downloadedPreviews[0] ?? null;
        $imageTag = '';
        if ($previewForPdf && File::exists($previewForPdf)) {
            // embed as data URI (helps with Dompdf local file issues)
            $bin = file_get_contents($previewForPdf);
            $base64 = base64_encode($bin);
            $mime = mime_content_type($previewForPdf) ?: 'image/png';
            $imageTag = "<img src=\"data:{$mime};base64,{$base64}\" style=\"max-width:100%;height:auto;display:block;margin:0 auto;border:1px solid #ddd;padding:8px;border-radius:6px;\" />";
        }

        $html = '<html><body style="font-family:Arial, sans-serif;">';
        $html .= "<h2>Design Order #{$order->id}</h2>";
        $html .= '<p><strong>Customer:</strong> ' . htmlspecialchars($order->name_text ?? '') . ' </p>';
        $html .= '<p><strong>Number:</strong> ' . htmlspecialchars($order->number_text ?? '') . ' </p>';
        $html .= $imageTag;
        $html .= '<hr/>';
        $html .= '<h4>Players</h4>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';
        $html .= '<thead><tr><th style="border:1px solid #ddd;padding:6px">#</th><th style="border:1px solid #ddd;padding:6px">Name</th><th style="border:1px solid #ddd;padding:6px">Number</th><th style="border:1px solid #ddd;padding:6px">Size</th></tr></thead><tbody>';
        $ci = 1;
        foreach ($players as $pl) {
            $plArr = is_array($pl) ? $pl : (array)$pl;
            $html .= '<tr>';
            $html .= '<td style="border:1px solid #ddd;padding:6px">' . $ci . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px">' . htmlspecialchars($plArr['name'] ?? '') . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px">' . htmlspecialchars($plArr['number'] ?? '') . '</td>';
            $html .= '<td style="border:1px solid #ddd;padding:6px">' . htmlspecialchars($plArr['size'] ?? '') . '</td>';
            $html .= '</tr>';
            $ci++;
        }
        $html .= '</tbody></table>';
        $html .= '</body></html>';

        // generate pdf
        if (!class_exists('\Dompdf\Dompdf')) {
            // if dompdf not available, write HTML fallback file
            file_put_contents($pdfPath . '.html', $html);
        } else {
            $dompdf = new Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($pdfPath, $dompdf->output());
        }

        // 6) Create ZIP
        $zipName = 'design_order_' . $order->id . '.zip';
        $zipPath = $tmpBase . '/' . $zipName;

        if (!class_exists('ZipArchive')) {
            throw new \Exception('ZipArchive not available on server. Install php-zip extension.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create zip file');
        }

        // add info + raw + csv + pdf + previews
        $zip->addFile($tmpBase . '/info.txt', 'info.txt');
        $zip->addFile($rawPath, 'raw_payload.json');
        $zip->addFile($csvPath, 'players.csv');
        if (File::exists($pdfPath)) {
            $zip->addFile($pdfPath, basename($pdfPath));
        } elseif (File::exists($pdfPath . '.html')) {
            $zip->addFile($pdfPath . '.html', basename($pdfPath) . '.html');
        }

        // add all preview images
        if (File::exists($previewDir)) {
            $files = File::files($previewDir);
            foreach ($files as $f) {
                $zip->addFile($f->getPathname(), 'preview/' . $f->getFilename());
            }
        }

        $zip->close();

        // Return download response and cleanup after sending
        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);

    } catch (\Throwable $e) {
        Log::error('DesignOrderController::download failed: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString(), 'id'=>$id]);
        return back()->with('error', 'Could not prepare download package. Check logs.');
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
