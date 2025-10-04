<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\DecorationAreaTemplate;
use Illuminate\Support\Facades\Log;

class DecorationAreaController extends Controller
{
    public function index()
    {
        $items = DecorationAreaTemplate::latest()->paginate(20);
        return view('admin.decoration.index', compact('items'));
    }

   public function store(Request $r)
{
    $r->validate([
        'name'       => 'required',
        'category'   => 'required|in:regular,custom,without_bleed',
        'width_mm'   => 'required|integer|min:1',
        'height_mm'  => 'required|integer|min:1',
        'svg'        => 'nullable|file|mimetypes:image/svg+xml|max:2048',
        // NEW:
        'slot_key'   => 'nullable|in:name,number',
        'max_chars'  => 'nullable|integer|min:1|max:32',
    ]);

    $svgPath = $r->file('svg')
        ? $r->file('svg')->store('area_shapes','public')
        : null;

    // sensible defaults if not provided
    $slotKey  = $r->input('slot_key');                         // 'name' | 'number' | null
    $maxChars = $r->input('max_chars');
    if ($slotKey === 'name'   && empty($maxChars)) $maxChars = 12;
    if ($slotKey === 'number' && empty($maxChars)) $maxChars = 3;

    DecorationAreaTemplate::create([
        'name'       => $r->name,
        'category'   => $r->category,
        'width_mm'   => $r->width_mm,
        'height_mm'  => $r->height_mm,
        'svg_path'   => $svgPath,
        // NEW:
        'slot_key'   => $slotKey,     // tells PDP this is Name/Number
        'max_chars'  => $maxChars,    // helper
    ]);

    return back()->with('ok','Saved');
}

private function computeLayoutSlotsFromArea($area, $imgW = 1200, $imgH = 800)
{
    $a = is_array($area) ? (object)$area : $area;

    // if percent fields present, use them
    if (isset($a->left_pct) && isset($a->top_pct) && isset($a->width_pct) && isset($a->height_pct)) {
        $left = floatval($a->left_pct);
        $top  = floatval($a->top_pct);
        $wPct = floatval($a->width_pct);
        $hPct = floatval($a->height_pct);
    } else {
        // pixel fallback
        $x = $a->x ?? $a->left ?? 0;
        $y = $a->y ?? $a->top ?? 0;
        $w = $a->width ?? $a->w ?? 100;
        $h = $a->height ?? $a->h ?? 100;

        $left = ($x / max(1, $imgW)) * 100;
        $top  = ($y / max(1, $imgH)) * 100;
        $wPct = ($w / max(1, $imgW)) * 100;
        $hPct = ($h / max(1, $imgH)) * 100;
    }

    $centerX = $left + ($wPct * 0.5);
    $nameTop = $top + ($hPct * 0.18);
    $numTop  = $top + ($hPct * 0.62);

    return [
        'name' => [
            'left_pct'  => round($centerX, 3),
            'top_pct'   => round($nameTop, 3),
            'width_pct' => round(max(6, $wPct * 0.86), 3),
            'height_pct'=> round(max(6, $hPct * 0.22), 3),
            'rotation'  => 0
        ],
        'number' => [
            'left_pct'  => round($centerX, 3),
            'top_pct'   => round($numTop, 3),
            'width_pct' => round(max(6, $wPct * 0.94), 3),
            'height_pct'=> round(max(6, $hPct * 0.36), 3),
            'rotation'  => 0
        ]
    ];
}
 
// AJAX list/search for the modal
public function search(Request $r)
{
    $q = \App\Models\DecorationAreaTemplate::query();

    if ($s = $r->get('q')) {
        $q->where('name','like',"%{$s}%");
    }

    $driver = \DB::getDriverName();
    if ($driver === 'mysql') {
        $q->orderByRaw("FIELD(category,'regular','custom','without_bleed')");
    } else {
        $q->orderByRaw("
            CASE category
                WHEN 'regular' THEN 1
                WHEN 'custom' THEN 2
                WHEN 'without_bleed' THEN 3
                ELSE 4
            END
        ");
    }
    $q->orderBy('name');

    // 👇 இதுதான் முக்கியம்: svg_path (அல்லது svg_url) வை திருப்பணும்
    return $q->get()->map(function ($t) {
        return [
            'id'        => $t->id,
            'name'      => $t->name,
            'category'  => $t->category,
            'width_mm'  => (int) $t->width_mm,
            'height_mm' => (int) $t->height_mm,
            'slot_key'  => $t->slot_key,
            'svg_path'  => $t->svg_path,                          // <-- இதை UI build பண்ணுறீங்க
            'svg_url'   => $t->svg_path ? url('files/'.$t->svg_path) : null,
        ];
    });
}


public function destroy(\App\Models\DecorationAreaTemplate $template)
{
    if ($template->svg_path) {
        Storage::disk('public')->delete($template->svg_path);
    }
    $template->delete();

    return back()->with('ok', 'Template deleted');
}

}
