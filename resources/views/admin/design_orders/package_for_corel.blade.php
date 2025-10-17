<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 18mm; }
    html,body{height:100%; margin:0; padding:0; font-family: DejaVu Sans, Arial, sans-serif; color:#000;}
    .content { width:100%; max-width:100%; margin: 0 auto; position: relative; }
    .meta { text-align:center; margin-bottom:6mm; }
    .preview-wrap { position: relative; margin: 0 auto; }
    .preview-box {
      position: relative;
      margin: 6mm auto;
      width: {{ $displayWidthMm }}mm;
      height: {{ $displayHeightMm }}mm;
      border-radius:4px;
      overflow:visible;
    }
    .preview-box img { width:100%; height:100%; display:block; border-radius:4px; }
    .overlay {
      position:absolute;
      white-space:nowrap;
      text-align:center;
      color: {{ $color }};
      /* anchor top-left to avoid transform differences in viewers */
      transform: none;
    }
    .name-text { font-weight:700; text-transform:uppercase; letter-spacing:1px; font-family: "{{ $font }}", DejaVu Sans, Arial, sans-serif; }
    .number-text { font-weight:900; font-family: "{{ $font }}", DejaVu Sans, Arial, sans-serif; }
  </style>
</head>
<body>
  <div class="content">
    <div class="meta">
      <h2 style="margin:0; font-size:22pt;">Design Order</h2>
      <div style="font-size:10pt; margin-top:6px;">
        <strong>Customer:</strong> {{ $customer_name ?? '—' }} &nbsp; | &nbsp;
        <strong>Number:</strong> {{ $customer_number ?? '—' }} <br />
        <small>Order ID: {{ $order_id }}</small>
      </div>
      <div style="font-size:9pt; margin-top:4px;">
        <small>Color: {{ $color ?? '—' }} &nbsp; | &nbsp; Font: {{ $font ?? '—' }}</small>
      </div>
    </div>

    <div class="preview-wrap">
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
          <img src="{{ $imgSrc }}" alt="Base artwork" />
        @else
          <div style="padding:40px; border:1px dashed #ccc; display:inline-block;">No base image found</div>
        @endif

        <div class="overlay name-text"
             style="left: {{ $name_left_mm }}mm; top: {{ $name_top_mm }}mm; font-size: {{ $name_font_size_pt }}pt;">
          {{ strtoupper($customer_name ?? 'NAME') }}
        </div>

        <div class="overlay number-text"
             style="left: {{ $number_left_mm }}mm; top: {{ $number_top_mm }}mm; font-size: {{ $number_font_size_pt }}pt;">
          {{ $customer_number ?? '09' }}
        </div>
      </div>
    </div>
  </div>
</body>
</html>
