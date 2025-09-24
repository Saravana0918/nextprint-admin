<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* --- keep all your previous CSS as-is --- */
    /* (omitted here for brevity — paste your full CSS from original) */
    /* ... copy the CSS from your version above ... */
  </style>
</head>
<body class="py-4">
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
  // Build a simple variant map for JS: size => shopify_variant_id
  // $productVariants should be passed from controller as collection
  $variantMap = [];
  if (!empty($productVariants)) {
      foreach($productVariants as $pv){
          if (!empty($pv->option_value) && !empty($pv->shopify_variant_id)) {
              $variantMap[$pv->option_value] = (string)$pv->shopify_variant_id;
          }
      }
  }
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
            <div id="np-num-err" class="text-danger small d-none">Enter 1–3 digits only.</div>
          </div>

          <div class="mb-3 np-field-wrap">
            <label for="np-name" class="form-label">Your Name</label>
            <input id="np-name" type="text" maxlength="12" class="form-control" placeholder="Your name" autocomplete="off">
            <span class="max-count">MAX. 11</span>
            <div id="np-name-help" class="form-text">Only A–Z and spaces. 1–12 chars.</div>
            <div id="np-name-err" class="text-danger small d-none">Enter 1–12 letters/spaces only.</div>
          </div>

          <div class="mb-3">
            <label class="form-label font-label">Font</label>
            <select id="np-font" class="form-select">
              <option value="bebas">Bebas Neue (Bold)</option>
              <option value="anton">Anton</option>
              <option value="oswald">Oswald</option>
              <option value="impact">Impact</option>
            </select>
          </div>

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

    <div class="col-md-3 np-col order-3 order-md-3">
      <h4 class="mb-1">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <div class="text-muted mb-3">Vendor: {{ $product->vendor ?? '—' }} • ₹ {{ number_format((float)($displayPrice ?? ($product->min_price ?? 0)), 2) }}</div>

      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" id="np-product-id" name="product_id" value="{{ $product->id }}">
        <input type="hidden" id="np-shopify-id" value="{{ $product->shopify_product_id ?? '' }}">
        <input type="hidden" id="np-method" value="ADD TEXT">

        <!-- Hidden fields for personalization -->
        <input type="hidden" name="name_text"     id="np-name-hidden">
        <input type="hidden" name="number_text"   id="np-num-hidden">
        <input type="hidden" name="font"          id="np-font-hidden">
        <input type="hidden" name="color"         id="np-color-hidden">

        <!-- Size & quantity fields -->
        <div class="mb-3">
          <label class="form-label">Size</label>
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            {{-- If you have sizes from DB, loop here --}}
            @foreach($productVariants ?? [] as $pv)
              <option value="{{ $pv->option_value }}">{{ $pv->option_value }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Quantity</label>
          <input id="np-qty" name="quantity" type="number" class="form-control" min="1" value="1" required>
        </div>

        <!-- variant_id for Shopify -->
        <input type="hidden" name="variant_id" id="np-variant-id" value="">

        <!-- preview image base64 -->
        <input type="hidden" name="preview_data" id="np-preview-data">

        <button id="np-atc-btn" type="submit" class="btn btn-primary w-100" disabled aria-busy="false">Add to Cart</button>
      </form>
      <div class="small-delivery text-muted mt-2">Button enables when both Name & Number are valid.</div>
    </div>
  </div>
</div>

<script>
  // server side data: layoutSlots is same as before
  window.layoutSlots = {!! json_encode($layoutSlots, JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
  // variant map (size -> shopify_variant_id)
  window.VARIANT_MAP = {!! json_encode($variantMap) !!};
</script>

<!-- html2canvas for preview capture -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  function init(){
    const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
    const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
    const ctrls = $('np-controls'), note = $('np-note'), status = $('np-status'), btn = $('np-atc-btn');
    const sizeSel = $('np-size'), qtyEl = $('np-qty'), variantHidden = $('np-variant-id');
    const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

    function validate(){
      const okName = nameEl ? NAME_RE.test((nameEl.value||'').trim()) : true;
      const okNum = numEl ? NUM_RE.test((numEl.value||'').trim()) : true;
      if (nameEl) document.getElementById('np-name-err')?.classList.toggle('d-none', okName);
      if (numEl)  document.getElementById('np-num-err')?.classList.toggle('d-none', okNum);
      if (ctrls && !ctrls.classList.contains('np-hidden')) {
        if (btn) btn.disabled = !(okName && okNum && sizeSel.value);
      } else {
        if (btn) btn.disabled = !sizeSel.value;
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

    // Place overlay (same logic as your original)
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
      if (slotKey === 'number') numericShrink = isMobile ? 1.0 : 0.98;
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
      const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};
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
      applyLayout();
    }

    function syncHidden(){
      const set = (id, v) => { const el = $(id); if (el) el.value = v; };
      set('np-name-hidden', (nameEl ? (nameEl.value||'') : '').toUpperCase().trim());
      set('np-num-hidden',  (numEl  ? (numEl.value||'')  : '').replace(/\D/g,'').trim());
      set('np-font-hidden', fontEl ? fontEl.value : '');
      set('np-color-hidden', colorEl ? colorEl.value : '');
      // set variant_id based on selected size
      if (sizeSel && variantHidden) {
        const map = window.VARIANT_MAP || {};
        variantHidden.value = map[sizeSel.value] || '';
      }
    }

    if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); validate(); syncHidden(); });
    if (numEl)  numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); validate(); syncHidden(); });
    if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncPreview(); syncHidden(); });
    if (colorEl) colorEl.addEventListener('input', ()=>{ if (pvName) pvName.style.color = colorEl.value; if (pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

    document.querySelectorAll('.np-swatch')?.forEach(b=>{ b.addEventListener('click', ()=>{ document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active')); b.classList.add('active'); if (colorEl) colorEl.value = b.dataset.color; if (pvName) pvName.style.color = b.dataset.color; if (pvNum) pvNum.style.color = b.dataset.color; syncHidden(); });});

    if (sizeSel) {
      sizeSel.addEventListener('change', ()=>{ syncHidden(); validate(); });
    }

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
      atcForm.addEventListener('submit', async function(e){
        // validate fields
        if (ctrls && !ctrls.classList.contains('np-hidden')) {
          if (!validate()) {
            e.preventDefault();
            alert('Please enter a valid Name (A–Z, 1–12) and Number (1–3 digits).');
            return;
          }
        }
        if (!sizeSel.value) {
          e.preventDefault();
          alert('Please select a Size.');
          return;
        }
        // capture preview using html2canvas
        e.preventDefault();
        if (btn) { btn.disabled = true; btn.setAttribute('aria-busy','true'); btn.innerText = 'Preparing...'; }
        syncHidden();

        try {
          const canvas = await html2canvas(stage, {backgroundColor: null, scale: window.devicePixelRatio || 1});
          const dataUrl = canvas.toDataURL('image/png');
          document.getElementById('np-preview-data').value = dataUrl;

          // submit form (regular POST) after setting preview_data
          atcForm.submit();
        } catch(err) {
          console.error('Preview capture failed', err);
          alert('Failed to prepare preview; try again.');
          if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerText = 'Add to Cart'; }
        }
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
