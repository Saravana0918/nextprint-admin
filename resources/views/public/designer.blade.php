<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* fonts */
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

    /* stage */
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; overflow: visible; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-mask { position:absolute; pointer-events:none; z-index:40; transform-origin:center center; image-rendering:optimizeQuality; }

    /* overlays: NO background, sits on top of image */
    .np-overlay {
      position: absolute;
      color: #D4AF37;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      text-align: center;
      text-shadow: 0 3px 10px rgba(0,0,0,0.65);
      pointer-events: none;
      white-space: nowrap;
      line-height: 1;
      transform-origin: center center;
      z-index: 9999;
    }

    /* ensure overlay does not create its own box or background */
    .np-overlay::before, .np-overlay::after { content: none; }

    /* swatches */
    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

    /* default page styles */
    body { background-color: #929292; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }
    /* user-uploaded artwork overlay */
    .np-user-image {
      position: absolute;
      pointer-events: auto; /* allow future drag/resize if you add handlers */
      object-fit: cover;
      display: block;
      transform-origin: center center;
      z-index: 400; /* below overlays which are z-index 9999, adjust if needed */
      box-shadow: 0 6px 18px rgba(0,0,0,0.25);
      border-radius: 4px;
    }

    /* mobile specific: keep overlays on image, inputs below */
    @media (max-width: 767px) {
      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; margin-top: -70px; }
      body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 5; pointer-events: none; }
      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }

      /* inputs: visible below the stage (normal flow) */
      .np-col input.form-control, .np-col select.form-select { z-index: 100020; position: relative; }

      /* keep overlays visually *on* the image (no white box) */
      .np-stage::after { content: ""; position: absolute; left: 12px; right: 12px; top: 12px; bottom: 12px; border-radius: 8px; background: rgba(0,0,0,0.06); z-index: 15; pointer-events: none; }

      /* Add-to-cart floating button */
      #np-atc-btn { position: fixed !important; top: 12px !important; right: 12px !important; z-index: 100050 !important; width: 130px !important; height: 44px !important; border-radius: 28px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; font-weight: 700 !important; }
      .mobile-layout{
        margin-top : -330px;
      }
    }
    @media (min-width: 768px) {
      .vt-icons { display: none !important; }
    }

    /* accessibility focus styles */
    input:focus, select:focus { outline: 3px solid rgba(13,110,253,0.12); }
    .desktop-display{ color : white ;}

  </style>
</head>
<body class="body-padding">

@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
@endphp

<div class="container">
  <div class="row g-4">
    <!-- preview -->
    <div class="col-md-6 np-col order-1 order-md-2">
      <div class="border rounded p-3">
        <div class="np-stage" id="np-stage">
          <img id="np-base" crossorigin="anonymous" src="{{ $img }}" alt="Preview"
               onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
          <!-- OVERLAYS: these are always *on the image* -->
          <div id="np-prev-name" class="np-overlay font-bebas" aria-hidden="true"></div>
          <div id="np-prev-num"  class="np-overlay font-bebas" aria-hidden="true"></div>
        </div>
      </div>
    </div>

    <!-- controls -->
    <div class="col-md-3 np-col order-2 order-md-1" id="np-controls">
      <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
      <input id="np-num"  type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
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
      <!-- customer image upload (put inside the controls column near fonts/colors) -->
      <div class="mb-2" id="np-upload-block" style="margin-top:6px;">
        <label for="np-upload-image" class="form-label" style="font-size:.9rem;color:#fff;opacity:.95">Upload Image (customer)</label>
        <input id="np-upload-image" type="file" accept="image/*" class="form-control" />
        <div style="margin-top:6px;">
          <button id="np-user-image-reset" type="button" class="btn btn-sm btn-outline-light" style="display:none;margin-right:6px;">Remove Image</button>
          <label for="np-user-image-scale" style="color:#fff; font-size:.85rem;margin-right:6px;display:none;" id="np-user-image-scale-label">Scale</label>
          <input id="np-user-image-scale" type="range" min="50" max="200" value="100" style="vertical-align: middle; display:none;" />
        </div>
      </div>
    </div>

    <!-- purchase + team -->
    <div class="col-md-3 np-col order-3 order-md-3 right-layout mobile-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>

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

        {{-- -- DYNAMIC SIZE DROPDOWN (populated from DB) --}}
        @php
          // Build a size options array using variantMap keys or product->variants fallback
          $sizeOptions = [];
          // If $product->variants is loaded, build from that (preserves original values)
          if (!empty($product) && $product->relationLoaded('variants') && $product->variants->count()) {
              $sizeOptions = $product->variants->pluck('option_value')->map(fn($x)=>trim((string)$x))->unique()->values()->all();
          }
          // Ensure uppercasing when building variantMap later - but display original option_value casing
        @endphp

        <div class="mb-2">
          <select id="np-size" name="size" class="form-select" required>
            <option value="">Select Size</option>
            @foreach($sizeOptions as $opt)
              <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>

        <button id="np-atc-btn" type="submit" class="btn btn-primary">Add to Cart</button>
        <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
      </form>
    </div>
  </div>
</div>

@php
  // Build a robust variant map server-side: KEY => shopify_variant_id
  // We normalize keys to the exact option_value as stored (and uppercase lookup will be used in JS)
  $variantMap = [];
  if (!empty($product) && $product->relationLoaded('variants')) {
      foreach ($product->variants as $v) {
          $rawKey = trim((string)($v->option_value ?? $v->option_name ?? $v->title ?? ''));
          if ($rawKey === '') continue;
          // store both original and uppercase mapping in JS will do uppercase lookup
          $variantMap[strtoupper($rawKey)] = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? '');
          // Also build a display map so we can match exact casing if needed
      }
  }
@endphp

<script>
  window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};

  // dynamic variant map injected from DB (keys uppercased)
  window.variantMap = {!! json_encode($variantMap, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!} || {};
  console.info('variantMap:', window.variantMap);

  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";
</script>

<script>
(function(){
  const $ = id => document.getElementById(id);

  const nameEl  = $('np-name'), numEl = $('np-num'), fontEl = $('np-font'), colorEl = $('np-color');
  const pvName  = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');
  const btn = $('np-atc-btn'), form = $('np-atc-form'), addTeam = $('btn-add-team');
  const sizeEl = $('np-size');
  const layout = (typeof window.layoutSlots === 'object' && window.layoutSlots !== null) ? window.layoutSlots : {};

  const NAME_RE = /^[A-Za-z ]{1,12}$/, NUM_RE = /^\d{1,3}$/;

  function applyFont(val){
    const map = {bebas:'font-bebas', anton:'font-anton', oswald:'font-oswald', impact:'font-impact'};
    const cls = map[val] || 'font-bebas';
    [pvName, pvNum].forEach(el => { if(el) el.className = 'np-overlay ' + cls; });
  }

  function computeStageSize(){
    if (!baseImg || !stage) return null;
    const stageRect = stage.getBoundingClientRect();
    const imgRect = baseImg.getBoundingClientRect();
    return {
      offsetLeft: Math.round(imgRect.left - stageRect.left),
      offsetTop: Math.round(imgRect.top - stageRect.top),
      imgW: Math.max(1,imgRect.width), imgH: Math.max(1,imgRect.height),
      stageW: Math.max(1, stageRect.width), stageH: Math.max(1, stageRect.height)
    };
  }

  function placeOverlay(el, slot, slotKey){
    if(!el || !slot) return;
    const s = computeStageSize();
    if(!s) return;
    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||0)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop  + ((slot.top_pct||0)/100)  * s.imgH + ((slot.height_pct||0)/200)*s.imgH);
    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));

    el.style.position = 'absolute';
    el.style.left = centerX + 'px';
    el.style.top  = centerY + 'px';
    el.style.width = areaWpx + 'px';
    el.style.height = areaHpx + 'px';
    el.style.transform = 'translate(-50%,-50%) rotate(' + ((slot.rotation||0)) + 'deg)';
    el.style.display = 'flex';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';
    el.style.boxSizing = 'border-box';
    el.style.padding = '0 6px';
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';
    el.style.pointerEvents = 'none';
    el.style.zIndex = (slotKey === 'number' ? 60 : 50);

    const text = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightFactorName = 1.00;
    const heightFactorNumber = isMobile ? 1.05 : 1.00;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? heightFactorNumber : heightFactorName));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let numericShrink = (slotKey === 'number') ? (isMobile ? 1.0 : 0.98) : 1.0;
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * numericShrink);
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
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
    if (!baseImg || !baseImg.complete) return;
    if (layout && layout.name) placeOverlay(pvName, layout.name, 'name');
    else { pvName.style.left='50%'; pvName.style.top='45%'; pvName.style.transform='translate(-50%,-50%)'; }
    if (layout && layout.number) placeOverlay(pvNum, layout.number, 'number');
    else { pvNum.style.left='50%'; pvNum.style.top='65%'; pvNum.style.transform='translate(-50%,-50%)'; }
    renderMasks();
  }

  // === ADD THIS FUNCTION to render SVG mask areas ===
function renderMasks() {
  const layout = window.layoutSlots || {};
  const stage = document.getElementById('np-stage');
  const baseImg = document.getElementById('np-base');
  if (!layout || !stage || !baseImg) return;

  const stageRect = stage.getBoundingClientRect();
  const imgRect = baseImg.getBoundingClientRect();
  const s = {
    offsetLeft: Math.round(imgRect.left - stageRect.left),
    offsetTop: Math.round(imgRect.top - stageRect.top),
    imgW: Math.max(1,imgRect.width),
    imgH: Math.max(1,imgRect.height)
  };

  Object.keys(layout).forEach(key => {
    const slot = layout[key];
    if (!slot || !slot.mask) return;

    const id = 'mask-' + key;
    let el = document.getElementById(id);
    if (!el) {
      el = document.createElement('img');
      el.id = id;
      el.src = slot.mask;
      el.className = 'np-mask';
      el.style.position = 'absolute';
      el.style.pointerEvents = 'none';
      el.style.zIndex = 40;
      el.style.opacity = 1;
      el.style.objectFit = 'contain';
      stage.appendChild(el);
    }

    const cx = Math.round(s.offsetLeft + ((slot.left_pct||0)/100)*s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const cy = Math.round(s.offsetTop + ((slot.top_pct||0)/100)*s.imgH + ((slot.height_pct||0)/200)*s.imgH);
    const wpx = Math.round(((slot.width_pct||10)/100)*s.imgW);
    const hpx = Math.round(((slot.height_pct||10)/100)*s.imgH);

    el.style.left = (cx - wpx/2) + 'px';
    el.style.top = (cy - hpx/2) + 'px';
    el.style.width = wpx + 'px';
    el.style.height = hpx + 'px';
    el.style.transform = 'rotate(' + (slot.rotation||0) + 'deg)';
  });
}

  function syncPreview(){
    if (pvName && nameEl) pvName.textContent = (nameEl.value || 'NAME').toUpperCase();
    if (pvNum && numEl) pvNum.textContent = (numEl.value || '09').replace(/\D/g,'');
    applyLayout();
  }

  function syncHidden(){
    const n = $('np-name-hidden'), nm = $('np-num-hidden'), f = $('np-font-hidden'), c = $('np-color-hidden');
    if (n) n.value = (nameEl ? (nameEl.value||'') : '').toUpperCase().trim();
    if (nm) nm.value = (numEl ? (numEl.value||'') : '').replace(/\D/g,'').trim();
    if (f) f.value = fontEl ? fontEl.value : '';
    if (c) c.value = colorEl ? colorEl.value : '';

    const size = $('np-size')?.value || '';
    if (window.variantMap && size) {
      const k = (size || '').toString();
      // try direct, then uppercase, then lowercase
      $('np-variant-id').value = window.variantMap[k] || window.variantMap[k.toUpperCase()] || window.variantMap[k.toLowerCase()] || '';
    } else {
      // clear variant id if no mapping
      if ($('np-variant-id')) $('np-variant-id').value = '';
    }
  }

  // events: inputs
  if (nameEl) nameEl.addEventListener('input', ()=>{ syncPreview(); syncHidden(); updateATCState(); });
  if (numEl) numEl.addEventListener('input', e=>{ e.target.value = e.target.value.replace(/\D/g,'').slice(0,3); syncPreview(); syncHidden(); updateATCState(); });
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); syncPreview(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

  // swatches
  document.querySelectorAll('.np-swatch').forEach(b=>{
    b.addEventListener('click', ()=>{
      document.querySelectorAll('.np-swatch').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      if (colorEl) colorEl.value = b.dataset.color;
      if (pvName) pvName.style.color = b.dataset.color;
      if (pvNum) pvNum.style.color = b.dataset.color;
      syncHidden();
    });
  });

  function updateATCState(){
    if (!btn) return;
    btn.disabled = false;
  }

  // size change listener (ensure variant id is always populated)
  if (sizeEl) {
    sizeEl.addEventListener('change', function(){
      const sizeVal = (sizeEl.value || '').toString();
      if (window.variantMap) {
        const k = sizeVal;
        const variant = window.variantMap[k] || window.variantMap[k.toUpperCase()] || window.variantMap[k.toLowerCase()] || '';
        if (document.getElementById('np-variant-id')) document.getElementById('np-variant-id').value = variant;
      }
      syncHidden();
      syncPreview();
    });

    // If there's a prefill (like Add Team Players passing in a prefill_size), set it now
    try {
      // window.prefill may be injected by Team page; gracefully handle if not set
      var prefillSize = (window.prefill && (window.prefill.prefill_size || window.prefill.size)) ? (window.prefill.prefill_size || window.prefill.size) : null;
      if (prefillSize && sizeEl.querySelector('option[value="'+prefillSize+'"]')) {
        sizeEl.value = prefillSize;
      }
    } catch(e) { /* ignore */ }

    // also call once to populate initial value
    try { sizeEl.dispatchEvent(new Event('change')); } catch(e) {}
  }

  // add team button (navigates to team.create with prefill)
  if (addTeam) {
    addTeam.addEventListener('click', function(e) {
      e.preventDefault();
      const productIdInput = document.getElementById('np-product-id');
      const productId = productIdInput ? productIdInput.value : null;
      const params = new URLSearchParams();
      if (productId) params.set('product_id', productId);
      if (nameEl?.value) params.set('prefill_name', nameEl.value);
      if (numEl?.value) params.set('prefill_number', numEl.value.replace(/\D/g,'')); 
      if (fontEl?.value) params.set('prefill_font', fontEl.value);
      if (colorEl?.value) params.set('prefill_color', encodeURIComponent(colorEl.value));
      const sizeVal = document.getElementById('np-size')?.value || '';
      if (sizeVal) params.set('prefill_size', sizeVal);
      try {
        if (window.layoutSlots && Object.keys(window.layoutSlots || {}).length) {
          params.set('layoutSlots', encodeURIComponent(JSON.stringify(window.layoutSlots)));
        }
      } catch (err) { console.warn('layoutSlots encode failed', err); }
      const base = "{{ route('team.create') }}";
      window.location.href = base + (params.toString() ? ('?' + params.toString()) : '');
    });
  }

  // init
  applyFont(fontEl?.value || 'bebas');
  if (pvName && colorEl) pvName.style.color = colorEl.value;
  if (pvNum && colorEl) pvNum.style.color = colorEl.value;
  syncPreview();
  syncHidden();
  updateATCState();

  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('orientationchange', ()=> setTimeout(applyLayout, 200));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  // submit handler (posts to storefront /cart/add and redirects to storefront cart)
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();

    const size = $('np-size')?.value || '';
    if (!size) { alert('Please select a size.'); return; }
    if (!NAME_RE.test(nameEl.value||'') || !NUM_RE.test(numEl.value||'')) { alert('Please enter valid Name and Number'); return; }

    syncHidden(); // ensures np-variant-id is populated

    const variantId = (document.getElementById('np-variant-id') || { value: '' }).value;
    if (!variantId || !/^\d+$/.test(variantId)) {
      alert('Variant not selected or invalid. Please re-select size.');
      return;
    }

    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }

    try {
      try {
        const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
        const dataUrl = canvas.toDataURL('image/png');
        $('np-preview-hidden').value = dataUrl;
      } catch(e) {
        console.warn('html2canvas failed, continuing without preview:', e);
      }

      const properties = {
        'Name': $('np-name-hidden')?.value || '',
        'Number': $('np-num-hidden')?.value || '',
        'Font': $('np-font-hidden')?.value || '',
        'Color': $('np-color-hidden')?.value || ''
      };

      const qty = Math.max(1, parseInt($('np-qty')?.value || '1', 10));
      const bodyArr = [];
      bodyArr.push('id=' + encodeURIComponent(variantId));
      bodyArr.push('quantity=' + encodeURIComponent(qty));
      for (const k in properties) {
        bodyArr.push('properties[' + encodeURIComponent(k) + ']=' + encodeURIComponent(properties[k]));
      }
      const body = bodyArr.join('&');

      // POST to storefront cart add (same-origin)
      const resp = await fetch('/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        credentials: 'same-origin'
      });

      // Redirect to storefront cart permalink (always use shopfrontUrl)
      const shopfront = (window.shopfrontUrl || '').replace(/\/+$/,'');
      const redirectUrl = shopfront + '/cart/' + variantId + ':' + qty;

      // If response OK, go to storefront cart; otherwise still try permalink
      if (resp && resp.ok) {
        window.location.href = redirectUrl;
        return;
      } else {
        window.location.href = redirectUrl;
        return;
      }
    } catch (err) {
      console.error('Add to cart error', err);
      alert('Something went wrong adding to cart.');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
    }
  });

})();
</script>
<script>
// Image upload handler: places the image into the "primary" layout slot (if layoutSlots available)
// or centers it on the stage as fallback.

(function(){
  const uploadEl = document.getElementById('np-upload-image');
  const stage = document.getElementById('np-stage');
  const baseImg = document.getElementById('np-base');
  const previewHidden = document.getElementById('np-preview-hidden');
  const removeBtn = document.getElementById('np-user-image-reset');
  const scaleRange = document.getElementById('np-user-image-scale');
  const scaleLabel = document.getElementById('np-user-image-scale-label');

  // keep a ref to inserted image node
  let userImg = null;
  let userImgScale = 1.0;

  function computeStageSizeLocal(){
    if (!baseImg || !stage) return null;
    const stageRect = stage.getBoundingClientRect();
    const imgRect = baseImg.getBoundingClientRect();
    return {
      offsetLeft: Math.round(imgRect.left - stageRect.left),
      offsetTop: Math.round(imgRect.top - stageRect.top),
      imgW: Math.max(1,imgRect.width), imgH: Math.max(1,imgRect.height),
      stageW: Math.max(1, stageRect.width), stageH: Math.max(1, stageRect.height)
    };
  }

  // place & size user image into a slot object { left_pct, top_pct, width_pct, height_pct, rotation }
  function placeUserImage(slot){
    if (!userImg) return;
    const s = computeStageSizeLocal();
    if (!s) return;

    // fallback: if no slot, center cover the whole image area
    if (!slot || !slot.width_pct) {
      const left = Math.round(s.stageW/2);
      const top  = Math.round(s.stageH/2);
      const wpx = Math.round(s.imgW * 0.8);
      const hpx = Math.round(s.imgH * 0.8);
      userImg.style.left = left + 'px';
      userImg.style.top  = top + 'px';
      userImg.style.width = wpx + 'px';
      userImg.style.height = hpx + 'px';
      userImg.style.transform = 'translate(-50%,-50%) scale(' + userImgScale + ')';
      return;
    }

    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop + ((slot.top_pct||50)/100) * s.imgH + ((slot.height_pct||0)/200)*s.imgH);
    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));

    // Fit user image to cover the area (cover scale)
    // keep CSS object-fit: cover; so width/height should be area size * scale.
    const scaledW = Math.round(areaWpx * (userImgScale));
    const scaledH = Math.round(areaHpx * (userImgScale));

    userImg.style.left = centerX + 'px';
    userImg.style.top  = centerY + 'px';
    userImg.style.width = scaledW + 'px';
    userImg.style.height = scaledH + 'px';
    userImg.style.transform = 'translate(-50%,-50%)';
    if (slot.rotation) {
      userImg.style.transform += ' rotate(' + slot.rotation + 'deg)';
    }
  }

  // find first "text" slot? prefer slot named "name" or "number" or a slot with usage = 'regular'?
  function findPreferredSlot(){
    try {
      if (window.layoutSlots && Object.keys(window.layoutSlots || {}).length) {
        // If there is a specific decoration area type in layoutSlots (e.g. 'logo' or 'artwork'), try that first.
        // We choose (in order): 'logo', 'artwork', 'number', 'name' or the first slot.
        const prefer = ['logo','artwork','number','name','custom'];
        for (const p of prefer) {
          if (window.layoutSlots[p]) return window.layoutSlots[p];
        }
        // fallback to first key
        const keys = Object.keys(window.layoutSlots);
        if (keys.length) return window.layoutSlots[keys[0]];
      }
    } catch(e){}
    return null;
  }

  // read file and create image element
  async function handleFile(file) {
    if (!file) return;
    // validate type and size
    if (!/^image\//.test(file.type)) { alert('Please upload an image file (PNG, JPG, SVG).'); return; }
    const maxMB = 4;
    if (file.size > maxMB * 1024 * 1024) { alert('Please use an image smaller than ' + maxMB + ' MB.'); return; }

    const reader = new FileReader();
    reader.onload = function(ev) {
      const url = ev.target.result;
      // remove old image if exists
      if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg);
      // create new img
      userImg = document.createElement('img');
      userImg.className = 'np-user-image';
      userImg.src = url;
      userImg.alt = 'User artwork';
      // ensure absolute positioning inside stage
      userImg.style.position = 'absolute';
      userImg.style.left = '50%';
      userImg.style.top = '50%';
      userImg.style.width = '100px';
      userImg.style.height = '100px';
      userImg.style.transform = 'translate(-50%,-50%)';
      userImg.style.objectFit = 'cover';
      userImg.style.pointerEvents = 'none'; // not interactive for now

      stage.appendChild(userImg);

      // show controls
      if (removeBtn) removeBtn.style.display = 'inline-block';
      if (scaleRange) { scaleRange.style.display = 'inline-block'; scaleLabel.style.display = 'inline-block'; scaleRange.value = 100; userImgScale = 1.0; }

      // place it in preferred slot
      const slot = findPreferredSlot();
      placeUserImage(slot);

      // when the user image loads, you might want to re-place to get natural size
      userImg.onload = function(){ placeUserImage(slot); };

      // also update the hidden preview immediately (optional)
      // we'll still re-capture preview at Add to Cart time; this just saves current data.
      try {
        // small timeout so image is painted
        setTimeout(()=> {
          html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 })
           .then(canvas => {
             previewHidden.value = canvas.toDataURL('image/png');
           })
           .catch(()=>{ /* ignore */ });
        }, 180);
      } catch(e){}
    };
    reader.readAsDataURL(file);
  }

  if (uploadEl) {
    uploadEl.addEventListener('change', function(e){
      const f = e.target.files && e.target.files[0];
      if (!f) return;
      handleFile(f);
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function(){
      if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg);
      userImg = null;
      removeBtn.style.display = 'none';
      if (scaleRange) { scaleRange.style.display = 'none'; scaleLabel.style.display = 'none'; }
      // clear hidden preview (it will be regenerated on Add to Cart)
      if (previewHidden) previewHidden.value = '';
    });
  }

  if (scaleRange) {
    scaleRange.addEventListener('input', function(){
      const v = parseInt(this.value || '100', 10);
      userImgScale = v / 100;
      const slot = findPreferredSlot();
      placeUserImage(slot);
    });
  }

  // keep image placement accurate on resize/rotation
  window.addEventListener('resize', function(){ if (userImg) placeUserImage(findPreferredSlot()); });
  document.fonts?.ready.then(()=> { if (userImg) placeUserImage(findPreferredSlot()); });

  // ensure when Add-to-cart's html2canvas runs it captures the user image correctly (your code already calls html2canvas)
  // nothing else needed; the inserted node is inside #np-stage so will be included.
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

</body>
</html>
