{{-- resources/views/public/designer.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Designer (Public)</title>
</head>
<body>
  <h1>Public Designer</h1>
  <p>Product: {{ $product->id ?? 'n/a' }} - {{ $product->title ?? $product->name ?? 'No title' }}</p>
  <p>View: {{ $view->id ?? 'none' }}</p>

  <script>
    // pass server data to client
    window._DESIGNER = {
      product: {!! json_encode($product) !!},
      view: {!! json_encode($view) !!},
      areas: {!! json_encode($areas) !!}
    };
    console.log('DESIGNER data', window._DESIGNER);
  </script>

  <div id="app">
    <p>Check console for details. Replace this blade with your admin designer markup when ready.</p>
  </div>
</body>
</html>
