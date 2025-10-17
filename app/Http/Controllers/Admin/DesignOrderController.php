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
    // fetch order row
    $order = DB::table('design_orders')->where('id', $id)->first();
    if (!$order) abort(404, 'Order not found');

    // players (same as before)
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

    // --- find base preview local path (the base artwork WITHOUT overlays) ---
    // You must save this path when saving from the designer (see JS below).
    $preview_local_path = null;
    $preview_url = $order->preview_base ?? $order->preview_src ?? $order->preview_path ?? null;

    if ($preview_url) {
        // if it contains '/storage' -> map to storage/app/public/...
        if (strpos($preview_url, '/storage/') !== false) {
            $rel = substr($preview_url, strpos($preview_url, '/storage/') + 9);
            $full = storage_path('app/public/' . $rel);
            if (file_exists($full)) $preview_local_path = $full;
        } else {
            // maybe it's stored relative to storage/app/public
            $possible = storage_path('app/public/' . ltrim($preview_url, '/'));
            if (file_exists($possible)) $preview_local_path = $possible;
        }
    }

    // ----- compute display size (mm) and overlay coordinates (mm) -----
    // default slot percents (fallback)
    $defaults = [
        'name_left_pct' => 72, 'name_top_pct' => 25, 'name_width_pct' => 22, 'name_font_pt' => 22,
        'number_left_pct' => 72, 'number_top_pct' => 48, 'number_width_pct' => 14, 'number_font_pt' => 40,
    ];

    // try load layoutSlots from payload/meta if available (common in your app)
    $slots = [];
    if (!empty($order->payload)) {
        $pl = json_decode($order->payload, true) ?: [];
        if (!empty($pl['layoutSlots'])) $slots = $pl['layoutSlots'];
    }
    if (empty($slots) && !empty($order->meta)) {
        $m = json_decode($order->meta, true) ?: [];
        if (!empty($m['layoutSlots'])) $slots = $m['layoutSlots'];
    }
    try {
        if (!empty($slots) && is_array($slots)) {
            $sname = $slots['name'] ?? $slots['Name'] ?? null;
            $snum = $slots['number'] ?? $slots['Number'] ?? null;
            if (is_array($sname)) {
                $defaults['name_left_pct'] = $sname['left_pct'] ?? $defaults['name_left_pct'];
                $defaults['name_top_pct']  = $sname['top_pct']  ?? $defaults['name_top_pct'];
                $defaults['name_width_pct']= $sname['width_pct'] ?? $defaults['name_width_pct'];
            }
            if (is_array($snum)) {
                $defaults['number_left_pct'] = $snum['left_pct'] ?? $defaults['number_left_pct'];
                $defaults['number_top_pct']  = $snum['top_pct']  ?? $defaults['number_top_pct'];
                $defaults['number_width_pct']= $snum['width_pct'] ?? $defaults['number_width_pct'];
            }
            if (!empty($sname['font_size_pt'])) $defaults['name_font_pt'] = $sname['font_size_pt'];
            if (!empty($snum['font_size_pt'])) $defaults['number_font_pt'] = $snum['font_size_pt'];
        }
    } catch (\Throwable $e) {
        // keep defaults
    }

    // decide how wide the artwork appears on PDF (mm). A4 width minus margins gives ~170-180mm; use safe 150mm or adjust.
    $maxImageWidthMm = 150.0;
    $displayWidthMm = $maxImageWidthMm;
    $displayHeightMm = 110.0;

    if ($preview_local_path && file_exists($preview_local_path)) {
        try {
            [$imgWpx, $imgHpx] = getimagesize($preview_local_path);
            $pxToMm = 25.4 / 96.0; // dompdf default 96 DPI
            $imageWidthMm = $imgWpx * $pxToMm;
            $imageHeightMm = $imgHpx * $pxToMm;
            $scale = ($imageWidthMm > 0) ? ($maxImageWidthMm / $imageWidthMm) : 1.0;
            $displayWidthMm = max(10, $imageWidthMm * $scale);
            $displayHeightMm = max(10, $imageHeightMm * $scale);
        } catch (\Throwable $e) {
            // fallback sizes
            $displayWidthMm = $maxImageWidthMm;
            $displayHeightMm = 110.0;
        }
    }

    // compute mm coordinates (left/top) relative to preview box
    $name_left_mm   = ($defaults['name_left_pct'] / 100.0) * $displayWidthMm;
    $name_top_mm    = ($defaults['name_top_pct']  / 100.0) * $displayHeightMm;
    $number_left_mm = ($defaults['number_left_pct'] / 100.0) * $displayWidthMm;
    $number_top_mm  = ($defaults['number_top_pct']  / 100.0) * $displayHeightMm;
    $name_font_pt   = $defaults['name_font_pt'];
    $number_font_pt = $defaults['number_font_pt'];

    // color & font from order (fall back)
    $color = $order->color ?? '#000000';
    $fontName = $order->font ?? 'DejaVu Sans';

    // --------- BUILD PDF DATA ----------
    $pdfData = [
        'product_name' => $order->product_name ?? null,
        'customer_name' => $order->name_text ?? null,
        'customer_number' => $order->number_text ?? null,
        'order_id' => $order->id,
        'players' => $players,
        'preview_local_path' => $preview_local_path,
        'preview_url' => $preview_url,
        'displayWidthMm' => round($displayWidthMm,2),
        'displayHeightMm' => round($displayHeightMm,2),
        'name_left_mm' => round($name_left_mm,2),
        'name_top_mm' => round($name_top_mm,2),
        'name_font_size_pt' => (int)$name_font_pt,
        'number_left_mm' => round($number_left_mm,2),
        'number_top_mm' => round($number_top_mm,2),
        'number_font_size_pt' => (int)$number_font_pt,
        'font' => $fontName,
        'color' => $color,
    ];

    // prepare temp dir
    $tmpDir = storage_path('app/tmp/design_package_' . $id . '_' . time());
    @mkdir($tmpDir, 0755, true);

    try {
        // generate PDF using blade that places text overlays in mm units
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.design_orders.package_for_corel', $pdfData);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'design_order_' . $id . '.pdf';
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

        // copy preview image (base) if exists into preview/ folder
        if ($preview_local_path && file_exists($preview_local_path)) {
            $previewDir = $tmpDir . DIRECTORY_SEPARATOR . 'preview';
            @mkdir($previewDir, 0755, true);
            copy($preview_local_path, $previewDir . DIRECTORY_SEPARATOR . basename($preview_local_path));
        }

        // info + raw payload
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'info.txt', "Design Order: {$id}\nProduct: " . ($order->product_name ?? '') . "\nCreated: " . ($order->created_at ?? '') . "\n");
        file_put_contents($tmpDir . DIRECTORY_SEPARATOR . 'raw_payload.json', $order->raw_payload ?? '{}');

        // create zip
        $zipName = 'design_order_' . $id . '.zip';
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

        // cleanup tmpDir
        $this->rrmdir($tmpDir);

        // send zip response and delete after send
        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);

    } catch (\Throwable $e) {
        if (is_dir($tmpDir)) $this->rrmdir($tmpDir);
        \Log::error('DesignOrder download failed: ' . $e->getMessage(), ['trace'=>$e->getTraceAsString()]);
        abort(500, 'Could not create download package: ' . $e->getMessage());
    }
}


// helper: remove dir recursively
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
