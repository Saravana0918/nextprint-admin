<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 20mm; }
    html,body{height:100%; margin:0; padding:0; font-family: DejaVu Sans, Arial, sans-serif; color:#000;}
    .content { width:100%; max-width:170mm; margin: 0 auto; position: relative; }
    .preview-box { text-align:center; position:relative; margin: 6mm 0; }
    .preview-box img { max-width:100%; height:auto; display:block; margin:0 auto; border:0; }
    .overlay { position:absolute; left:0; top:0; transform-origin:left top; pointer-events:auto; white-space:nowrap; }
    .name-text { font-weight:700; text-transform:uppercase; letter-spacing:1px; }
    .number-text { font-weight:900; }
    .meta { text-align:center; margin-bottom:6mm; }
  </style>
</head>
<body>
  <div class="content">
    <div class="meta">
      <h2 style="margin:0; font-size:22pt;">Design Order</h2>
      <div style="font-size:11pt; margin-top:6px;">
        <strong>Customer:</strong> {{ $customer_name ?? '—' }} &nbsp; | &nbsp;
        <strong>Number:</strong> {{ $customer_number ?? '—' }} <br />
        <small>Order ID: {{ $order_id }}</small>
      </div>
      <div style="font-size:10pt; margin-top:4px;">
        <small>Color: {{ $color ?? '—' }} &nbsp; | &nbsp; Font: {{ $font ?? '—' }}</small>
      </div>
    </div>

    <div class="preview-box" id="preview-box">
      @php
        $imgSrc = null;
        if (!empty($preview_local_path) && file_exists($preview_local_path)) {
            $imgSrc = 'file://'.$preview_local_path;
        } elseif (!empty($preview_url)) {
            $imgSrc = $preview_url;
        }
      @endphp

      @if($imgSrc)
        <img id="base-art" src="{{ $imgSrc }}" alt="Base artwork" />
      @else
        <div style="padding:40px; border:1px dashed #ccc; display:inline-block;">No base image found</div>
      @endif

      {{-- overlays: positions are percentage based relative to the image box --}}
      <div class="overlay name-text"
           style="left: {{ $name_left_pct ?? 72 }}%; top: {{ $name_top_pct ?? 25 }}%;
                  width: {{ $name_width_pct ?? 22 }}%; font-size: {{ $name_font_size_pt ?? 22 }}pt;
                  color: {{ $color ?? '#000' }}; transform: translate(-50%,-50%); text-align:center;">
        {{ strtoupper($customer_name ?? 'NAME') }}
      </div>

      <div class="overlay number-text"
           style="left: {{ $number_left_pct ?? 72 }}%; top: {{ $number_top_pct ?? 48 }}%;
                  width: {{ $number_width_pct ?? 14 }}%; font-size: {{ $number_font_size_pt ?? 40 }}pt;
                  color: {{ $color ?? '#000' }}; transform: translate(-50%,-50%); text-align:center;">
        {{ $customer_number ?? '09' }}
      </div>
    </div>

  </div>
</body>
</html>
