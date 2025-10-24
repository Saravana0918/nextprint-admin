<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ZipArchive;

class DesignOrderController extends Controller
{
    /**
     * List design orders (simple index)
     */
    public function index()
    {
        $rows = DB::table('design_orders as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->select([
                'd.id',
                'd.shopify_order_id',
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
     * Show a single design order (detail)
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
            // Try team players from teams table
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

                // fallback to team_players table if teams.players not present
                if ($players->isEmpty()) {
                    $players = DB::table('team_players')
                        ->where('team_id', $order->team_id)
                        ->orderBy('id')
                        ->get();
                }
            }

            // fallback to payload/meta if still empty
            if ($players->isEmpty() && !empty($order->meta)) {
                $metaDecoded = json_decode($order->meta, true);
                if (is_string($metaDecoded)) $metaDecoded = json_decode($metaDecoded, true);
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
                }
            }

            // fallback to team_players by product
            if ($players->isEmpty() && !empty($order->product_id)) {
                $players = DB::table('team_players')
                    ->where('product_id', $order->product_id)
                    ->orderBy('id')
                    ->get();
            }

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

    /**
     * Download package (PDF + CSV + preview image) as a ZIP.
     * This method is robust about preview image sources:
     *  - preview_base (data uri or raw base64) -> embed
     *  - preview_src (data uri or path) -> use / resolve
     *  - preview_path (relative storage path) -> resolve to storage_path('app/public/...') and embed
     */
    public function download($id)
    {
        $order = DB::table('design_orders')->where('id', $id)->first();
        if (!$order) abort(404, 'Order not found');

        // --- players extraction (try multiple sources) ---
        $players = [];
        if (!empty($order->payload)) {
            $decoded = json_decode($order->payload, true) ?: [];
            if (!empty($decoded['players']) && is_array($decoded['players'])) {
                $players = $decoded['players'];
            }
        }
        if (empty($players) && !empty($order->raw_payload)) {
            $r = json_decode($order->raw_payload, true) ?: [];
            if (!empty($r['players'])) $players = $r['players'];
        }
        if (empty($players) && !empty($order->team_id)) {
            $team = DB::table('teams')->where('id', $order->team_id)->first();
            if ($team && !empty($team->players)) {
                $players = json_decode($team->players, true) ?: [];
            }
        }

        // --- determine preview image source ---
        $preview_data_uri = null;
        $preview_local_path = null;
        $preview_url = null;

        // 1) preview_base (raw base64 or data uri)
        if (!empty($order->preview_base)) {
            if (Str::startsWith($order->preview_base, 'data:')) {
                $preview_data_uri = $order->preview_base;
            } else {
                $bin = base64_decode($order->preview_base);
                if ($bin !== false) {
                    $preview_data_uri = 'data:image/png;base64,' . base64_encode($bin);
                }
            }
        }

        // 2) preview_src
        if (!$preview_data_uri && !empty($order->preview_src)) {
            if (Str::startsWith($order->preview_src, 'data:')) {
                $preview_data_uri = $order->preview_src;
            } else {
                $preview_url = $order->preview_src;
            }
        }

        // 3) preview_path fallback
        if (!$preview_data_uri && !$preview_url && !empty($order->preview_path)) {
            $preview_url = $order->preview_path;
        }

        // Try to resolve preview_url to a local file (storage/app/public/...)
        if ($preview_url) {
            $pathOnly = parse_url($preview_url, PHP_URL_PATH) ?: $preview_url;
            $pathOnly = ltrim($pathOnly, '/');

            if (strpos($pathOnly, 'storage/') === 0) {
                $rel = preg_replace('#^storage/#', '', $pathOnly);
                $possible = storage_path('app/public/' . $rel);
                if (file_exists($possible) && is_readable($possible)) {
                    $preview_local_path = $possible;
                } else {
                    Log::warning("DesignOrder #{$id}: expected local preview at {$possible} not found.");
                }
            } elseif (strpos($pathOnly, 'team_previews/') === 0) {
                $possible = storage_path('app/public/' . $pathOnly);
                if (file_exists($possible) && is_readable($possible)) {
                    $preview_local_path = $possible;
                } else {
                    Log::warning("DesignOrder #{$id}: expected local preview at {$possible} not found.");
                }
            }
        }

        // If local file found, read/prepare data-uri or file:// fallback
        if ($preview_local_path && file_exists($preview_local_path)) {
            try {
                $contents = file_get_contents($preview_local_path);
                if ($contents !== false) {
                    // embed if not too large, else fallback to file://
                    if (strlen($contents) < (4 * 1024 * 1024)) { // 4MB threshold
                        $mime = @mime_content_type($preview_local_path) ?: 'image/png';
                        $preview_data_uri = 'data:' . $mime . ';base64,' . base64_encode($contents);
                    } else {
                        $preview_url = 'file://' . $preview_local_path;
                        Log::info("DesignOrder #{$id}: preview file large; using file:// fallback.");
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("DesignOrder #{$id}: failed reading preview file: " . $e->getMessage());
            }
        }

        // Layout and dimension calculations for PDF (safe defaults)
        $maxImageWidthMm = 150.0;
        $displayWidthMm = $maxImageWidthMm;
        $displayHeightMm = 110.0;

        if ($preview_local_path && file_exists($preview_local_path)) {
            try {
                [$imgWpx, $imgHpx] = getimagesize($preview_local_path);
                $pxToMm = 25.4 / 96.0;
                $imageWidthMm = $imgWpx * $pxToMm;
                $imageHeightMm = $imgHpx * $pxToMm;
                $scale = ($imageWidthMm > 0) ? ($maxImageWidthMm / $imageWidthMm) : 1.0;
                $displayWidthMm = max(10, $imageWidthMm * $scale);
                $displayHeightMm = max(10, $imageHeightMm * $scale);
            } catch (\Throwable $e) {
                $displayWidthMm = $maxImageWidthMm;
                $displayHeightMm = 110.0;
            }
        }

        // default slot percentages (can be overridden by payload/meta layoutSlots)
        $defaults = [
            'name_left_pct' => 72, 'name_top_pct' => 25, 'name_width_pct' => 22, 'name_font_pt' => 22,
            'number_left_pct' => 72, 'number_top_pct' => 48, 'number_width_pct' => 14, 'number_font_pt' => 40,
        ];

        $slots = [];
        if (!empty($order->payload)) {
            $pl = json_decode($order->payload, true) ?: [];
            if (!empty($pl['layoutSlots']) && is_array($pl['layoutSlots'])) $slots = $pl['layoutSlots'];
        }
        if (empty($slots) && !empty($order->meta)) {
            $m = json_decode($order->meta, true) ?: [];
            if (!empty($m['layoutSlots']) && is_array($m['layoutSlots'])) $slots = $m['layoutSlots'];
        }

        if (!empty($slots) && is_array($slots)) {
            $sname = $slots['name'] ?? $slots['Name'] ?? null;
            $snum = $slots['number'] ?? $slots['Number'] ?? null;
            if (is_array($sname)) {
                $defaults['name_left_pct'] = $sname['left_pct'] ?? $defaults['name_left_pct'];
                $defaults['name_top_pct']  = $sname['top_pct'] ?? $defaults['name_top_pct'];
                $defaults['name_width_pct']= $sname['width_pct'] ?? $defaults['name_width_pct'];
                if (!empty($sname['font_size_pt'])) $defaults['name_font_pt'] = (int)$sname['font_size_pt'];
            }
            if (is_array($snum)) {
                $defaults['number_left_pct'] = $snum['left_pct'] ?? $defaults['number_left_pct'];
                $defaults['number_top_pct']  = $snum['top_pct'] ?? $defaults['number_top_pct'];
                $defaults['number_width_pct']= $snum['width_pct'] ?? $defaults['number_width_pct'];
                if (!empty($snum['font_size_pt'])) $defaults['number_font_pt'] = (int)$snum['font_size_pt'];
            }
        }

        $name_left_mm   = ($defaults['name_left_pct'] / 100.0) * $displayWidthMm;
        $name_top_mm    = ($defaults['name_top_pct']  / 100.0) * $displayHeightMm;
        $number_left_mm = ($defaults['number_left_pct'] / 100.0) * $displayWidthMm;
        $number_top_mm  = ($defaults['number_top_pct']  / 100.0) * $displayHeightMm;

        $name_font_size_pt   = (int) ($defaults['name_font_pt'] ?? 22);
        $number_font_size_pt = (int) ($defaults['number_font_pt'] ?? 40);

        // font & color normalization
        $fontCandidates = [
            'bebas' => 'Bebas Neue',
            'oswald' => 'Oswald',
            'anton' => 'Anton',
            'impact' => 'Impact',
        ];
        $fontNameRaw = trim((string)($order->font ?? ''));
        $fontKey = strtolower(preg_replace('/[^a-z0-9]+/',' ', $fontNameRaw));
        $fontName = $fontCandidates[$fontKey] ?? ($order->font ?? 'DejaVu Sans');

        $colorRaw = trim((string)($order->color ?? ''));
        $color = $colorRaw !== '' ? (($colorRaw[0] === '#') ? $colorRaw : '#' . ltrim($colorRaw, '#')) : '#000000';

        // build pdf view data
        $pdfData = [
            'product_name' => $order->product_name ?? null,
            'customer_name' => $order->name_text ?? null,
            'customer_number' => $order->number_text ?? null,
            'order_id' => $order->id,
            'players' => $players,
            'preview_data_uri' => $preview_data_uri,
            'preview_url' => $preview_url,
            'preview_local_path' => $preview_local_path,
            'displayWidthMm' => round($displayWidthMm,2),
            'displayHeightMm' => round($displayHeightMm,2),
            'name_left_mm' => round($name_left_mm,2),
            'name_top_mm' => round($name_top_mm,2),
            'number_left_mm' => round($number_left_mm,2),
            'number_top_mm' => round($number_top_mm,2),
            'name_font_size_pt' => $name_font_size_pt,
            'number_font_size_pt' => $number_font_size_pt,
            'font' => $fontName,
            'color' => $color,
        ];

        // --- create temp dir & generate PDF + package ---
        $tmpDir = storage_path('app/tmp/design_package_' . $order->id . '_' . time());
        @mkdir($tmpDir, 0755, true);

        try {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ])->loadView('admin.design_orders.package_for_corel', $pdfData);

            $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'design_order_' . $order->id . '.pdf';
            $pdf->save($pdfPath);

            // players CSV
            $csvPath = $tmpDir . DIRECTORY_SEPARATOR . 'players.csv';
            $fp = fopen($csvPath, 'w');
            fputcsv($fp, ['id','name','number','size','font','variant_id','preview_src']);
            foreach ($players as $i => $p) {
                $row = [
                    $p['id'] ?? ($i+1),
                    $p['name'] ?? '',
                    $p['number'] ?? '',
                    $p['size'] ?? '',
                    $p['font'] ?? '',
                    $p['variant_id'] ?? '',
                    $p['preview_src'] ?? '',
                ];
                fputcsv($fp, $row);
            }
            fclose($fp);

            // copy preview file into package if local file exists, otherwise try to write from data-uri
            if (!empty($preview_local_path) && file_exists($preview_local_path)) {
                $previewDir = $tmpDir . DIRECTORY_SEPARATOR . 'preview';
                @mkdir($previewDir, 0755, true);
                copy($preview_local_path, $previewDir . DIRECTORY_SEPARATOR . basename($preview_local_path));
            } elseif (!empty($preview_data_uri) && strpos($preview_data_uri, 'data:') === 0) {
                // decode and save one preview image
                try {
                    $matches = [];
                    if (preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $preview_data_uri, $matches)) {
                        $ext = explode('/', $matches[1])[1] ?? 'png';
                        $bin = base64_decode($matches[2]);
                        if ($bin !== false) {
                            $previewDir = $tmpDir . DIRECTORY_SEPARATOR . 'preview';
                            @mkdir($previewDir, 0755, true);
                            file_put_contents($previewDir . DIRECTORY_SEPARATOR . 'preview.' . $ext, $bin);
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore preview write failure
                }
            }

            file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'info.txt',
                "Design Order: {$order->id}\nProduct: " . ($order->product_name ?? '') . "\nCreated: " . ($order->created_at ?? '') . "\n");
            file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'raw_payload.json', $order->raw_payload ?? '{}');

            $zipName = 'design_order_' . $order->id . '.zip';
            $zipPath = storage_path('app/tmp/' . $zipName);
            if (file_exists($zipPath)) @unlink($zipPath);

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                throw new \Exception("Could not create zip file");
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpDir));
            foreach ($files as $file) {
                if (!$file->isFile()) continue;
                $filePath = $file->getRealPath();
                $relativePath = ltrim(str_replace($tmpDir, '', $filePath), DIRECTORY_SEPARATOR);
                $zip->addFile($filePath, $relativePath);
            }
            $zip->close();

            $this->rrmdir($tmpDir);

            return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            if (is_dir($tmpDir)) $this->rrmdir($tmpDir);
            Log::error('DesignOrder download failed: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            abort(500, 'Could not create download package: ' . $e->getMessage());
        }
    }

    /**
     * Recursively remove a directory
     */
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
     * Delete a design order (and preview file if present)
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
