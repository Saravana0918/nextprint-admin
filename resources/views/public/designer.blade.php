<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* --- core --- */
    .np-hidden { display: none !important; }
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; min-height: 220px; overflow: visible; background: #fff; border-radius:8px; padding:8px; }
    .np-stage img { width: 100%; height: auto; display:block; border-radius:6px; }
    .np-overlay { position:absolute; color:#D4AF37; text-shadow: 0 2px 6px rgba(0,0,0,0.35); white-space:nowrap; pointer-events:none; font-weight:700; text-transform:uppercase; letter-spacing:2px; display:flex; align-items:center; justify-content:center; user-select:none; line-height:1; }
    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .max-count{ display:none; }

    /* -------------------- MOBILE ONLY (<=767px) -------------------- */
@media (max-width: 767px) {
  body {
    background-image: url('/images/stadium-bg.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    position: relative;
    min-height: 100vh;
  }

  body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.55); /* darkness control */
    z-index: 0;
  }

  .container, .row, .np-col, .np-stage, .border {
    position: relative;
    z-index: 1;
  }

  .np-overlay {
    color: #FFD700;
    text-shadow: 0 2px 8px rgba(0,0,0,0.65);
    pointer-events: auto; /* allow tapping */
    cursor: text; /* hint user can type */
  }

  .font-label{ color : white; }
  .color-label{ color: white; }

  .row.g-4 { display:flex; flex-direction:column; gap:14px; align-items:stretch; }

  .np-col.order-1.order-md-2 { order:-1; width:100% !important; max-width:380px !important; margin:0 auto; display:block !important; }
  .np-stage { max-width:340px; margin:0 auto; background:#fff; padding:10px; border-radius:10px; }

  .col-md-3.order-3.order-md-3 { display:none !important; }

  /* Controls box transparent so body background shows */
  .col-md-3.order-2.order-md-1 {
    display:block !important;
    width:100% !important;
    max-width:none !important;
    margin:0 auto;
  }
  .col-md-3.order-2.order-md-1 .border {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
  }

  /* Hide helper texts that would clutter */
  .col-md-3.order-2.order-md-1 h6,
  #np-status,
  #np-note,
  #np-num-help,
  #np-name-help {
    display: none !important;
  }

  /* Style the visible decorative inputs area but we will actually KEEP "real" inputs offscreen */
  .np-field-wrap { position:relative; margin-bottom:20px; }
  .np-field-wrap .visual-field {
    display:block;
    width:100%;
    border: 2px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    padding: 14px 12px;
    color: #fff;
    text-align:center;
    font-weight:700;
    letter-spacing: 2px;
    text-transform: uppercase;
    background: rgba(0,0,0,0.18);
  }
  .np-field-wrap .visual-field.placeholder { color: rgba(255,255,255,0.6) }

  .customization-form .form-select { background:#fff; color:#222; border-radius:8px; margin-bottom:16px; }

  .np-swatch { width:28px; height:28px; border-radius:50%; border:2px solid #fff; margin-right:6px; }

  #np-atc-btn { display:block !important; width:100% !important; font-size:16px !important; padding:12px 14px !important; margin-top:10px; }

  /* =================== KEY: hide real inputs offscreen but keep them focusable =================== */
  /* move the real inputs far off-screen so browser won't scroll them into view */
  #np-name, #np-num {
    position: absolute !important;
    left: -9999px !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    pointer-events: none !important;
    overflow: hidden !important;
  }
  /* keep color and font select visible and usable */
}

    /* -------------------- END MOBILE ONLY -------------------- */
  </style>
</head>
<body class="py-4">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <!-- preview column (desktop large) -->
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" src="{{ $img }}" alt="Preview" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <!-- overlays (clickable on mobile) -->
          <div id="np-prev-name" class="np-overlay np-name font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay np-num  font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <!-- controls column -->
    <div class="col-md-3 np-col order-2 order-md-1">
      <div class="border rounded p-3">
        <h6 class="mb-3">Customize</h6>
        <div id="np-status" class="small text-muted mb-2">Checking methods…</div>
        <div id="np-note" class="small text-muted mb-3 d-none">Personalization not available for this product.</div>

        <!-- controls -->
        <div id="np-controls" class="np-hidden customization-form">

          <!-- VISUAL NUMBER (mobile shows this box; clicking it will focus real hidden input) -->
          <div class="mb-3 np-field-wrap">
            <div id="visual-num" class="visual-field placeholder">YOUR NUMBER</div>
            <input id="np-num" type="text" inputmode="numeric" maxlength="3" class="form-control" placeholder="Your number" autocomplete="off">
            <span class="max-count">MAX. 2</span>
            <div id="np-num-help" class="form-text">Digits only. 1–3 digits.</div>
            <div id="np-num-err" class="text-danger small d-none">Enter 1–3 digits only.</div>
          </div>

          <!-- VISUAL NAME -->
          <div class="mb-3 np-field-wrap">
            <div id="visual-name" class="visual-field placeholder">YOUR NAME</div>
            <input id="np-name" type="text" maxlength="12" class="form-control" placeholder="Your name" autocomplete="off">
            <span class="max-count">MAX. 11</span>
            <div id="np-name-help" class="form-text">Only A–Z and spaces. 1–12 chars.</div>
            <div id="np-name-err" class="text-danger small d-none">Enter 1–12 letters/spaces only.</div>
          </div>

          <!-- Font select -->
          <div class="mb-3">
            <label class="form-label font-label">Font</label>
            <select id="np-font" class="form-select">
              <option value="bebas">Bebas Neue (Bold)</option>
              <option value="anton">Anton</option>
              <option value="oswald">Oswald</option>
              <option value="impact">Impact</option>
            </select>
          </div>

          <!-- Color swatches -->
          <div class="mb-2">
            <label class="form-label d-block color-label">Text Color</label>
            <div class="d-flex gap-2 flex-wrap mb-2">
              <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFFFFF"></button>
              <button type="button" class="np-swatch" data-color="#000000" style="background:#000000"></button>
              <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
              <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
              <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
            </div>
            <input id="np-color" type="color" class="form-control form-control-color mt-2" value="#D4AF37" title="Pick color">
          </div>
        </div>
      </div>
    </div>

    <!-- right product column (desktop) -->
    <div class="col-md-3 np-col order-3 order-md-3">
      <h4 class="mb-1">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <div class="text-muted mb-3">Vendor: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($product->min_price ?? 0), 2) }}</div>

      <form id="np-atc-form" method="post" action="#">
        @csrf
        <input type="hidden" id="np-product-id" value="{{ $product->id }}">
        <input type="hidden" id="np-shopify-id" value="{{ $product->shopify_product_id ?? '' }}">
        <input type="hidden" id="np-method" value="ADD TEXT">

        <input type="hidden" name="name_text"     id="np-name-hidden">
        <input type="hidden" name="number_text"   id="np-num-hidden">
        <input type="hidden" name="selected_font" id="np-font-hidden">
        <input type="hidden" name="text_color"    id="np-color-hidden">

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled aria-busy="false">Add to Cart</button>
      </form>
      <div class="small-delivery text-muted mt-2">Button enables when both Name & Number are valid.</div>
    </div>
  </div>
</div>

{{-- inject JSON from server-side normalized layoutSlots --}}
<script>
  window.layoutSlots = {!! json_encode($layoutSlots, JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
    const visualName = $('visual-name'), visualNum = $('visual-num');
    const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');
    const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

    const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;
    function validate(){
      const okName = nameEl ? NAME_RE.test((nameEl.value||'').trim()) : true;
      const okNum = numEl ? NUM_RE.test((numEl.value||'').trim()) : true;
      if (nameEl) document.getElementById('np-name-err')?.classList.toggle('d-none', okName);
      if (numEl)  document.getElementById('np-num-err')?.classList.toggle('d-none', okNum);
      if (ctrls && !ctrls.classList.contains('np-hidden')) {
        if (btn) btn.disabled = !(okName && okNum);
      }
      return okName && okNum;
    }

    function applyFont(val){
      const classes = ['font-bebas','font-anton','font-oswald','font-impact'];
      if (pvName) pvName.classList.remove(...classes);
      if (pvNum) pvNum.classList.remove(...classes);
      if (visualName) visualName.classList.remove(...classes);
      if (visualNum) visualNum.classList.remove(...classes);
      const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
      const c = map[val] || 'font-bebas';
      if (pvName) pvName.classList.add(c);
      if (pvNum) pvNum.classList.add(c);
      if (visualName) visualName.classList.add(c);
      if (visualNum) visualNum.classList.add(c);
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

      const heightFactorName = 0.86;
      const heightFactorNumber = isMobile ? 0.95 : 0.9;

      const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? heightFactorNumber : heightFactorName));

      const avgCharRatio = 0.55;
      const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));

      let numericShrink = 1.0;
      if (slotKey === 'number') {
        numericShrink = isMobile ? 1.0 : 0.98;
      }

      let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * numericShrink);

      const maxAllowed = Math.max(14, Math.floor(stageW * (isMobile ? 0.34 : 0.18)));
      fontSize = Math.max(8, Math.min(fontSize, maxAllowed));

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
        pvName.textContent = txt || 'YOUR NAME';
      }
      if (pvNum && numEl) {
        const numTxt = (numEl.value||'').replace(/\D/g,'');
        pvNum.textContent = numTxt || 'YOUR NUMBER';
      }

      // also update the visual placeholders visible inside the controls area (mobile)
      if (visualName) {
        visualName.textContent = (nameEl && nameEl.value) ? nameEl.value.toUpperCase() : 'YOUR NAME';
        visualName.classList.toggle('placeholder', !(nameEl && nameEl.value));
      }
      if (visualNum) {
        visualNum.textContent = (numEl && numEl.value) ? (numEl.value.replace(/\D/g,'')) : 'YOUR NUMBER';
        visualNum.classList.toggle('placeholder', !(numEl && numEl.value));
      }

      applyLayout();
    }

    function syncHidden(){
      const set = (id, v) => { const el = $(id); if (el) el.value = v; };
      set('np-name-hidden', (nameEl ? (nameEl.value||'') : '').toUpperCase().trim());
      set('np-num-hidden',  (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim());
      set('np-font-hidden', fontEl ? fontEl.value : '');
      set('np-color-hidden', colorEl ? colorEl.value : '');
    }

    // --- click handlers so tapping overlay or visual box focuses real (hidden/offscreen) input ---
    function focusInputQuiet(el){
      if (!el) return;
      // try to focus without scrolling the viewport
      try {
        el.focus({preventScroll: true});
      } catch(e){
        // fallback
        el.focus();
        // attempt to restore scroll if browser scrolled: scroll stage into view
        try { document.getElementById('np-stage').scrollIntoView({behavior:'smooth', block:'center'}); } catch(err) {}
      }
    }

    // overlay click -> focus name/num input (mobile friendly)
    if (pvName) {
      pvName.style.pointerEvents = 'auto';
      pvName.addEventListener('click', function(e){
        e.preventDefault();
        focusInputQuiet(nameEl);
      });
    }
    if (pvNum) {
      pvNum.style.pointerEvents = 'auto';
      pvNum.addEventListener('click', function(e){
        e.preventDefault();
        focusInputQuiet(numEl);
      });
    }

    // also make the visual controls boxes clickable (so user taps visual box instead of hidden input)
    if (visualName) {
      visualName.addEventListener('click', function(e){
        e.preventDefault();
        focusInputQuiet(nameEl);
      });
    }
    if (visualNum) {
      visualNum.addEventListener('click', function(e){
        e.preventDefault();
        focusInputQuiet(numEl);
      });
    }

    // live update handlers
    if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
    if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
    if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
    if (colorEl) colorEl.addEventListener('input', ()=>{ if (pvName) pvName.style.color = colorEl.value; if (pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

    document.querySelectorAll('.np-swatch')?.forEach(b=>{ b.addEventListener('click', ()=>{ document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active')); b.classList.add('active'); if (colorEl) colorEl.value = b.dataset.color; if (pvName) pvName.style.color = b.dataset.color; if (pvNum) pvNum.style.color = b.dataset.color; syncHidden(); }); });

    applyFont(fontEl ? fontEl.value : 'bebas');
    if (pvName && colorEl) pvName.style.color = colorEl.value;
    if (pvNum && colorEl) pvNum.style.color = colorEl.value;
    syncPreview(); syncHidden();

    if (baseImg) baseImg.addEventListener('load', applyLayout);
    window.addEventListener('resize', applyLayout);
    setTimeout(applyLayout, 200);
    setTimeout(applyLayout, 800);

    if (typeof window.personalizationSupported !== 'undefined' && ctrls) {
      if (window.personalizationSupported) {
        status.textContent = 'Personalization supported.';
        note.classList.add('d-none');
        ctrls.classList.remove('np-hidden');
        if (btn) btn.disabled = true;
      } else {
        status.textContent = 'Personalization not available.';
        note.classList.remove('d-none');
        ctrls.classList.add('np-hidden');
        if (btn) btn.disabled = false;
      }
    }

    const atcForm = $('np-atc-form');
    if (atcForm) {
      atcForm.addEventListener('submit', function(e){
        if (ctrls && !ctrls.classList.contains('np-hidden')) {
          if (!validate()) {
            e.preventDefault();
            alert('Please enter a valid Name (A–Z, 1–12) and Number (1–3 digits).');
            return;
          }
        }
        if (btn) btn.setAttribute('aria-busy','true');
        syncHidden();
      });
    }
  } // init

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>

</body>
</html>
