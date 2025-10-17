<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body{ font-family: DejaVu Sans, Arial, sans-serif; color:#222; }
    .header{ text-align:center; margin-bottom:12px; }
    .preview{ text-align:center; margin:12px 0; }
    .meta{ margin-bottom:8px; }
    .players { width:100%; border-collapse: collapse; margin-top:10px; }
    .players th, .players td { border:1px solid #ddd; padding:6px; text-align:left; font-size:12px; }
    .players th { background:#f4f4f4; }
  </style>
</head>
<body>
  <div class="header">
    <h2>{{ $product_name ?? 'Design Order' }}</h2>
    <div class="meta">
      <strong>Customer:</strong> {{ $customer_name ?? '—' }}  &nbsp; | &nbsp;
      <strong>Number:</strong> {{ $customer_number ?? '—' }} <br />
      <strong>Font:</strong> {{ $order->font ?? '—' }} <br>
      <strong>Color:</strong> {{ $order->color ?? '—' }} <br>
      <small>Order ID: {{ $order_id }}</small>
    </div>
  </div>

  <div class="preview">
    @if($preview_local_path && file_exists($preview_local_path))
      <img src="file://{{ $preview_local_path }}" style="max-width: 420px; width:100%; height:auto;"/>
    @elseif(!empty($preview_url))
      <img src="{{ $preview_url }}" style="max-width: 420px; width:100%; height:auto;"/>
    @else
      <div style="padding:30px; border:1px dashed #ccc;">No preview available</div>
    @endif
  </div>

  <h4>Team Players</h4>
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

</body>
</html>
