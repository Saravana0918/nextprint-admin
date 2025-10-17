<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Design Order Image</title>
  <style>
    /* full-page white, image centered and scaled to fit page width */
    html,body{height:100%; margin:0; padding:0; background:#fff;}
    .page { width:100%; height:100%; display:flex; align-items:center; justify-content:center; box-sizing:border-box; padding:36px; }
    img.preview { max-width:100%; max-height:100%; display:block; object-fit:contain; border-radius:6px; border:0; }
    /* remove any text, just image */ 
  </style>
</head>
<body>
  <div class="page">
    @php
      // prefer local file path if available so dompdf will embed image directly
      $imgLocal = $preview_local_path ?? null;
      $imgUrl = $preview_url ?? null;
    @endphp

    @if(!empty($imgLocal) && file_exists($imgLocal))
      {{-- dompdf supports file://local paths --}}
      <img class="preview" src="file://{{ $imgLocal }}" alt="Design preview"/>
    @elseif(!empty($imgUrl))
      {{-- fallback to public/url --}}
      <img class="preview" src="{{ $imgUrl }}" alt="Design preview"/>
    @else
      <div style="text-align:center;color:#666;font-size:14px;">No preview image available</div>
    @endif
  </div>
</body>
</html>
