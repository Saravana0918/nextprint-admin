<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .np-hidden { display: none !important; }
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 562px; margin: 0 auto; min-height: 220px; overflow: visible; background: #fff; }
    .np-stage img { width: 100%; height: auto; display:block; border-radius:6px; }
    .np-overlay { position:absolute; color:#D4AF37; text-shadow: 0 2px 6px rgba(0,0,0,0.35); white-space:nowrap; pointer-events:none; font-weight:700; text-transform:uppercase; letter-spacing:2px; display:flex; align-items:center; justify-content:center; user-select:none; line-height:1; }
    .np-swatch { width:28px; height:28px; border-radius:4px; border:1px solid #ccc; cursor:pointer; }

    /* ===== MOBILE SINGLE-COLUMN LAYOUT (ONLY <=767px) ===== */
    @media (max-width: 767px) {

      /* Make layout single column: show preview small on top, then controls */
      .row.g-4 { display: flex; flex-direction: column; gap: 12px; align-items: stretch; }

      /* small top preview card (centered) */
      .np-col.order-1.order-md-2 { order: -1; width: 100% !important; max-width: 380px !important; margin: 0 auto 8px; display: block !important; }
      .np-stage { max-width: 340px; margin: 0 auto; }

      /* hide right product info column completely on mobile */
      .col-md-3.order-3.order-md-3 { display: none !important; }

      /* Make controls full width below preview */
      .col-md-3.order-2.order-md-1 {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 auto;
      }

      /* Style the controls card like your screenshot */
      .col-md-3.order-2.order-md-1 .border {
      position: relative;
      padding: 18px !important;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: none;
      /* fallback color */
      background-color: #073a68;
    }

    .col-md-3.order-2.order-md-1 .border::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image: url('/images/stadium-bg.jpg'); /* <-- change path if needed */
      background-size: cover;
      background-position: center center;
      background-repeat: no-repeat;
      opacity: 0.95;
      z-index: 0;
      transform: translateZ(0);
    }

    .col-md-3.order-2.order-md-1 .border::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(7,58,104,0.88) 0%, rgba(13,103,40,0.88) 100%);
      z-index: 1;
    }

    .col-md-3.order-2.order-md-1 .border > * {
      position: relative;
      z-index: 2;
      color: #fff;
    }

      /* White rounded input boxes with centered placeholder like screenshot */
      #np-name, #np-num {
        background: rgba(255,255,255,0.06);
        border: 2px solid rgba(255,255,255,0.15);
        color: #fff;
        border-radius: 8px;
        padding: 14px 12px;
        text-align: center;
        font-weight:700;
        letter-spacing: 2px;
        font-size: 16px;
        text-transform: uppercase;
      }
      #np-name::placeholder, #np-num::placeholder { color: rgba(255,255,255,0.45); text-transform:uppercase; }

      /* Hide help text/extra small notes */
      #np-name-help, #np-num-help, #np-name-err, #np-num-err, #np-font { display: none !important; }
      .np-swatch, #np-color { display: none !important; }

      /* Labels shown above fields as small uppercase (like your screenshot) */
      label[for="np-name"], label[for="np-num"] {
        display:block;
        color: rgba(255,255,255,0.85);
        font-size: 12px;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
      }

      /* max-count small yellow label to right (we will add small absolute spans) */
      .max-count {
        color: #ffd24d;
        position: absolute;
        right: 18px;
        top: 22px;
        font-size: 11px;
        font-weight:700;
      }

      /* Input wrappers to allow relative positioning for max-count */
      .np-field-wrap { position: relative; margin-bottom: 18px; }

      /* Make Add to Cart appear below controls as full width CTA */
      #np-atc-btn {
        display: block !important;
        width: 100% !important;
        font-size: 16px !important;
        padding: 12px 14px !important;
        margin-top: 10px;
      }

      /* small supporting text below CTA */
      .small-delivery { color: rgba(255,255,255,0.85); font-size:13px; margin-top:10px; text-align:center; }

      /* small polish: white placeholder for empty preview name */
      .np-overlay { color: #FFD700; text-shadow: none; }

    }
  </style>
</head>
<body class="py-4">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" src="{{ $img }}" alt="Preview" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <div id="np-prev-name" class="np-overlay np-name font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay np-num  font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <div class="col-md-3 np-col order-2 order-md-1">
      <div class="border rounded p-3">
        <h6 class="mb-3">Customize</h6>
        <div id="np-status" class="small text-muted mb-2">Checking methods…</div>
        <div id="np-note" class="small text-muted mb-3 d-none">Personalization not available for this product.</div>

        <div id="np-controls" class="np-hidden">
          <div class="mb-3 np-field-wrap">
            <label for="np-num" class="form-label">Your Number</label>
            <input id="np-num" type="text" inputmode="numeric" maxlength="3" class="form-control" placeholder="Your number" autocomplete="off">
            <span class="max-count">MAX. 2</span>
            <div id="np-num-help" class="form-text">Digits only. 1–3 digits.</div>
            <div id="np-num-err"  class="text-danger small d-none">Enter 1–3 digits only.</div>
          </div>

          <div class="mb-3 np-field-wrap">
            <label for="np-name" class="form-label">Your Name</label>
            <input id="np-name" type="text" maxlength="12" class="form-control" placeholder="Your name" autocomplete="off">
            <span class="max-count">MAX. 11</span>
            <div id="np-name-help" class="form-text">Only A–Z and spaces. 1–12 chars.</div>
            <div id="np-name-err"  class="text-danger small d-none">Enter 1–12 letters/spaces only.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Font</label>
            <select id="np-font" class="form-select">
              <option value="bebas">Bebas Neue (Bold)</option>
              <option value="anton">Anton</option>
              <option value="oswald">Oswald</option>
              <option value="impact">Impact</option>
            </select>
          </div>

          <div class="mb-2">
            <label class="form-label d-block">Text Color</label>
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

    // STRONG placeOverlay: height + width caps + numeric shrink, mobile-specific caps
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

      const maxAllowed = Math.max(14, Math.floor(stageW * (isMobile ? 0.28 : 0.18)));
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
      applyLayout(); // IMPORTANT: recalc sizes after updating text
    }

    function syncHidden(){
      const set = (id, v) => { const el = $(id); if (el) el.value = v; };
      set('np-name-hidden', (nameEl ? (nameEl.value||'') : '').toUpperCase().trim());
      set('np-num-hidden',  (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim());
      set('np-font-hidden', fontEl ? fontEl.value : '');
      set('np-color-hidden', colorEl ? colorEl.value : '');
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
