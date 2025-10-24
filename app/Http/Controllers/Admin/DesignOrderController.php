<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class DesignOrderController extends Controller
{
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

    public function download($id)
{
    $order = DB::table('design_orders')->where('id', $id)->first();
    if (!$order) abort(404, 'Order not found');

    // players extraction (same as you had)
    $players = [];
    if (!empty($order->payload)) {
        $decoded = json_decode($order->payload, true) ?: [];
        if (!empty($decoded['players']) && is_array($decoded['players'])) $players = $decoded['players'];
    }
    if (empty($players) && !empty($order->raw_payload)) {
        $r = json_decode($order->raw_payload, true) ?: [];
        if (!empty($r['players'])) $players = $r['players'];
    }
    if (empty($players) && !empty($order->team_id)) {
        $team = DB::table('teams')->where('id', $order->team_id)->first();
        if ($team && !empty($team->players)) $players = json_decode($team->players, true) ?: [];
    }

    // Determine preview source (priority: preview_base (raw data) -> preview_src if data: -> preview_path -> preview_url)
    $preview_data_uri = null;
    $preview_local_path = null;
    $preview_url = null;

    // if preview_base exists and looks like base64 or data-uri
    if (!empty($order->preview_base)) {
        // preview_base may be a data URI or raw base64; try to normalise
        if (Str::startsWith($order->preview_base, 'data:')) {
            $preview_data_uri = $order->preview_base;
        } else {
            // assume raw base64 (PNG)
            $bin = base64_decode($order->preview_base);
            if ($bin !== false) {
                $preview_data_uri = 'data:image/png;base64,' . base64_encode($bin);
            }
        }
    }

    // if not yet data-uri, check preview_src (could be data: or path)
    if (!$preview_data_uri && !empty($order->preview_src)) {
        if (Str::startsWith($order->preview_src, 'data:')) {
            $preview_data_uri = $order->preview_src;
        } else {
            $preview_url = $order->preview_src;
        }
    }

    // fallback to preview_path column
    if (!$preview_data_uri && !$preview_url && !empty($order->preview_path)) {
        $preview_url = $order->preview_path;
    }

    // Try to resolve local filesystem path from preview_url (if it points to /storage/...)
    if ($preview_url) {
        $pathOnly = parse_url($preview_url, PHP_URL_PATH) ?: $preview_url;
        $pathOnly = ltrim($pathOnly, '/'); // remove leading slash if any

        // If it's a storage path like "storage/team_previews/xxx.png" or "/storage/..."
        if (strpos($pathOnly, 'storage/') === 0) {
            $rel = preg_replace('#^storage/#', '', $pathOnly);
            $possible = storage_path('app/public/' . $rel);
            if (file_exists($possible) && is_readable($possible)) {
                $preview_local_path = $possible;
            }
        } else {
            // maybe preview_url already is the relative path without "storage/" prefix (e.g. "team_previews/xxx.png")
            if (strpos($pathOnly, 'team_previews/') === 0) {
                $possible = storage_path('app/public/' . $pathOnly);
                if (file_exists($possible) && is_readable($possible)) {
                    $preview_local_path = $possible;
                }
            }
        }
    }

    // If we have local file, embed as data-uri (if file size reasonable), else fallback to file:// or remote URL
    if ($preview_local_path && file_exists($preview_local_path)) {
        try {
            $contents = file_get_contents($preview_local_path);
            if ($contents !== false) {
                // if file not huge, embed as data uri (safer)
                if (strlen($contents) < (4 * 1024 * 1024)) { // 4MB threshold
                    $mime = @mime_content_type($preview_local_path) ?: 'image/png';
                    $preview_data_uri = 'data:' . $mime . ';base64,' . base64_encode($contents);
                } else {
                    // too big to embed, use file:// absolute path (DomPDF can read local files)
                    $preview_url = 'file://' . $preview_local_path;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("DesignOrder #{$id}: failed reading local preview file: " . $e->getMessage());
        }
    }

    // If still no preview_data_uri and preview_url exists, keep using preview_url (could be remote URL)
    // At this point $preview_data_uri or $preview_url (which might be file://, asset url or remote url) will be used.

    // Layout / fonts / dimensions (you can re-use your existing calculations)
    $maxImageWidthMm = 150.0;
    $displayWidthMm = $maxImageWidthMm;
    $displayHeightMm = 110.0;
    if (!empty($preview_local_path) && file_exists($preview_local_path)) {
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

    // font/color fallback logic (reuse your prior code)
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

    // prepare view data
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
        'font' => $fontName,
        'color' => $color,
        // add other layout vars as you need...
    ];

    // generate pdf (use your existing blade view)
    try {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('admin.design_orders.package_for_corel', $pdfData);

        // save to temp and zip as you already do, or just stream pdf:
        return $pdf->stream('design_order_' . $id . '.pdf'); // or ->download(...)
    } catch (\Throwable $e) {
        Log::error('DesignOrder download PDF generation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        abort(500, 'Could not generate PDF: ' . $e->getMessage());
    }
}


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
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
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
