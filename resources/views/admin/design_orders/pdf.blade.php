<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #333; }
    .header { text-align:center; margin-bottom: 20px; }
    .big-title { font-size: 32px; font-weight:700; margin-bottom: 6px; }
    .meta { margin-bottom: 18px; text-align:center; }
    .preview { text-align:center; margin: 20px 0; }
    .players { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .players th, .players td { padding: 6px 8px; border: 1px solid #ddd; font-size: 12px; }
    .small { font-size: 11px; color:#666; }
  </style>
</head>
<body>
  <div class="header">
    <div class="big-title">Design Order</div>
    <div class="meta">
      <strong>Customer:</strong> {{ $order->name_text ?? '—' }} &nbsp; | &nbsp;
      <strong>Number:</strong> {{ $order->number_text ?? '—' }} <br>
      <strong>Font:</strong> {{ $order->font ?? '—' }} <br>
      <strong>Color:</strong> {{ $order->color ?? '—' }} <br>
      <span class="small">Order ID: {{ $order->id }}</span>
    </div>
  </div>

  <div class="preview">
    @php
      $img = $order->preview_src ?? null;
      $abs = '';
      if ($img) {
        if (strpos($img, '/storage/') === 0) {
            $rel = substr($img, 9);
            $abs = storage_path('app/public/' . $rel);
        } else {
            $u = parse_url($img);
            if (!empty($u['path']) && strpos($u['path'], '/storage/') !== false) {
                $rel = substr($u['path'], strpos($u['path'],'/storage/') + 9);
                $abs = storage_path('app/public/' . $rel);
            } else {
                // remote URL fallback - dompdf may not fetch remote by default
                $abs = $img;
            }
        }
      }
    @endphp

    @if($abs && file_exists($abs))
      <img src="file://{{ $abs }}" style="max-width: 480px; width: 100%; height: auto; border:1px solid #eee; padding:6px; background:#fff;">
    @else
      <p class="small">Preview image not available</p>
    @endif
  </div>

  <h4>Players</h4>
  <table class="players">
    <thead>
      <tr><th>#</th><th>Name</th><th>Number</th><th>Size</th><th>Font</th></tr>
    </thead>
    <tbody>
      @foreach($players as $i => $p)
        <tr>
          <td>{{ $i+1 }}</td>
          <td>{{ $p['name'] ?? '' }}</td>
          <td>{{ $p['number'] ?? '' }}</td>
          <td>{{ $p['size'] ?? '' }}</td>
          <td>{{ $p['font'] ?? '' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <div style="margin-top: 24px; font-size: 11px; color:#666;">
    <strong>Font / Color:</strong>
    {{ $order->font ?? '—' }}  /  {{ $order->color ?? '—' }}
  </div>
</body>
</html>
