<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position:absolute; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; display:flex; align-items:center; justify-content:center; pointer-events:none; box-sizing:border-box; }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

    body { background-color : #929292; }
    .desktop-display{ color:white; font-family: "Roboto Condensed", sans-serif; font-weight: bold; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }

    /* mobile tweaks */
    @media (max-width: 767px) {
      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; position: relative; margin-top: -70px; }
      body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 5; pointer-events: none; }
      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }
      .np-stage { padding: 12px; background: transparent; box-sizing: border-box; border-radius: 10px; z-index: 100; position: relative !important; }
      .np-stage img#np-base { display:block; width:100%; height:auto; border-radius:8px; background-color:#f6f6f6; box-shadow: 0 6px 18px rgba(0,0,0,0.35); border: 3px solid rgba(255,255,255,0.12); position: relative; z-index: 14; }
      #np-atc-btn.mobile-fixed { position: fixed !important; top: 10px !important; right: 12px !important; z-index: 99999 !important; width: 109px !important; height: 40px !important; padding: 6px 12px !important; border-radius: 28px !important; background: #0d6efd !important; color: #fff !important; }
      /* ensure overlays are visible above image */
      #np-prev-name, #np-prev-num { z-index: 999999 !important; pointer-events: none !important; text-shadow: 0 3px 10px rgba(0,0,0,0.7) !important; color: #fff; }
    }
  </style>
</head>
<body class="body-padding">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview"
               onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 np-col order-2 order-md-1" id="np-controls">
      <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
      <input id="np-num" type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
      <select id="np-font" class="form-select mb-2">
        <option value="bebas">Bebas Neue</option>
        <option value="anton">Anton</option>
        <option value="oswald">Oswald</option>
        <option value="impact">Impact</option>
      </select>

      <div class="d-flex gap-2 align-items-center mb-2">
        <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF"></button>
        <button type="button" class="np-swatch" data-color="#000000" style="background:#000000"></button>
        <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
        <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
        <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
      </div>
      <input id="np-color" type="color" class="form-control form-control-color mt-1" value="#D4AF37">
    </div>

    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <select id="np-size" name="size" class="form-select mb-2" required>
        <option value="">Select Size</option>
        <option value="S">S</option><option value="M">M</option>
        <option value="L">L</option><option value="XL">XL</option>
      </select>
      <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control mb-2">
      <button id="np-atc-btn" type="button" class="btn btn-primary">Add to Cart</button>
    </div>
  </div>
</div>

<script> window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!}; window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }}; </script>

<script>
(function(){
  // short helpers
  const $ = id => document.getElementById(id);
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const ctrls = $('np-controls'), btn = $('np-atc-btn');
  const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

  // safety guards
  if (!pvName || !pvNum || !baseImg || !stage || !nameEl || !numEl || !fontEl || !colorEl) {
    console.warn('Designer: missing required DOM nodes. Aborting.');
    return;
  }

  // compute image bounding relative to stage
  function getRenderedImageRect() {
    const imgRect = baseImg.getBoundingClientRect();
    const stageRect = stage.getBoundingClientRect();
    return {
      imgLeft: imgRect.left - stageRect.left,
      imgTop: imgRect.top - stageRect.top,
      imgWidth: Math.max(1, imgRect.width),
      imgHeight: Math.max(1, imgRect.height),
      stageWidth: Math.max(1, stageRect.width),
      stageHeight: Math.max(1, stageRect.height)
    };
  }

  // place overlay centered inside slot area (pixel-based)
  function placeOverlay(el, slot, slotKey) {
    if (!slot) return;
    const r = getRenderedImageRect();
    const leftPx = r.imgLeft + (slot.left_pct || 0) / 100 * r.imgWidth;
    const topPx  = r.imgTop  + (slot.top_pct  || 0) / 100 * r.imgHeight;
    const areaW  = Math.max(8, Math.round((slot.width_pct || 10)/100 * r.imgWidth));
    const areaH  = Math.max(8, Math.round((slot.height_pct || 10)/100 * r.imgHeight));

    // position: overlay center at (leftPx + areaW/2, topPx + areaH/2)
    const centerX = Math.round(leftPx + areaW/2);
    const centerY = Math.round(topPx + areaH/2);

    el.style.position = 'absolute';
    el.style.left = centerX + 'px';
    el.style.top  = centerY + 'px';
    el.style.width = areaW + 'px';
    el.style.height = areaH + 'px';
    el.style.transform = 'translate(-50%,-50%) rotate(' + ((slot.rotation||0)) + 'deg)';
    el.style.display = 'flex';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';
    el.style.boxSizing = 'border-box';
    el.style.padding = '0 4px';
    el.style.overflow = 'hidden';
    el.style.pointerEvents = 'none';
    el.style.zIndex = (slotKey === 'number' ? 60 : 50);

    // compute font size (balanced by area height and by width chars)
    const text = (el.textContent || '').toString().trim() || 'TEXT';
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaH * (slotKey === 'number' ? (isMobile ? 1.05 : 1.0) : 1.0));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaW * 0.95) / (chars * avgCharRatio));
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap));
    const maxAllowed = Math.max(12, Math.floor(r.stageWidth * (isMobile ? 0.45 : 0.32)));
    fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
    fontSize = Math.floor(fontSize * 1.05);
    el.style.fontSize = fontSize + 'px';
    el.style.lineHeight = '1';
    el.style.fontWeight = '700';

    // final shrink loop if overflow
    let attempts = 0;
    while (el.scrollWidth > el.clientWidth && fontSize > 7 && attempts < 30) {
      fontSize = Math.max(7, Math.floor(fontSize * 0.92));
      el.style.fontSize = fontSize + 'px';
      attempts++;
    }
  }

  function applyLayout() {
    if (layout && layout.name) placeOverlay(pvName, layout.name, 'name');
    if (layout && layout.number) placeOverlay(pvNum, layout.number, 'number');
  }

  function applyFont(val) {
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    pvName.className = 'np-overlay ' + cls;
    pvNum.className  = 'np-overlay ' + cls;
  }

  function syncPreview() {
    pvName.textContent = (nameEl.value || 'NAME').toUpperCase();
    pvNum.textContent  = (numEl.value || '09').replace(/\D/g,'');
    applyLayout();
  }

  // wiring
  nameEl.addEventListener('input', ()=> { syncPreview(); });
  numEl.addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); });
  fontEl.addEventListener('change', ()=> { applyFont(fontEl.value); syncPreview(); });
  colorEl.addEventListener('input', ()=> { pvName.style.color = colorEl.value; pvNum.style.color = colorEl.value; });

  document.querySelectorAll('.np-swatch').forEach(b=>{
    b.addEventListener('click', ()=>{
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      colorEl.value = b.dataset.color;
      pvName.style.color = b.dataset.color;
      pvNum.style.color = b.dataset.color;
    });
  });

  // make sure layout runs after image & fonts ready
  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', debounce(()=> applyLayout(), 120));
  window.addEventListener('orientationchange', ()=> setTimeout(applyLayout, 200));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  // small debounce helper
  function debounce(fn, wait){ let t; return function(){ clearTimeout(t); t = setTimeout(fn, wait); }; }

  // initial state
  applyFont(fontEl.value || 'bebas');
  pvName.style.color = colorEl.value || '#D4AF37';
  pvNum.style.color  = colorEl.value || '#D4AF37';
  syncPreview();

  // mobile add-to-cart float handler (keeps button visible)
  function moveButtonToStage() {
    if (!btn) return;
    const isMobile = window.innerWidth <= 767;
    if (isMobile) btn.classList.add('mobile-fixed'); else btn.classList.remove('mobile-fixed');
  }
  window.addEventListener('load', moveButtonToStage);
  window.addEventListener('resize', debounce(moveButtonToStage, 120));
  window.addEventListener('orientationchange', ()=> setTimeout(moveButtonToStage, 200));
})();
</script>

</body>
</html>
