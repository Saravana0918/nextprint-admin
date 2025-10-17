<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* try to load remote fonts if dompdf can fetch them */
    @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Anton&family=Bebas+Neue&display=swap');

    body{ font-family: "DejaVu Sans", Arial, sans-serif; color:#222; margin:28px; }
    .header{ text-align:center; margin-bottom:14px; }
    .meta{ margin-bottom:6px; }
    .preview{ text-align:center; margin:18px 0; }
    .players { width:100%; border-collapse: collapse; margin-top:18px; }
    .players th, .players td { border:1px solid #ddd; padding:8px; text-align:left; font-size:12px; vertical-align:middle; }
    .players th { background:#f8f8f8; }
    .f-oswald { font-family: 'Oswald', 'DejaVu Sans', Arial, sans-serif; }
    .f-anton { font-family: 'Anton', 'DejaVu Sans', Arial, sans-serif; }
    .f-bebas { font-family: 'Bebas Neue', 'DejaVu Sans', Arial, sans-serif; }
    .title { font-size: 28px; margin-bottom: 4px; }
    .sub { font-size: 13px; color:#444; }
    .info { font-size:11px; color:#666; margin-top:10px; }
    .color-sample { display:inline-block; width:18px; height:18px; border-radius:4px; border:1px solid #ccc; vertical-align:middle; margin-right:8px; }
    img.preview-img { border-radius:8px; border:1px solid #e7e7e7; }
  </style>
</head>
<body>
@php
  // normalize color
  $rawColor = $color ?? '#000000';
  $rawColor = trim(urldecode($rawColor));
  if ($rawColor === '') $rawColor = '#000000';
  if ($rawColor[0] !== '#') $rawColor = '#'.ltrim($rawColor,'#');

  // choose readable text color (if the chosen color is too light)
  function hex_is_light($hex) {
    $hex = ltrim($hex,'#');
    if (strlen($hex) === 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
    }
    $lum = (0.299*$r + 0.587*$g + 0.114*$b) / 255;
    return ($lum > 0.75);
  }
  $isLight = false;
  try { $isLight = hex_is_light($rawColor); } catch(\Throwable $e) { $isLight = false; }
  $textColor = $isLight ? '#222222' : $rawColor;

  // font css class guess
  $fontClass = '';
  if (!empty($font)) {
    $f = strtolower(trim($font));
    if (strpos($f,'oswald') !== false) $fontClass = 'f-oswald';
    elseif (strpos($f,'anton') !== false) $fontClass = 'f-anton';
    elseif (strpos($f,'bebas') !== false) $fontClass = 'f-bebas';
  }
@endphp

  <div class="header">
    <div class="title {{ $fontClass }}" style="color: {{ $textColor }};">
      Design Order
    </div>

    <div class="meta" style="color: {{ $textColor }};">
      <strong>Customer:</strong> {{ $customer_name ?? '—' }} &nbsp; | &nbsp;
      <strong>Number:</strong> {{ $customer_number ?? '—' }} <br/>
      <small>Order ID: {{ $order_id }}</small>
    </div>

    {{-- show font name and color --}}
    <div style="margin-top:6px;">
      <span class="color-sample" style="background: {{ $rawColor }};"></span>
      <span style="font-size:12px; color:#333;">Color: <strong>{{ strtoupper($rawColor) }}</strong></span>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      <span style="font-size:12px; color:#333;">Font: <strong>{{ $font ?? '—' }}</strong></span>
    </div>

    {{-- sample text using the selected font & color --}}
    <div style="margin-top:8px;">
      <div class="{{ $fontClass }}" style="color: {{ $rawColor }}; font-size:18px; display:inline-block; padding:6px 10px; border-radius:4px;">
        SAMPLE: {{ strtoupper($customer_name ?? 'NAME') }}
      </div>
    </div>
  </div>

  <div class="preview">
    @if(!empty($preview_local_path) && file_exists($preview_local_path))
      <img class="preview-img" src="file://{{ $preview_local_path }}" style="max-width:540px; width:100%; height:auto;" />
    @elseif(!empty($preview_url))
      <img class="preview-img" src="{{ $preview_url }}" style="max-width:540px; width:100%; height:auto;" />
    @else
      <div style="padding:40px; border:1px dashed #ccc; display:inline-block;">No preview available</div>
    @endif
  </div>

  <h4 style="margin-bottom:8px; color:#222;">Team Players</h4>

  <table class="players" role="table" aria-label="Team players">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Name</th>
        <th style="width:90px">Number</th>
        <th style="width:90px">Size</th>
        <th style="width:120px">Font</th>
      </tr>
    </thead>
    <tbody>
    @forelse($players as $i => $p)
      @php
        $pid = $p['id'] ?? ($i+1);
        $pname = $p['name'] ?? '';
        $pnum  = $p['number'] ?? '';
        $psize = $p['size'] ?? '';
        $pfont = $p['font'] ?? '';
      @endphp
      <tr>
        <td>{{ $pid }}</td>
        <td>{{ $pname }}</td>
        <td>{{ $pnum }}</td>
        <td>{{ $psize }}</td>
        <td>{{ $pfont }}</td>
      </tr>
    @empty
      <tr><td colspan="5" style="color:#666; padding:10px;">No players data</td></tr>
    @endforelse
    </tbody>
  </table>

  <div class="info">
    Generated at: {{ \Carbon\Carbon::now()->format('d M Y H:i') }}
  </div>
</body>
</html>
