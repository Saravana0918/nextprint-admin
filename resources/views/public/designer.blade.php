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
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-overlay { position:absolute; font-weight:700; text-transform:uppercase; letter-spacing:2px; white-space:nowrap; display:flex; align-items:center; justify-content:center; pointer-events:none; }

    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
    .max-count{ display:none; }

    body { background-color : #929292; }
    .desktop-display{ color:white; font-family: "Roboto Condensed", sans-serif; font-weight: bold; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }
    .hide-on-mobile { display: none !important; }

    @media (max-width: 767px) {
      .np-stage { position: relative !important; }

      .np-mobile-controls {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        width: 92%;
        max-width: 420px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
        z-index: 100030;
        pointer-events: auto;
      }

      .np-mobile-controls .mobile-input {
        width: 100%;
        box-sizing: border-box;
        background: rgba(255,255,255,0.95);
        border: none;
        border-radius: 6px;
        padding: 10px 12px;
        font-weight: 700;
        text-transform: uppercase;
        text-align: center;
        letter-spacing: 1px;
        font-size: 16px;
        color: #222;
      }

      .np-mobile-controls .mobile-num {
        font-size: 28px;
        padding: 10px 12px;
      }

      .np-mobile-controls .max-row { width:100%; display:flex; justify-content:space-between; align-items:center; gap:8px; }
      .np-mobile-controls .max-count { font-size:12px; font-weight:800; color:rgba(255,255,255,0.95); background: rgba(0,0,0,0.35); padding:4px 8px; border-radius:12px; }

      #np-atc-btn.mobile-fixed { position: fixed !important; top: 10px !important; right: 12px !important; z-index: 99999 !important; width: 109px !important; height: 40px !important; padding: 6px 12px !important; border-radius: 28px !important; background: #0d6efd !important; color: #fff !important; }
      #np-prev-name, #np-prev-num { z-index: 999999 !important; pointer-events: none !important; text-shadow: 0 3px 10px rgba(0,0,0,0.7) !important; }

      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; position: relative; margin-top: -70px; }
      body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 5; pointer-events: none; }
      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }

      .np-col input.form-control, .np-col select.form-select, .np-col textarea, .np-col .np-swatch,
      #np-name, #np-num, #np-font, #np-color, #np-size, #np-qty, #np-atc-btn, #btn-add-team {
        position: relative !important;
        z-index: 100010 !important;
        pointer-events: auto !important;
      }

      .np-stage,
      .np-stage::after,
      .np-stage.covering,
      .np-stage .np-overlay {
        pointer-events: none !important;
      }

      #np-atc-btn.mobile-fixed,
      #np-atc-btn {
        pointer-events: auto !important;
        z-index: 100020 !important;
      }
    }
    @media (min-width: 768px) {
      .vt-icons { display: none !important; }
    }
    .col-md-3.np-col > #np-controls { padding: 16px !important; box-sizing: border-box; min-height: 360px; }
  </style>
</head>
<body class="body-padding">

@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
  // IMPORTANT: render a variantMap for sizes -> variant ids (numeric OR gid string)
  // Example server-side:
  // window.variantMap = { "S": 123456789, "M": 234567890, "L": 345678901 }
@endphp

<div class="container">
  <div class="row g-4">
    <!-- preview -->
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

    <!-- controls -->
    <div class="col-md-3 np-col order-2 order-md-1" id="np-controls">
      <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
      <input id="np-num" type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
      <select id="np-font" class="form-select mb-2">
        <option value="bebas">Bebas Neue</option>
        <option value="anton">Anton</option>
        <option value="oswald">Oswald</option>
        <option value="impact">Impact</option>
      </select>
      <div class="mb-2">
        <button type="button" class="np-swatch" data-color="#FFFFFF" style="background:#FFF"></button>
        <button type="button" class="np-swatch" data-color="#000000" style="background:#000"></button>
        <button type="button" class="np-swatch" data-color="#FFD700" style="background:#FFD700"></button>
        <button type="button" class="np-swatch" data-color="#FF0000" style="background:#FF0000"></button>
        <button type="button" class="np-swatch" data-color="#1E90FF" style="background:#1E90FF"></button>
      </div>
      <input id="np-color" type="color" class="form-control form-control-color mt-2" value="#D4AF37">
    </div>

    <!-- purchase + team -->
    <div class="col-md-3 np-col order-3 order-md-3 right-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>

      <!-- form (hidden fields included) -->
      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" name="preview_data" id="np-preview-hidden">
        <input type="hidden" name="product_id" id="np-product-id" value="{{ $product->id ?? $product->local_id ?? '' }}">
        <input type="hidden" name="shopify_product_id" id="np-shopify-product-id" value="{{ $product->shopify_product_id ?? $product->shopify_id ?? '' }}">
        <input type="hidden" name="variant_id" id="np-variant-id" value="">
        <div class="mb-2">
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            <option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option>
          </select>
        </div>
        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary" disabled>Add to Cart</button>
        <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
      </form>
    </div>
  </div>
</div>

{{-- server must print variantMap if available, example:
   <script> window.variantMap = {"S": 45229263159492, "M": 45229263159493, "L": 45229263159494} </script>
--}}
<script>
  // server-rendered mapping - ensure this exists in your Blade
  window.variantMap = window.variantMap || {!! json_encode($variantMap ?? [], JSON_NUMERIC_CHECK) !!} || {};
  // layoutSlots
  window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  const nameEl = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const btn = $('np-atc-btn'), form = $('np-atc-form'), addTeam = $('btn-add-team');
  const SIZE_SELECT = $('np-size');

  const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

  // Utility: convert numeric variant ID -> gid string
  function toGidIfNeeded(v) {
    if (!v) return '';
    v = v.toString();
    if (v.startsWith('gid://')) return v; // already in gid form
    // otherwise assume numeric id and convert
    return `gid://shopify/ProductVariant/${v}`;
  }

  // Set variant hidden value based on size using window.variantMap
  function setVariantForSize() {
    const size = SIZE_SELECT?.value || '';
    if (!size) { $('np-variant-id').value = ''; return; }
    const map = window.variantMap || {};
    const mapped = map[size] || map[size.toUpperCase()] || map[size.toLowerCase()] || '';
    // If mapped is numeric, convert to gid; if already gid, keep.
    $('np-variant-id').value = toGidIfNeeded(mapped);
    console.log('variant for size', size, '->', $('np-variant-id').value);
  }

  // Sync hidden text fields
  function syncHidden(){
    const n = $('np-name-hidden'), nm = $('np-num-hidden'), f=$('np-font-hidden'), c=$('np-color-hidden');
    if(n) n.value = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    if(nm) nm.value = (numEl ? (numEl.value||'') : '').replace(/\D/g,'').trim();
    if(f) f.value = fontEl ? fontEl.value : '';
    if(c) c.value = colorEl ? colorEl.value : '';
    setVariantForSize();
  }

  function applyFont(val){
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    [pvName,pvNum].forEach(el=>{ if(el) el.className = 'np-overlay '+cls; });
  }

  // Preview sync
  function syncPreview(){
    if (pvName && nameEl) pvName.textContent = (nameEl.value||'NAME').toUpperCase();
    if (pvNum && numEl) pvNum.textContent = (numEl.value||'09').replace(/\D/g,'');
    // call layout placement if you have layoutSlots
    if (window.layoutSlots && window.layoutSlots.name) {
      // placeOverlay code may exist elsewhere; we'll fallback to centering if missing
      try { if (typeof placeOverlay === 'function') { placeOverlay(pvName, window.layoutSlots.name, 'name'); } } catch(e){}
    }
  }

  // Validation + enable button logic
  function validateAndToggle() {
    const okName = NAME_RE.test((nameEl?.value||'').trim());
    const okNum = NUM_RE.test((numEl?.value||'').trim());
    const hasSize = !!(SIZE_SELECT && SIZE_SELECT.value);
    if (btn) btn.disabled = !(okName && okNum && hasSize);
  }

  // Wire events
  if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); syncHidden(); validateAndToggle(); });
  if (numEl) numEl.addEventListener('input', e=> { e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); syncHidden(); validateAndToggle(); });
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });
  document.querySelectorAll('.np-swatch').forEach(b => {
    b.addEventListener('click', ()=> {
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      if (colorEl) colorEl.value = b.dataset.color;
      if (pvName) pvName.style.color = b.dataset.color;
      if (pvNum) pvNum.style.color = b.dataset.color;
      syncHidden();
    });
  });

  // size change must set variant id
  SIZE_SELECT?.addEventListener('change', ()=> { setVariantForSize(); syncHidden(); validateAndToggle(); });

  // Add Team button
  if (addTeam) addTeam.addEventListener('click', function(e){
    e.preventDefault();
    const params = new URLSearchParams();
    const productId = $('np-product-id')?.value || '';
    if (productId) params.set('product_id', productId);
    if (nameEl?.value) params.set('prefill_name', nameEl.value);
    if (numEl?.value) params.set('prefill_number', numEl.value);
    if (fontEl?.value) params.set('prefill_font', fontEl.value);
    if (colorEl?.value) params.set('prefill_color', colorEl.value);
    if (SIZE_SELECT?.value) params.set('prefill_size', SIZE_SELECT.value);
    const base = "{{ route('team.create') }}";
    window.location.href = base + '?' + params.toString();
  });

  // init
  applyFont(fontEl?.value || 'bebas');
  if (pvName && colorEl) pvName.style.color = colorEl.value;
  if (pvNum && colorEl) pvNum.style.color = colorEl.value;
  syncPreview();
  syncHidden();
  validateAndToggle();

  // Submit handler: capture canvas, set hidden fields (including variant), post via fetch
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    // final validations
    const size = SIZE_SELECT?.value || '';
    if (!size) { alert('Please select a size.'); return; }
    if (!(NAME_RE.test(nameEl?.value||'') && NUM_RE.test(numEl?.value||''))) { alert('Please enter valid Name (A–Z, 1–12) and Number (1–3 digits).'); return; }

    // ensure variant set
    setVariantForSize();
    const v = $('np-variant-id').value;
    if (!v) {
      alert('Variant not selected. Please choose a size or contact admin.');
      console.error('No variant id found. variantMap:', window.variantMap);
      return;
    }

    syncHidden();

    if (btn) { btn.disabled = true; btn.setAttribute('aria-busy','true'); btn.innerText = 'Preparing...'; }

    try {
      const canvas = await html2canvas(stage, { useCORS: true, backgroundColor: null, scale: window.devicePixelRatio || 1 });
      const dataUrl = canvas.toDataURL('image/png');
      $('np-preview-hidden').value = dataUrl;

      const fd = new FormData(form);
      // confirm what is being sent for debugging
      console.log('Submitting form data (preview): name=', fd.get('name_text'), 'number=', fd.get('number_text'), 'variant_id=', fd.get('variant_id'));
      const token = document.querySelector('input[name="_token"]')?.value || '';
      const resp = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token, 'Accept':'application/json' }});
      if (resp.redirected) { window.location.href = resp.url; return; }
      const data = await resp.json().catch(()=>null);
      if (!resp.ok) {
        console.error('Add to cart failed', resp.status, data);
        alert((data && (data.error||data.message)) || 'Add to cart failed');
        return;
      }
      if (data && data.checkoutUrl) { window.location.href = data.checkoutUrl; return; }
      // success fallback
      alert('Added to cart. Proceed to checkout.');
    } catch (err) {
      console.error('ATC exception', err);
      alert('Something went wrong. Try again.');
    } finally {
      if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); btn.innerText = 'Add to Cart'; }
    }
  });

  // small UI helpers
  window.addEventListener('load', ()=> { if (window.innerWidth <= 767) btn?.classList?.add('mobile-fixed'); });
  window.addEventListener('resize', ()=> { if (window.innerWidth <= 767) btn?.classList?.add('mobile-fixed'); else btn?.classList?.remove('mobile-fixed'); });

})();
</script>

<!-- mobile overlay script (only creates floating inputs that sync to real inputs) -->
<script>
(function(){
  function mobileOverlaySetup() {
    if (window.innerWidth > 767) return;
    const stage = document.getElementById('np-stage');
    if (!stage) return;
    if (document.querySelector('.np-mobile-controls')) return;

    const cont = document.createElement('div');
    cont.className = 'np-mobile-controls';
    cont.setAttribute('aria-hidden','false');

    const nameInput = document.createElement('input');
    nameInput.type = 'text'; nameInput.id = 'np-mobile-name'; nameInput.placeholder = 'YOUR NAME';
    nameInput.maxLength = 12; nameInput.className = 'mobile-input'; nameInput.autocapitalize = 'characters';
    nameInput.autocomplete = 'off'; nameInput.spellcheck = false;

    const numInput = document.createElement('input');
    numInput.type = 'text'; numInput.id = 'np-mobile-num'; numInput.placeholder = '09';
    numInput.inputMode = 'numeric'; numInput.maxLength = 3; numInput.className = 'mobile-input mobile-num';

    const maxRow = document.createElement('div'); maxRow.className = 'max-row';
    const spacer = document.createElement('div'); spacer.style.flex = '1';
    const maxName = document.createElement('div'); maxName.className = 'max-count'; maxName.textContent = 'MAX. 12';
    const maxNum = document.createElement('div'); maxNum.className = 'max-count'; maxNum.textContent = 'MAX. 3';
    maxRow.appendChild(spacer); maxRow.appendChild(maxName); maxRow.appendChild(maxNum);

    cont.appendChild(nameInput); cont.appendChild(numInput); cont.appendChild(maxRow);
    stage.appendChild(cont);

    // existing real inputs
    const realName = document.getElementById('np-name');
    const realNum = document.getElementById('np-num');

    // initialize with current values
    if (realName && realName.value) nameInput.value = realName.value;
    if (realNum && realNum.value) numInput.value = realNum.value;

    // sync from mobile -> real
    nameInput.addEventListener('input', e => {
      const v = e.target.value.toUpperCase().replace(/[^A-Z ]/g,'').slice(0,12);
      e.target.value = v;
      if (realName) realName.value = v;
      const pvName = document.getElementById('np-prev-name'); if (pvName) pvName.textContent = v||'NAME';
      // also update hidden sync if needed
      document.getElementById('np-name-hidden') && (document.getElementById('np-name-hidden').value = v);
    });

    numInput.addEventListener('input', e => {
      const v = e.target.value.replace(/\D/g,'').slice(0,3);
      e.target.value = v;
      if (realNum) realNum.value = v;
      const pvNum = document.getElementById('np-prev-num'); if (pvNum) pvNum.textContent = v||'09';
      document.getElementById('np-num-hidden') && (document.getElementById('np-num-hidden').value = v);
    });

    // Keep the stage positioned nicely when keyboard opens
    function keepStageVisibleOnKeyboard() {
      if (!window.visualViewport) return;
      const setPos = () => {
        const sRect = stage.getBoundingClientRect();
        const topPx = Math.max(8, Math.round(sRect.height * 0.60));
        cont.style.top = topPx + 'px';
      };
      setPos();
      window.visualViewport.addEventListener('resize', () => {
        setPos();
        const vhRatio = window.visualViewport.height / window.innerHeight;
        if (vhRatio < 0.75) {
          stage.style.position = 'fixed'; stage.style.top = '12px'; stage.style.left = '50%'; stage.style.transform = 'translateX(-50%)';
        } else {
          stage.style.position = ''; stage.style.top = ''; stage.style.left = ''; stage.style.transform = '';
        }
      });
      window.addEventListener('resize', setPos);
      window.addEventListener('orientationchange', () => setTimeout(setPos,150));
    }
    keepStageVisibleOnKeyboard();

    // focus the name input for convenience
    setTimeout(()=> nameInput.focus(), 250);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mobileOverlaySetup);
  } else {
    mobileOverlaySetup();
  }
  window.addEventListener('load', ()=> setTimeout(mobileOverlaySetup, 200));
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
