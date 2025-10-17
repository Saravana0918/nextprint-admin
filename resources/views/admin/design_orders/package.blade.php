<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* Allow DomPDF to fetch Google fonts when isRemoteEnabled true */
    @import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Anton&family=Bebas+Neue&display=swap');

    body{ font-family: "DejaVu Sans", Arial, sans-serif; color:#222; margin:40px; }
    .header{ text-align:center; margin-bottom:18px; }
    .meta{ margin-bottom:8px; }
    .preview{ text-align:center; margin:18px 0; }
    .players { width:100%; border-collapse: collapse; margin-top:18px; }
    .players th, .players td { border:1px solid #ddd; padding:8px; text-align:left; font-size:12px; }
    .players th { background:#f4f4f4; }
    .f-oswald { font-family: 'Oswald', 'DejaVu Sans', Arial, sans-serif; }
    .f-anton { font-family: 'Anton', 'DejaVu Sans', Arial, sans-serif; }
    .f-bebas { font-family: 'Bebas Neue', 'DejaVu Sans', Arial, sans-serif; }
    .title { font-size: 28px; margin-bottom: 6px; }
    .sub { font-size: 14px; color:#444; }
  </style>
</head>
<body>
@php
  // normalize font class
  $fontClass = '';
  if (!empty($font)) {
    $f = strtolower(trim($font));
    if (strpos($f, 'oswald') !== false) $fontClass = 'f-oswald';
    elseif (strpos($f, 'anton') !== false) $fontClass = 'f-anton';
    elseif (strpos($f, 'bebas') !== false) $fontClass = 'f-bebas';
  }
  $colorCss = $color ?? '#000000';
@endphp

  <div class="header">
    <div class="title {{ $fontClass }}" style="color:{{ $colorCss }};">
      Design Order
    </div>
    <div class="meta {{ $fontClass }}" style="color:{{ $colorCss }};">
      <strong>Customer:</strong> {{ $customer_name ?? '—' }} &nbsp; | &nbsp;
      <strong>Number:</strong> {{ $customer_number ?? '—' }} <br/>
      <small>Order ID: {{ $order_id }}</small>
    </div>
  </div>

  <div class="preview">
    @if(!empty($preview_local_path) && file_exists($preview_local_path))
      <img src="file://{{ $preview_local_path }}" style="max-width:540px; width:100%; height:auto; border-radius:8px;"/>
    @elseif(!empty($preview_url))
      {{-- remote URL --}}
      <img src="{{ $preview_url }}" style="max-width:540px; width:100%; height:auto; border-radius:8px;"/>
    @else
      <div style="padding:40px; border:1px dashed #ccc; display:inline-block;">No preview available</div>
    @endif
  </div>

  <h4>Team Players</h4>
  <table class="players" role="table">
    <thead>
      <tr>
        <th style="width:40px">#</th>
        <th>Name</th>
        <th>Number</th>
        <th>Size</th>
        <th>Font</th>
      </tr>
    </thead>
    <tbody>
    @foreach($players as $i => $p)
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
    @endforeach
    </tbody>
  </table>

  <div style="margin-top:18px; font-size:11px; color:#666;">
    Generated at: {{ \Carbon\Carbon::now()->format('d M Y H:i') }}
  </div>
</body>
</html>
