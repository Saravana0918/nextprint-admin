@extends('layouts.app')

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Storage;

  // font key -> friendly name map
  $fontMap = [
    'bebas' => 'Bebas Neue',
    'bebas neue' => 'Bebas Neue',
    'bebas-neue' => 'Bebas Neue',
    'oswald' => 'Oswald',
    'anton' => 'Anton',
    'impact' => 'Impact',
  ];

  /**
   * Recursively search array (or object coerced to array) for the first matching key
   * Returns null if not found.
   */
  $searchKeysRecursive = function($hay, array $keys) use (&$searchKeysRecursive) {
    if (empty($hay)) return null;
    if (!is_array($hay)) {
      // try to convert objects
      if (is_object($hay)) $hay = (array)$hay;
      else return null;
    }
    foreach ($keys as $k) {
      if (array_key_exists($k, $hay) && $hay[$k] !== null && $hay[$k] !== '') {
        return $hay[$k];
      }
      // case-insensitive check
      foreach ($hay as $hk => $hv) {
        if (strcasecmp($hk, $k) === 0 && $hv !== null && $hv !== '') {
          return $hv;
        }
      }
    }
    // traverse nested arrays
    foreach ($hay as $v) {
      if (is_array($v) || is_object($v)) {
        $found = $searchKeysRecursive((array)$v, $keys);
        if ($found !== null && $found !== '') return $found;
      }
    }
    return null;
  };

  /**
   * Safely decode JSON which may be double encoded or empty
   */
  $safeJsonDecode = function($text) {
    if (empty($text)) return [];
    // try normal decode
    $decoded = json_decode($text, true);
    if (is_array($decoded)) return $decoded;
    // if decode returned string, try decode again
    if (is_string($decoded)) {
      $decoded2 = json_decode($decoded, true);
      if (is_array($decoded2)) return $decoded2;
    }
    // if still no array, try to coerce simple formats
    return [];
  };

  /**
   * Normalize color string to leading '#' hex if possible
   */
  $normalizeColor = function($c) {
    if (!$c) return null;
    $c = trim((string)$c);
    try { $c = urldecode($c); } catch(\Throwable$e) {}
    if ($c === '') return null;
    // if it's like rgb(...) try to convert to hex (basic)
    if (stripos($c, 'rgb(') === 0) {
      $vals = preg_replace('/[^\d,\.]/','', $c);
      $parts = array_map('trim', explode(',', $vals));
      if (count($parts) >= 3) {
        $r = (int)$parts[0]; $g = (int)$parts[1]; $b = (int)$parts[2];
        return sprintf('#%02x%02x%02x', max(0,min(255,$r)), max(0,min(255,$g)), max(0,min(255,$b)));
      }
    }
    // hex without #?
    if (preg_match('/^[0-9a-f]{3,6}$/i', ltrim($c, '#'))) {
      $hex = ltrim($c, '#');
      if (strlen($hex) === 3) {
        // expand short hex
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
      }
      return '#' . strtolower($hex);
    }
    // allow color names? (basic common)
    $cssNames = [
      'black'=>'#000000','white'=>'#ffffff','red'=>'#ff0000','green'=>'#008000','blue'=>'#0000ff',
      'yellow'=>'#ffff00','gray'=>'#808080','grey'=>'#808080'
    ];
    $low = strtolower($c);
    if (isset($cssNames[$low])) return $cssNames[$low];
    // already contains # and hex
    if ($c[0] === '#' && preg_match('/^#[0-9a-f]{3,6}$/i', $c)) {
      if (strlen($c) === 4) {
        $h = substr($c,1);
        $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        return '#'.strtolower($h);
      }
      return strtolower($c);
    }
    return null;
  };

  // Start extraction for font/color
  $orderFontRaw = trim((string)($order->font ?? ''));
  $orderColorRaw = trim((string)($order->color ?? ''));

  // if top-level present keep it, else try payload/raw_payload/meta
  if (empty($orderFontRaw) || empty($orderColorRaw)) {
    $payloadArr = $safeJsonDecode($order->payload ?? '');
    $rawPayloadArr = $safeJsonDecode($order->raw_payload ?? '');
    $metaArr = $safeJsonDecode($order->meta ?? '');

    // keys to look for (order matters)
    $fontKeys = ['font','selectedFont','font_family','fontName','font_key','fontKey','typeface','family'];
    $colorKeys = ['color','selectedColor','hex','colour','fontColor','textColor','fillColor'];

    if (empty($orderFontRaw)) {
      // check payload, raw_payload, meta, then top-level fallback
      $orderFontRaw = $searchKeysRecursive($payloadArr, $fontKeys) ?? $searchKeysRecursive($rawPayloadArr, $fontKeys) ?? $searchKeysRecursive($metaArr, $fontKeys) ?? $orderFontRaw;
      if (is_array($orderFontRaw)) {
        // sometimes font object: try common subkeys
        $orderFontRaw = $orderFontRaw['name'] ?? $orderFontRaw['key'] ?? $orderFontRaw['family'] ?? null;
      }
    }

    if (empty($orderColorRaw)) {
      $orderColorRaw = $searchKeysRecursive($payloadArr, $colorKeys) ?? $searchKeysRecursive($rawPayloadArr, $colorKeys) ?? $searchKeysRecursive($metaArr, $colorKeys) ?? $orderColorRaw;
      if (is_array($orderColorRaw)) {
        $orderColorRaw = $orderColorRaw['hex'] ?? $orderColorRaw['value'] ?? null;
      }
    }
  }

  // final normalize / friendly label
  $orderFontKey = strtolower(preg_replace('/[^a-z0-9]+/',' ', trim((string)$orderFontRaw)));
  $orderFontLabel = $fontMap[$orderFontKey] ?? ($orderFontRaw ? $orderFontRaw : '—');

  $orderColorHex = $normalizeColor($orderColorRaw ?? null);
@endphp

<div class="container py-4">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
      <a href="{{ route('admin.design-orders.index') }}" class="btn btn-sm btn-outline-secondary">← Back to Orders</a>
    </div>
    <div>
      <span class="badge bg-secondary">Order #{{ $order->id }}</span>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2">
            {{ $order->product_name ?? ('Product #' . ($order->product_id ?? '—')) }}
          </h5>

          <p class="mb-1">
            <strong>Customer Name / Number:</strong>
            {{ $order->name_text ?? '—' }} / {{ $order->number_text ?? '—' }}
          </p>

          <p class="mb-1">
            <strong>Font / Color:</strong>
            <span title="{{ $orderFontRaw }}">{{ $orderFontLabel }}</span>
            &nbsp;/&nbsp;
            @if($orderColorHex)
              <span class="d-inline-flex align-items-center">
                <span style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;background:{{ $orderColorHex }};margin-right:6px;border-radius:3px;"></span>
                <code style="font-size:15px;">{{ $orderColorHex }}</code>
              </span>
            @else
              <span class="text-muted">—</span>
            @endif
          </p>

          <p class="mb-1"><strong>Quantity:</strong> {{ $order->quantity ?? '1' }}</p>

          <p class="mb-1"><strong>Uploaded Logo URL:</strong>
            @if(!empty($order->uploaded_logo_url))
              <a href="{{ $order->uploaded_logo_url }}" target="_blank">Open logo</a>
            @else
              <span class="text-muted">—</span>
            @endif
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6 class="mb-2">Preview</h6>

          @php
            $preview = $order->preview_src ?? $order->preview_path ?? null;
            $previewUrl = null;
            if (!empty($preview)) {
              if (Str::startsWith($preview, '/storage') || Str::startsWith($preview, 'storage')) {
                $previewUrl = asset(ltrim($preview, '/'));
              } elseif (Str::startsWith($preview, 'http://') || Str::startsWith($preview, 'https://')) {
                $previewUrl = $preview;
              } else {
                // try disk public existence (preview may be "team_previews/xxx.png")
                if (Storage::disk('public')->exists($preview)) {
                  $previewUrl = asset('storage/' . ltrim($preview, '/'));
                } else {
                  // maybe saved already as '/storage/...'
                  $previewUrl = $preview;
                }
              }
            }
          @endphp

          @if($previewUrl)
            <a href="{{ $previewUrl }}" target="_blank">
              <img src="{{ $previewUrl }}" alt="preview" class="img-fluid border rounded">
            </a>
            <div class="mt-2 text-muted small">Click image to open in a new tab.</div>
          @else
            <div class="text-muted">No preview available</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-6">
      {{-- Team players card --}}
      <div class="card mb-3">
        <div class="card-body">
          <h6>Team Players ({{ $players->count() }})</h6>

          @if($players->isEmpty())
            <div class="text-muted">No players saved for this order.</div>
          @else
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Number</th>
                    <th>Size</th>
                    <th>Font</th>
                    <th>Preview</th>
                    <th>Saved</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($players as $index => $p)
                    @php
                      $pObj = is_object($p) ? $p : (object)$p;
                      $pId = $pObj->id ?? ($index+1);
                      $pName = $pObj->name ?? '';
                      $pNumber = $pObj->number ?? '';
                      $pSize = $pObj->size ?? '';
                      // player font fallback: player's font -> order font
                      $pFontRaw = $pObj->font ?? $order->font ?? '';
                      $pFontKey = strtolower(preg_replace('/[^a-z0-9]+/',' ', trim((string)$pFontRaw)));
                      $pFontLabel = $fontMap[$pFontKey] ?? ($pFontRaw ?: '—');

                      $pColor = $pObj->color ?? null;
                      if ($pColor) { $pColor = (strpos($pColor, '#') === 0) ? $pColor : '#' . ltrim($pColor, '#'); }

                      $pPreview = $pObj->preview_src ?? null;
                      $pUrl = null;
                      if (!empty($pPreview)) {
                        if (Str::startsWith($pPreview, '/storage') || Str::startsWith($pPreview, 'storage')) {
                          $pUrl = asset(ltrim($pPreview, '/'));
                        } elseif (Str::startsWith($pPreview, 'http://') || Str::startsWith($pPreview, 'https://')) {
                          $pUrl = $pPreview;
                        } else {
                          $pUrl = Storage::disk('public')->exists($pPreview) ? asset('storage/'.$pPreview) : $pPreview;
                        }
                      }

                      $pCreated = $pObj->created_at ?? null;
                    @endphp

                    <tr>
                      <td>{{ $pId }}</td>
                      <td>{{ $pName }}</td>
                      <td>{{ $pNumber }}</td>
                      <td>{{ $pSize }}</td>
                      <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                          <span>{{ $pFontLabel }}</span>
                          @if($pColor)
                            <span style="display:inline-block;width:14px;height:14px;background:{{ $pColor }};border:1px solid #ccc;border-radius:3px;"></span>
                            <small class="text-muted">{{ $pColor }}</small>
                          @endif
                        </div>
                      </td>
                      <td style="width:90px;">
                        @if($pUrl)
                          <img src="{{ $pUrl }}" width="56" height="56" style="object-fit:cover; border-radius:4px;">
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>
                      <td class="text-muted small">
                        @if($pCreated)
                          {{ \Carbon\Carbon::parse($pCreated)->format('d M Y') }}
                        @else
                          {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      {{-- Metadata --}}
      <div class="card">
        <div class="card-body">
          <h6>Metadata</h6>
          <ul class="list-unstyled small mb-0">
            <li><strong>Stored at:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y, H:i') }}</li>
            <li><strong>Status:</strong> {{ $order->status ?? '—' }}</li>
            <li><strong>Download URL:</strong>
              @if(!empty($order->preview_src) || !empty($order->preview_path) || !empty($order->preview_url))
                <a href="{{ route('admin.design-orders.download', ['id' => $order->id]) }}" class="btn btn-sm btn-outline-primary">Download package</a>
              @else
                <span class="text-muted">—</span>
              @endif
            </li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
