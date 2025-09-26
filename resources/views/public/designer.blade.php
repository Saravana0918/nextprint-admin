<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* base fonts */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    /* preview stage */
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position:absolute; color:#D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; text-shadow:0 2px 6px rgba(0,0,0,0.35); display:flex; align-items:center; justify-content:center; pointer-events:none; }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    .max-count{ display:none; }

    /* ---------- Desktop layout: icons + panels (panels positioned beside icons) ---------- */
    .vertical-tabs { display:flex; gap:12px; align-items:flex-start;}
    .vt-icons { display:flex; flex-direction:column; gap:102px; flex:0 0 56px; align-items:center; padding-top:6px; }
    .vt-btn { display:flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:8px; border:1px solid #e6e6e6; background:#fff; cursor:pointer; }
    .vt-btn .vt-ico { font-size:18px; line-height:1; }
    .vt-btn.active { background:#f5f7fb; box-shadow:0 6px 18px rgba(10,20,40,0.04); border-color:#dbe7ff; }
    .vt-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(100,150,255,0.12); }

    /* panels container is relative; panels absolute (desktop only) */
    .vt-panels { flex:1 1 auto; min-width:0; position: relative; }
     
    .vt-panel.active { display: block; opacity: 1; transform: translateY(0); }

    .vt-panel h6 { margin: 0 0 6px 0; font-size:14px; font-weight:600; }
    .vt-panel .form-text { margin-top: 4px; color: #6c757d; font-size: 12px; }

    /* make input heights comfortable */
    .vt-panel input.form-control, .vt-panel select.form-select, .vt-panel .form-control-color {
      min-height: 40px;
    }

    /* ensure swatches block stays inside the panel and is placed nicely */
    .vt-panel .swatches-wrap { margin-top: 8px; display:block; }
    body { background-color : #929292; }
  .desktop-display{ color:white; font-family: "Roboto Condensed", sans-serif; font-weight: bold; }
  .body-padding{ padding-top: 100px; }
  .right-layout{ padding-top:225px; }
  .hide-on-mobile { display: none !important; }

    /* small screens: revert to stacked flow (mobile rules unchanged) */
    @media (max-width: 767px) {
     .vertical-tabs { display:block; }
     .vt-icons { display:flex; flex-direction:row; gap:8px; margin-bottom:8px; display:none;}
    .vt-btn { width:40px; height:40px; }
      /* On mobile, panels should behave as normal block elements (flow) */
      .vt-panels { position: static; }
      .vt-panel { position: static; left: auto; width: 100%; display: block !important; opacity:1 !important; transform:none !important; padding: 8px 0; background: transparent; border: none; box-shadow: none; }
      .col-md-3.np-col > #np-controls { min-height: auto; padding: 12px !important; }
    }
    
    @media (max-width: 767px) {

  /* 1) body stadium background + full-screen tint (below UI) */
  body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; position: relative; margin-top: -70px; }
  body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); /* tweak 0.22-0.36 */ z-index: 5; pointer-events: none; }

  /* ensure UI sits above body tint */
  .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }

  /* 2) np-stage & image frame (visible pale border around t-shirt) */
  .np-stage { padding: 12px; background: transparent; box-sizing: border-box; border-radius: 10px;  z-index: 100; position: relative !important; }
  
   #np-atc-btn.mobile-fixed { position: absolute !important; top: -40px !important; right: -25px !important; z-index: 99999 !important; width: auto !important; min-width: 110px !important; height: 40px !important; padding: 6px 12px !important; border-radius: 24px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; white-space: nowrap !important; }
  #np-atc-btn.mobile-fixed-outside { position: fixed !important; top: 12px !important; right: 12px !important; z-index: 99999 !important; }
  /* style the base image to look like framed box */
  .np-stage img#np-base { display:block; width:100%; height:auto; border-radius:8px;   background-color:#f6f6f6; box-shadow: 0 6px 18px rgba(0,0,0,0.35); border: 3px solid rgba(255,255,255,0.12); position: relative; z-index: 14; }
  /* subtle overlay inside frame to keep overlays readable */
  .np-stage::after { content: ""; position: absolute; left: 12px; right: 12px; top: 12px; bottom: 12px; border-radius: 8px; background: rgba(0,0,0,0.06); z-index: 15; pointer-events: none; }

  /* 3) mobile-only small header shown over image */
  .np-mobile-head { display: block !important; position: absolute; top: 8px; left: 14px; right: 14px; z-index: 22; color: #fff; text-shadow: 0 3px 8px rgba(0,0,0,0.7); font-weight: 700; font-size: 13px; text-transform: uppercase; pointer-events: none; }

  /* 4) overlays (name & number) default centered */
  #np-prev-name, #np-prev-num { z-index: 24; position: absolute; left: 50% !important; transform: translateX(-50%) !important; width: 90% !important; text-align: center !important; color: #fff; text-shadow: 0 3px 8px rgba(0,0,0,0.7); pointer-events: none; }

  /* 5) INPUTS: name & number styles (underline only, centered, MAX tag on right) */
  .np-field-wrap.name-input,
  .np-field-wrap.number-input { position: relative; text-align: center; margin: 18px 0; }

  .np-field-wrap.name-input input.form-control,
  .np-field-wrap.number-input input.form-control { background: transparent; border: none; border-bottom: 2px solid #fff; color: #fff; text-align: center; text-transform: uppercase; font-weight: 800; box-shadow: none; }

  /* font-size: number larger than name */
  .np-field-wrap.number-input input.form-control { font-size: clamp(18px, 5.6vw, 32px); }
  .np-field-wrap.name-input input.form-control   { font-size: clamp(18px, 5.6vw, 32px); }

  /* placeholder color */
  .np-field-wrap.name-input input.form-control::placeholder,
  .np-field-wrap.number-input input.form-control::placeholder { color: rgba(255,255,255,0.45); font-weight: 400; }

  /* MAX label (right below input, aligned right) */
  .np-field-wrap.name-input .max-count,
  .np-field-wrap.number-input .max-count { display: block; position: absolute; right: 8px; bottom: -18px; color: #fff; font-weight:700; font-size:12px; }

   .np-field-wrap { position: relative; width:100%; }

  /* 6) keep helper text legible */
  .np-field-wrap .form-text, .small-delivery { color: rgba(255,255,255,0.9); display : none}

  /* 7) hide desktop-only bits with this class */
  .hide-on-mobile { display: none !important; }

  /* 8) make Add to Cart visible */
  #np-atc-btn { position: fixed !important; top: 12px !important;        /* distance from top — adjust */ right: 12px !important;      /* distance from right — adjust */ z-index: 99999 !important; width: 130px !important;     /* button width on mobile */ height: 44px !important; padding: 6px 12px !important;
  border-radius: 28px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; font-weight: 700 !important; white-space: nowrap !important;
}

  /* 9) mobile large overlay styles (apply via JS .mobile-style) */
  #np-prev-name.mobile-style { top: 18px !important; font-weight: 800 !important; font-size: clamp(18px, 5.6vw, 34px) !important; letter-spacing: 1.5px !important; }
  #np-prev-num.mobile-style { top: 52% !important; transform: translate(-50%,-50%) !important; font-weight: 900 !important; font-size: clamp(28px, 8.4vw, 56px) !important; }

  /* small utility */
  .mobile-display { display: none; }  
  .color-display  { color: #fff; }
  .right-layout{ margin-top: -210px; }
    
  .np-stage.covering { pointer-events: none; -webkit-user-select: none; }

  .np-stage.covering .np-overlay { pointer-events: none; }

/* keep Add to Cart clickable even when stage pointer-events:none */
#np-atc-btn {
  /* default mobile styling you already have */
}
.np-stage.covering #np-atc-btn { pointer-events: auto !important; z-index: 999999 !important;}

/* Ensure controls (swatches, font select) have higher stacking when needed */
.swatches-wrap, .vt-panels, .vt-panel, #panel-font { position: relative; z-index: 100000 !important;}

/* If font select is a native <select>, ensure it is not visually hidden behind stage */
#np-font, .np-swatch, #np-color { position: relative; z-index: 100001;}

}
@media (min-width: 768px) {
  .vt-panels .vt-panel { display: block !important; opacity: 1 !important; position: static !important; transform: none !important; width: 100% !important; margin-bottom: 12px; padding: 12px !important; }
  .vt-icons { display: none !important; }
}
    /* optional styling for the left wrapper */
    .col-md-3.np-col > #np-controls { padding: 16px !important; box-sizing: border-box; min-height: 360px; }

  </style>
</head>
<body class="body-padding">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <!-- center preview -->
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

    <!-- left controls (icons + panels) -->
    <div class="col-md-3 np-col order-2 order-md-1">
      <div id="np-controls" class="border rounded p-3">
        <div class="vertical-tabs">
          <nav class="vt-icons" aria-hidden="false" role="tablist" aria-orientation="vertical">
            <button class="vt-btn active" data-panel="panel-name" aria-controls="panel-name" title="Name"><span class="vt-ico">②</span></button>
            <button class="vt-btn" data-panel="panel-number" aria-controls="panel-number" title="Number"><span class="vt-ico">①</span></button>
            <button class="vt-btn" data-panel="panel-font" aria-controls="panel-font" title="Font"><span class="vt-ico">𝙰</span></button>
            <button class="vt-btn" data-panel="panel-color" aria-controls="panel-color" title="Color"><span class="vt-ico">⚪</span></button>
          </nav>

         <div class="vt-panels" aria-live="polite">
            <!-- Name panel -->
            <div id="panel-name" class="vt-panel" role="region" aria-hidden="true">
              <h6 class="hide-on-mobile">Name</h6>
              <div>
                <div class="np-field-wrap name-input">
                  <input id="np-name" type="text" maxlength="12"
                        class="form-control text-center" placeholder="YOUR NAME">
                  <div class="form-text small hide-on-mobile">Only A–Z and spaces. 1–12 chars.</div>
                  <span class="max-count">MAX. 12</span>
                </div>
              </div>
            </div>
            <!-- Number panel -->
            <div id="panel-number" class="vt-panel" role="region" aria-hidden="true">
              <h6 class="hide-on-mobile">Number</h6>
              <div>
                <!-- wrapper kept simple so mobile mover JS can pick and move this exact input node -->
                <div class="np-field-wrap number-input">
                  <input id="np-num" type="text" maxlength="3" inputmode="numeric"
                        class="form-control text-center" placeholder="YOUR NUMBER">
                  <!-- helper text (desktop) -->
                  <div class="form-text small hide-on-mobile">Digits only. 1–3 digits.</div>
                  <!-- MAX label (visible on mobile via CSS) -->
                  <span class="max-count">MAX. 3</span>
                </div>
              </div>
            </div>

            <!-- Font panel -->
            <div id="panel-font" class="vt-panel" role="region" aria-hidden="true">
              <h6 class="hide-on-mobile">Font</h6>
              <div>
                <select id="np-font" class="form-select">
                  <option value="bebas">Bebas Neue</option>
                  <option value="anton">Anton</option>
                  <option value="oswald">Oswald</option>
                  <option value="impact">Impact</option>
                </select>
              </div>
            </div>

            <!-- Color panel -->
            <div id="panel-color" class="vt-panel" role="region" aria-hidden="true">
              <h6 class="hide-on-mobile">Text Color</h6>
              <div class="swatches-wrap">
                <div class="d-flex gap-2 flex-wrap mb-2">
                  <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF"></button>
                  <button type="button" class="np-swatch" data-color="#000000" style="background:#000000"></button>
                  <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
                  <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
                  <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
                </div>
                <input id="np-color" type="color" class="form-control form-control-color" value="#D4AF37">
              </div>
            </div>
          </div> <!-- vt-panels -->
        </div> <!-- vertical-tabs -->
      </div> <!-- np-controls -->
    </div> <!-- left col -->

    <!-- right purchase column -->
    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="mb-1 mobile-display desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <div class="text mb-3 mobile-display desktop-display">Price: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($displayPrice ?? ($product->min_price ?? 0)), 2) }}</div>

      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" name="preview_data" id="np-preview-hidden">
        <!-- required hidden fields -->
        <input type="hidden" name="product_id" id="np-product-id" value="{{ $product->id ?? $product->local_id ?? '' }}">
        <input type="hidden" name="shopify_product_id" id="np-shopify-product-id" value="{{ $product->shopify_product_id ?? $product->shopify_id ?? '' }}">
        <input type="hidden" name="variant_id" id="np-variant-id" value="">

        <div class="mb-3">
          <label class="form-label color-display desktop-display">Size</label>
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="XXL">XXL</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label color-display desktop-display">Quantity</label>
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled>Add to Cart</button>
      </form>

      <div class="small-delivery text mt-2 desktop-display">Button enables when both Name & Number are valid.</div>
    </div>
  </div>
</div>

{{-- server-provided layoutSlots --}}
<script> window.layoutSlots = {!! json_encode($layoutSlots, JSON_NUMERIC_CHECK) !!}; window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }}; </script>

 

{{-- core preview + UI JS (validation + preview layout) --}}
<script>
(function(){
  const $ = id => document.getElementById(id);

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
    const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');
    const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

    const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

    function validate(){
      const okName = nameEl ? NAME_RE.test((nameEl.value||'').trim()) : true;
      const okNum = numEl ? NUM_RE.test((numEl.value||'').trim()) : true;
      if (nameEl) document.getElementById('np-name-err')?.classList.toggle('d-none', okName);
      if (numEl)  document.getElementById('np-num-err')?.classList.toggle('d-none', okNum);
      if (ctrls && !ctrls.classList.contains('np-hidden')) {
        if (btn) btn.disabled = !(okName && okNum && document.getElementById('np-size')?.value);
      } else {
        if (btn) btn.disabled = !(document.getElementById('np-size')?.value);
      }
      return okName && okNum;
    }

    function applyFont(val){
      const classes = ['font-bebas','font-anton','font-oswald','font-impact'];
      if (pvName) pvName.classList.remove(...classes);
      if (pvNum) pvNum.classList.remove(...classes);
      const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
      const c = map[val] || 'font-bebas';
      if (pvName) pvName.classList.add(c);
      if (pvNum) pvNum.classList.add(c);
    }

    function computeStageSize(){
      const imgW = (baseImg && baseImg.naturalWidth) ? baseImg.naturalWidth : (baseImg? baseImg.width : stage.clientWidth);
      const imgH = (baseImg && baseImg.naturalHeight) ? baseImg.naturalHeight : (baseImg? baseImg.clientHeight : stage.clientWidth);
      const stageW = stage.clientWidth || 300;
      const stageH = imgW ? Math.round((imgH * stageW)/imgW) : (baseImg? baseImg.clientHeight : 300);
      return {imgW, imgH, stageW, stageH};
    }

    function placeOverlay(el, slot, slotKey){
  if(!el || !slot || !stage) return;
  el.style.position = 'absolute';
  el.style.left = (slot.left_pct||0) + '%';
  el.style.top  = (slot.top_pct||0) + '%';
  el.style.width = (slot.width_pct||10) + '%';
  el.style.height = (slot.height_pct||10) + '%';
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.justifyContent = 'center';
  el.style.boxSizing = 'border-box';
  el.style.padding = '0 4px';
  el.style.transform = 'rotate(' + ((slot.rotation||0)) + 'deg)';
  el.style.whiteSpace = 'nowrap';
  el.style.overflow = 'hidden';
  el.style.pointerEvents = 'none';
  el.style.zIndex = (slotKey === 'number' ? 60 : 50);

  const {imgW, imgH, stageW, stageH} = computeStageSize();
  const areaWpx = Math.max(8, Math.round(((slot.width_pct || 10)/100) * stageW));
  const areaHpx = Math.max(8, Math.round(((slot.height_pct || 10)/100) * stageH));

  const text = (el.textContent || '').toString().trim() || 'TEXT';
  const chars = Math.max(1, text.length);

  const isMobile = window.innerWidth <= 767;

  // Increase these to get larger text:
  const heightFactorName = 1.00;    // was 0.86 — raise to make name taller
  const heightFactorNumber = isMobile ? 1.05 : 1.00; // slightly bigger numbers on mobile

  const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? heightFactorNumber : heightFactorName));

  // Assume characters take a bit less horizontal space (allow bigger font)
  const avgCharRatio = 0.48; // was 0.55, lowering this increases font allowed by width
  const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));

  // slight boost for numbers on desktop
  let numericShrink = 1.0;
  if (slotKey === 'number') numericShrink = isMobile ? 1.0 : 0.98;

  // compute font size and allow bigger maximum relative to stage width
  let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * numericShrink);

  // raise maxAllowed so name/number can grow more: increase multiplier (0.32 -> 0.38 etc)
  const maxAllowed = Math.max(14, Math.floor(stageW * (isMobile ? 0.45 : 0.32)));

  fontSize = Math.max(8, Math.min(fontSize, maxAllowed));

  // final nudge multiplier to make overlays a bit larger overall (tweak 1.0 -> 1.2)
  fontSize = Math.floor(fontSize * 1.10);

  el.style.fontSize = fontSize + 'px';
  el.style.lineHeight = '1';
  el.style.fontWeight = '700';

  let attempts = 0;
  while (el.scrollWidth > el.clientWidth && fontSize > 7 && attempts < 30) {
    fontSize = Math.max(7, Math.floor(fontSize * 0.92));
    el.style.fontSize = fontSize + 'px';
    attempts++;
  }
}

    function applyLayout(){
      if (!baseImg) return;
      if (!baseImg.complete || !baseImg.naturalWidth) return;
      if (layout.name) placeOverlay(pvName, layout.name, 'name');
      if (layout.number) placeOverlay(pvNum, layout.number, 'number');
    }

    function syncPreview(){
      if (pvName && nameEl) {
        const txt = (nameEl.value||'').toUpperCase();
        pvName.textContent = txt || 'NAME';
      }
      if (pvNum && numEl) {
        const numTxt = (numEl.value||'').replace(/\D/g,'');
        pvNum.textContent = numTxt || 'NUMBER';
      }
      applyLayout();
    }

    function syncHidden(){
      document.getElementById('np-name-hidden').value  = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
      document.getElementById('np-num-hidden').value   = (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim();
      document.getElementById('np-font-hidden').value  = fontEl ? fontEl.value : '';
      document.getElementById('np-color-hidden').value = colorEl ? colorEl.value : '';
    }

    if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
    if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
    if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
    if (colorEl) colorEl.addEventListener('input', ()=>{ if (pvName) pvName.style.color = colorEl.value; if (pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

    document.querySelectorAll('.np-swatch')?.forEach(b=>{
      b.addEventListener('click', ()=>{
        document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        if (colorEl) colorEl.value = b.dataset.color;
        if (pvName) pvName.style.color = b.dataset.color;
        if (pvNum) pvNum.style.color = b.dataset.color;
        syncHidden();
      });
    });

    applyFont(fontEl ? fontEl.value : 'bebas');
    if (pvName && colorEl) pvName.style.color = colorEl.value;
    if (pvNum && colorEl) pvNum.style.color = colorEl.value;
    syncPreview(); syncHidden();

    if (baseImg) baseImg.addEventListener('load', applyLayout);
    window.addEventListener('resize', applyLayout);
    setTimeout(applyLayout, 200);
    setTimeout(applyLayout, 800);

    // show/hide controls based on personalizationSupported flag
    if (typeof window.personalizationSupported !== 'undefined' && ctrls) {
      if (window.personalizationSupported) {
        status && (status.textContent = 'Personalization supported.');
        note && note.classList?.add('d-none');
        ctrls.classList.remove('np-hidden');
        if (btn) btn.disabled = true;
      } else {
        status && (status.textContent = 'Personalization not available.');
        note && note.classList?.remove('d-none');
        ctrls.classList.add('np-hidden');
        if (btn) btn.disabled = false;
      }
    }

    // wire up size change to revalidate
    document.getElementById('np-size')?.addEventListener('change', validate);
    document.getElementById('np-qty')?.addEventListener('input', validate);
  } // init

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

{{-- html2canvas + single submit handler --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(function(){
  const atcForm = document.getElementById('np-atc-form');
  const btn = document.getElementById('np-atc-btn');

  function syncHiddenFields(){
    const nameEl = document.getElementById('np-name'),
          numEl  = document.getElementById('np-num'),
          fontEl = document.getElementById('np-font'),
          colorEl= document.getElementById('np-color');

    document.getElementById('np-name-hidden').value  = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    document.getElementById('np-num-hidden').value   = (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim();
    document.getElementById('np-font-hidden').value  = fontEl ? fontEl.value : '';
    document.getElementById('np-color-hidden').value = colorEl ? colorEl.value : '';

    // ensure variant_id hidden is set from variantMap (if available)
    const size = document.getElementById('np-size')?.value || '';
    if (window.variantMap && size) {
      const vid = window.variantMap[size] || '';
      document.getElementById('np-variant-id').value = vid;
    }
  }

  async function postFormData(url, formData) {
    const token = document.querySelector('input[name="_token"]')?.value || '';
    const resp = await fetch(url, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json'
      }
    });
    return resp;
  }

  if (atcForm) {
    atcForm.addEventListener('submit', async function(evt){
      evt.preventDefault();

      // size & personalization validation
      const size = document.getElementById('np-size')?.value || '';
      if (!size) { alert('Please select a size.'); return; }

      const controlsHidden = document.getElementById('np-controls')?.classList.contains('np-hidden');
      if (!controlsHidden) {
        const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;
        const name = document.getElementById('np-name')?.value || '';
        const num  = document.getElementById('np-num')?.value || '';
        if (!NAME_RE.test(name.trim()) || !NUM_RE.test(num.trim())) {
          alert('Please enter valid Name (A–Z, 1–12) and Number (1–3 digits).');
          return;
        }
      }

      syncHiddenFields();
      if (btn) { btn.disabled = true; btn.setAttribute('aria-busy','true'); btn.innerText = 'Preparing...'; }

      try {
        const stage = document.getElementById('np-stage');
        const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
        const dataUrl = canvas.toDataURL('image/png');
        document.getElementById('np-preview-hidden').value = dataUrl;

        const formData = new FormData(atcForm);

        // ensure product_id present
        if (!formData.get('product_id') || formData.get('product_id') === '') {
          const pid = document.getElementById('np-product-id')?.value || '';
          if (pid) formData.set('product_id', pid);
        }

        const resp = await postFormData(atcForm.action, formData);

        // if server redirected (302) the fetch won't follow to external domain by default; prefer JSON
        if (resp.redirected) { window.location.href = resp.url; return; }

        let data = null;
        try { data = await resp.json(); } catch (err) { /* ignore */ }

        if (!resp.ok) {
          console.error('AddToCart failed', resp.status, data);
          const msg = (data && (data.error || data.message)) ? (data.error || data.message) : 'Something went wrong. Try again.';
          alert(msg);
          return;
        }

        if (data && data.checkoutUrl) {
          window.location.href = data.checkoutUrl;
          return;
        }

        console.error('AddToCart: unexpected response', data);
        alert('Something went wrong. Try again.');

      } catch (err) {
        console.error('ATC exception', err);
        alert('Something went wrong. Try again.');
      } finally {
        if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerText = 'Add to Cart'; }
      }
    });
  }
})();
</script>
<script>
(function(){
  const btn = document.getElementById('np-atc-btn');
  const stage = document.getElementById('np-stage');
  if (!btn || !stage) return;

  // placeholder to restore original location
  let placeholder = document.getElementById('np-atc-placeholder');
  if (!placeholder) {
    placeholder = document.createElement('div');
    placeholder.id = 'np-atc-placeholder';
    btn.parentNode.insertBefore(placeholder, btn);
  }

  function moveButtonToStage() {
    const isMobile = window.innerWidth <= 767;
    if (isMobile) {
      if (btn.parentNode !== stage) {
        stage.appendChild(btn);
        btn.classList.remove('mobile-fixed-outside');
        btn.classList.add('mobile-fixed');
      }
    } else {
      // restore to original place
      if (placeholder.parentNode) {
        placeholder.parentNode.insertBefore(btn, placeholder.nextSibling);
      }
      btn.classList.remove('mobile-fixed');
      btn.classList.remove('mobile-fixed-outside');
    }
  }

  // Keep stage fixed when keyboard opens, so it doesn't jump off-screen
  function setupKeyboardHandler() {
    if (!window.visualViewport) return;
    let lastHeight = window.visualViewport.height;
    window.visualViewport.addEventListener('resize', () => {
      const vH = window.visualViewport.height;
      const vhRatio = vH / window.innerHeight;
      // when keyboard opens, visualViewport.height shrinks heavily (< ~0.7)
      if (vhRatio < 0.75) {
        // keyboard opened
        // keep stage position relative to viewport so button stays with stage
        stage.style.position = 'fixed';
        // place stage near top so input still visible
        stage.style.top = '60px';
        stage.style.left = '50%';
        stage.style.transform = 'translateX(-50%)';
        stage.style.width = '100%';
        stage.style.maxWidth = '420px';
      } else {
        // keyboard closed — restore
        stage.style.position = '';
        stage.style.top = '';
        stage.style.left = '';
        stage.style.transform = '';
        stage.style.maxWidth = '';
      }
      lastHeight = vH;
    });
  }

  // initial + on resize/orientation
  window.addEventListener('load', moveButtonToStage);
  window.addEventListener('DOMContentLoaded', moveButtonToStage);
  window.addEventListener('resize', moveButtonToStage);
  window.addEventListener('orientationchange', () => setTimeout(moveButtonToStage, 200));

  // keyboard handler only on mobile
  if (window.innerWidth <= 767) setupKeyboardHandler();

})();
</script>

</body>
</html>
