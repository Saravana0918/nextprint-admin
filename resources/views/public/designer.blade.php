<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $product->name ?? ($product->title ?? 'Product') }} – NextPrint</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Bebas+Neue&family=Oswald:wght@400;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
    .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
    .font-oswald{font-family:'Oswald', Arial, sans-serif;}
    .font-impact{font-family:Impact, 'Arial Black', sans-serif;}
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; border-radius:8px; padding:8px; box-sizing: border-box; overflow: visible; background:#eee; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-mask { position:absolute; pointer-events:none; z-index:40; transform-origin:center center; image-rendering:optimizeQuality; }
    .np-overlay { position: absolute; color: #D4AF37; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1.5px; text-align: center; text-shadow: 0 3px 10px rgba(0,0,0,0.65);  pointer-events: none; white-space: nowrap; line-height: 1; transform-origin: center center;
      z-index: 9999; padding: 0 6px; }
    .np-swatch { width:28px; height:28px; border-radius:50%; border:1px solid #ccc; cursor:pointer; display:inline-block; }
    .np-swatch.active { outline: 2px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }

    body { background-color: #929292; }
    .body-padding{ padding-top: 100px; }
    .right-layout{ padding-top:350px; }
    .desktop-display{ color : white; }

    .np-user-image { position: absolute; pointer-events: auto; object-fit: cover; display: block; transform-origin: center center; z-index: 300; box-shadow: 0 6px 18px rgba(0,0,0,0.25); border-radius: 4px; }

    .mobile-only { display: none; }
    .desktop-only { display: block; }

    @media (max-width: 767px) {
      body { background-image: url('/images/stadium-bg.jpg'); background-size: cover; background-position: center center; background-repeat: no-repeat; min-height: 100vh; margin-top: -70px; }
      body::before { content: ""; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 5; pointer-events: none; }
      .container, .row, .np-stage, header, main, footer { position: relative; z-index: 10; }
      .np-col input.form-control, .np-col select.form-select { z-index: 100020; position: relative; }
      .np-stage::after { content: ""; position: absolute; left: 12px; right: 12px; top: 12px; bottom: 12px; border-radius: 8px; background: rgba(0,0,0,0.06); z-index: 15; pointer-events: none; }
      #np-atc-btn { position: fixed !important; top: 12px !important; right: 12px !important; z-index: 100050 !important; border-radius: 28px !important; box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important; font-weight: 700 !important; }
      .mobile-layout{ margin-top : -330px; }
    }
    @media (min-width: 768px) { .vt-icons { display: none !important; } }
    input:focus, select:focus { outline: 3px solid rgba(13,110,253,0.12); }
    @media (max-width: 767px) {
      .np-input-group { display: flex; flex-direction: column; align-items: center; font-family: 'Arial', sans-serif; padding: 6px 12px; margin-bottom: 20px; }
      .np-field { position: relative; width: 86%; max-width: 360px; min-width: 200px; }
      .np-input { width: 100%; background: transparent;  border: none; border-bottom: 2px solid #fff; color: #fff; text-align: center; font-size: 20px; font-weight: 800; outline: none; padding: 8px 0 12px 0;
        text-transform: uppercase; letter-spacing: 1px; } .np-input::placeholder { color: #fff; opacity: 1; }
      .np-max { position: absolute; right: 2px; bottom: -18px; font-size: 10px; color: #fff; opacity: 0.9; font-weight: 700; letter-spacing: 0.4px; }
      #np-num {-webkit-appearance: none; appearance: none;}
      .np-field:nth-child(2) .np-input { font-size: 28px; letter-spacing: 0; }
      .mobile-only { display: block; }
      .desktop-only { display: none; }
    }
  </style>
</head>
<body class="body-padding">

@php
  $img = $product->preview_src ?? ($product->image_url ?? asset('images/placeholder.png'));

  // Normalize layoutSlots to include mask URL if available.
  $slotsForJs = [];
  if (!empty($layoutSlots) && is_array($layoutSlots)) {
      foreach ($layoutSlots as $k => $s) {
          $slot = (array)$s;
          $mask = $slot['mask'] ?? null;
          if (!$mask && !empty($slot['mask_svg_path'])) {
              $mask = '/files/' . ltrim($slot['mask_svg_path'], '/');
          }
          $slotsForJs[$k] = array_merge($slot, ['mask' => $mask ?? null]);
      }
  }
  $originalSlotsForJs = [];
  if (!empty($originalLayoutSlots) && is_array($originalLayoutSlots)) {
      foreach ($originalLayoutSlots as $k => $s) {
          $slot = (array)$s;
          $mask = $slot['mask'] ?? null;
          if (!$mask && !empty($slot['mask_svg_path'])) {
              $mask = '/files/' . ltrim($slot['mask_svg_path'], '/');
          }
          $originalSlotsForJs[$k] = array_merge($slot, ['mask' => $mask ?? null]);
      }
  }
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
      <div class="desktop-only mb-2">
        <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
        <input id="np-num"  type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
      </div>

      <div class="mobile-only">
        <div class="np-input-group">
          <div class="np-field">
            <input id="np-name-mobile" type="text" maxlength="11" class="np-input" placeholder="YOUR NAME">
            <span class="np-max">MAX. 11</span>
          </div>

          <div class="np-field">
            <input id="np-num-mobile" type="text" maxlength="2" inputmode="numeric" class="np-input" placeholder="09">
            <span class="np-max">MAX. 2</span>
          </div>
        </div>
      </div>

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

      @if(!empty($showUpload))
      <div class="mb-2" id="np-upload-block" style="margin-top:6px;">
        <input id="np-upload-image" type="file" accept="image/*" class="form-control" />
        <div style="margin-top:6px;">
          <button id="np-user-image-reset" type="button" class="btn btn-sm btn-outline-light" style="display:none;margin-right:6px;">Remove Image</button>
          <label for="np-user-image-scale" style="color:#fff; font-size:.85rem;margin-right:6px;display:none;" id="np-user-image-scale-label">Scale</label>
          <input id="np-user-image-scale" type="range" min="50" max="200" value="100" style="vertical-align: middle; display:none;" />
        </div>
      </div>
      @endif
    </div>

    <div class="col-md-3 np-col order-3 order-md-3 right-layout mobile-layout">
      <h4 class="desktop-display">{{ $product->name ?? ($product->title ?? 'Product') }}</h4>
      <form id="np-atc-form" method="post" action="{{ route('designer.addtocart') }}">
        @csrf
        <input type="hidden" name="name_text" id="np-name-hidden">
        <input type="hidden" name="number_text" id="np-num-hidden">
        <input type="hidden" name="font" id="np-font-hidden">
        <input type="hidden" name="color" id="np-color-hidden">
        <input type="hidden" id="np-uploaded-logo-url" name="uploaded_logo_url" value="">
        <input type="hidden" name="preview_data" id="np-preview-hidden">
        <input type="hidden" name="product_id" id="np-product-id" value="{{ $product->id ?? $product->local_id ?? '' }}">
        <input type="hidden" name="shopify_product_id" id="np-shopify-product-id" value="{{ $product->shopify_product_id ?? $product->shopify_id ?? '' }}">
        <input type="hidden" name="variant_id" id="np-variant-id" value="">
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

        <div class="d-flex gap-2">
          <button id="save-design-btn" type="button" class="btn btn-primary">Save Design</button>
          <button id="np-atc-btn" type="submit" class="btn btn-secondary">Add to Cart</button>
          <a href="#" class="btn btn-success ms-auto" id="btn-add-team">Add Team Players</a>
        </div>
      </form>
    </div>
  </div>
</div>

@php
  $variantMap = [];
  if (!empty($product) && $product->relationLoaded('variants')) {
      foreach ($product->variants as $v) {
          $rawKey = trim((string)($v->option_value ?? $v->option_name ?? $v->title ?? ''));
          if ($rawKey === '') continue;
          $variantMap[strtoupper($rawKey)] = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? '');
      }
  }
@endphp

@php
  $img = $product->preview_src ?? ($product->image_url ?? asset('images/placeholder.png'));
@endphp

<script>
  window.layoutSlots = {!! json_encode($slotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  window.originalLayoutSlots = {!! json_encode($originalSlotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  window.showUpload = {{ !empty($showUpload) ? 'true' : 'false' }};
  window.hasArtworkSlot = {{ !empty($hasArtworkSlot) ? 'true' : 'false' }};
  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
  window.variantMap = {!! json_encode($variantMap, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!} || {};
  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";
</script>

<script>
/* helper: choose preferred artwork slot */
window.findPreferredSlot = function(){
  try {
    const orig = (typeof window.originalLayoutSlots === 'object' && window.originalLayoutSlots) ? window.originalLayoutSlots : {};
    const filtered = (typeof window.layoutSlots === 'object' && window.layoutSlots) ? window.layoutSlots : {};
    const useSlots = Object.keys(orig).length ? orig : filtered;
    const keys = Object.keys(useSlots);
    if (!keys.length) return null;
    const arr = keys.map(k => ({ key: k, slot: useSlots[k] }));
    const masked = arr.filter(i => i.slot && (i.slot.mask || i.slot.mask_svg_path));
    if (masked.length) {
      const front = masked.filter(i => parseFloat(i.slot.left_pct || 0) < 50);
      if (front.length) return front[0].slot;
      return masked[0].slot;
    }
    const preferNames = ['logo','artwork','team_logo','graphic','image','badge','patch'];
    for (const p of preferNames) if (useSlots[p]) return useSlots[p];
    const others = arr.filter(i => {
      const keyLower = (i.key||'').toLowerCase();
      const slotKey = (i.slot?.slot_key||'').toLowerCase();
      return keyLower!=='name' && keyLower!=='number' && slotKey!=='name' && slotKey!=='number';
    });
    const left = others.filter(i => parseFloat(i.slot.left_pct || 0) < 50);
    if (left.length) return left[0].slot;
    if (useSlots['number']) return useSlots['number'];
    if (useSlots['name']) return useSlots['name'];
    return useSlots[keys[0]] || null;
  } catch(e) { return null; }
};
</script>

<script>
/**
 * Compose preview: draw base image + name + number into a canvas and return dataURL
 * tweak positions/sizes if your template layout differs
 */
async function composePreviewDataURL(options = {}) {
  const baseImgEl = document.getElementById('np-base');
  const name = (document.getElementById('np-name')?.value || document.getElementById('np-name-mobile')?.value || '').trim().toUpperCase();
  const number = (document.getElementById('np-num')?.value || document.getElementById('np-num-mobile')?.value || '').trim();
  const fontKey = document.getElementById('np-font')?.value || 'bebas';
  const color = document.getElementById('np-color')?.value || '#FFFFFF';
  const canvasW = options.width || 1200;
  const canvasH = options.height || 900;

  if (!baseImgEl) return null;
  const baseSrc = baseImgEl.getAttribute('src');
  if (!baseSrc) return null;

  // load image with crossOrigin when possible
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.src = baseSrc;

  await new Promise(resolve => {
    if (img.complete && img.naturalWidth) return resolve();
    img.onload = () => resolve();
    img.onerror = () => resolve(); // continue even if load fails
  });

  const canvas = document.createElement('canvas');
  canvas.width = canvasW;
  canvas.height = canvasH;
  const ctx = canvas.getContext('2d');

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvasW, canvasH);

  // draw base image scaled to canvas
  const imgW = img.naturalWidth || canvasW;
  const imgH = img.naturalHeight || canvasH;
  const imgRatio = imgW / imgH;
  let drawW = canvasW, drawH = canvasH;
  if (imgRatio > canvasW / canvasH) { drawW = canvasW; drawH = Math.round(canvasW / imgRatio); }
  else { drawH = canvasH; drawW = Math.round(canvasH * imgRatio); }
  const dx = Math.round((canvasW - drawW) / 2);
  const dy = Math.round((canvasH - drawH) / 2);
  try { ctx.drawImage(img, dx, dy, drawW, drawH); } catch(e) { console.warn('drawImage failed', e); }

  const fontMap = { bebas: 'Bebas Neue, Arial', anton: 'Anton, Arial', oswald: 'Oswald, Arial', impact: 'Impact, Arial' };
  const fontFamily = fontMap[fontKey] || fontMap.bebas;

  // NAME (upper back)
  if (name) {
    const nameFontSize = Math.round(canvasW * 0.06);
    ctx.font = `${nameFontSize}px ${fontFamily}`;
    ctx.fillStyle = color;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const nameX = Math.round(canvasW * 0.72);
    const nameY = Math.round(canvasH * 0.38);
    ctx.lineWidth = Math.max(2, Math.round(nameFontSize * 0.08));
    ctx.strokeStyle = '#000';
    ctx.strokeText(name, nameX, nameY);
    ctx.fillText(name, nameX, nameY);
  }

  // NUMBER (lower back)
  if (number) {
    const numFontSize = Math.round(canvasW * 0.1);
    ctx.font = `bold ${numFontSize}px ${fontFamily}`;
    ctx.fillStyle = color;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const numX = Math.round(canvasW * 0.72);
    const numY = Math.round(canvasH * 0.57);
    ctx.lineWidth = Math.max(3, Math.round(numFontSize * 0.08));
    ctx.strokeStyle = '#000';
    ctx.strokeText(number, numX, numY);
    ctx.fillText(number, numX, numY);
  }

  return canvas.toDataURL('image/png', 0.92);
}
</script>

<script>
(async function(){
  // element helpers
  const get = id => document.getElementById(id);
  const nameInputs = [get('np-name'), get('np-name-mobile')].filter(Boolean);
  const numInputs = [get('np-num'), get('np-num-mobile')].filter(Boolean);
  const fontEl = get('np-font');
  const colorEl = get('np-color');
  const pvName = get('np-prev-name'), pvNum = get('np-prev-num'), baseImg = get('np-base'), stage = get('np-stage');
  const saveBtn = get('save-design-btn'), addToCartBtn = get('np-atc-btn'), form = get('np-atc-form'), addTeam = get('btn-add-team');

  // sync name+number between mobile/desktop inputs and update preview overlay
  function syncPreview() {
    const nameVal = (nameInputs[0]?.value || nameInputs[1]?.value || '').toString().toUpperCase();
    const numVal = (numInputs[0]?.value || numInputs[1]?.value || '').toString().replace(/\D/g,'');
    nameInputs.forEach(i => i && (i.value = nameVal));
    numInputs.forEach(i => i && (i.value = numVal));
    if (pvName) pvName.textContent = nameVal || 'NAME';
    if (pvNum) pvNum.textContent = numVal || '09';
    applyLayout(); // position overlays
  }

  function syncHidden() {
    if (get('np-name-hidden')) get('np-name-hidden').value = (nameInputs[0]?.value || '').toUpperCase();
    if (get('np-num-hidden')) get('np-num-hidden').value = (numInputs[0]?.value || '').replace(/\D/g,'');
    if (get('np-font-hidden')) get('np-font-hidden').value = fontEl?.value || '';
    if (get('np-color-hidden')) get('np-color-hidden').value = colorEl?.value || '';
  }

  // small placement helpers (similar to your existing logic)
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
    if (!el) return;
    const s = computeStageSize(); if (!s) return;
    // default: center-ish if no slot
    if (!slot) {
      const cx = s.stageW * 0.5, cy = s.stageH * (slotKey==='number' ? 0.62 : 0.4);
      el.style.left = cx + 'px'; el.style.top = cy + 'px';
      el.style.transform = 'translate(-50%,-50%)';
      return;
    }
    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||0)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop  + ((slot.top_pct||0)/100)  * s.imgH + ((slot.height_pct||0)/200)*s.imgH);
    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));
    el.style.left = centerX + 'px';
    el.style.top  = centerY + 'px';
    el.style.width = areaWpx + 'px';
    el.style.height = areaHpx + 'px';
    el.style.transform = 'translate(-50%,-50%) rotate(' + ((slot.rotation||0)) + 'deg)';
    // font sizing basic approach
    const text = (el.textContent || '').toString().trim();
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile ? 1.05 : 1.00) : 1.00));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * (slotKey === 'number' ? 0.98 : 1.0));
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fontSize = Math.max(8, Math.min(fontSize, maxAllowed));
    el.style.fontSize = Math.floor(fontSize * 1.10) + 'px';
  }

  // we reuse findPreferredSlot
  function applyLayout(){
    try {
      const slot = window.findPreferredSlot();
      placeOverlay(pvName, slot && slot.name ? slot.name : slot, 'name');
      placeOverlay(pvNum, slot && slot.number ? slot.number : slot, 'number');
      // render mask images if provided
      if (window.layoutSlots) {
        Object.keys(window.layoutSlots).forEach(k => {
          const s = window.layoutSlots[k];
          if (!s || !s.mask) return;
          const id = 'mask-' + k;
          let el = document.getElementById(id);
          if (!el) {
            el = document.createElement('img');
            el.id = id;
            el.className = 'np-mask';
            el.src = s.mask;
            el.style.position = 'absolute';
            el.style.pointerEvents = 'none';
            el.style.zIndex = 40;
            el.style.objectFit = 'contain';
            stage.appendChild(el);
          }
          const st = computeStageSize();
          if (!st) return;
          const cx = Math.round(st.offsetLeft + ((s.left_pct||0)/100)*st.imgW + ((s.width_pct||0)/200)*st.imgW);
          const cy = Math.round(st.offsetTop + ((s.top_pct||0)/100)*st.imgH + ((s.height_pct||0)/200)*st.imgH);
          const wpx = Math.round(((s.width_pct||10)/100)*st.imgW);
          const hpx = Math.round(((s.height_pct||10)/100)*st.imgH);
          el.style.left = (cx - wpx/2) + 'px';
          el.style.top = (cy - hpx/2) + 'px';
          el.style.width = wpx + 'px';
          el.style.height = hpx + 'px';
          el.style.transform = 'rotate(' + (s.rotation||0) + 'deg)';
        });
      }
    } catch(e) { /* ignore */ }
  }

  // sync listeners for inputs
  nameInputs.forEach(el => el && el.addEventListener('input', ()=> { syncPreview(); syncHidden(); }));
  numInputs.forEach(el => el && el.addEventListener('input', ()=> { syncPreview(); syncHidden(); }));
  if (fontEl) fontEl.addEventListener('change', ()=> { pvName.className = 'np-overlay font-' + (fontEl.value||'bebas'); pvNum.className = 'np-overlay font-' + (fontEl.value||'bebas'); syncHidden(); });
  if (colorEl) colorEl.addEventListener('input', ()=> { pvName.style.color = colorEl.value; pvNum.style.color = colorEl.value; syncHidden(); });

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

  // initial sync
  pvName.className = 'np-overlay font-bebas';
  pvNum.className = 'np-overlay font-bebas';
  if (colorEl) { pvName.style.color = colorEl.value; pvNum.style.color = colorEl.value; }
  syncPreview(); syncHidden();
  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 60));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 80));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  // SAVE button: send composed dataURL to admin.design.order.store
  if (saveBtn) {
    saveBtn.addEventListener('click', async function(e){
      e.preventDefault();
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
      try {
        // simple validation
        const size = get('np-size')?.value || '';
        if (!size) { alert('Please select a size.'); saveBtn.disabled = false; saveBtn.textContent = 'Save Design'; return; }

        const preview_src = await composePreviewDataURL({ width: 1200, height: 900 });
        const payload = {
          product_id: parseInt(get('np-product-id')?.value) || null,
          shopify_product_id: get('np-shopify-product-id')?.value || null,
          variant_id: get('np-variant-id')?.value || null,
          name: get('np-name-hidden')?.value || get('np-name')?.value || '',
          number: get('np-num-hidden')?.value || get('np-num')?.value || '',
          font: get('np-font-hidden')?.value || get('np-font')?.value || '',
          color: get('np-color-hidden')?.value || get('np-color')?.value || '',
          size: size,
          quantity: parseInt(get('np-qty')?.value || '1', 10) || 1,
          preview_src: preview_src || null,
          uploaded_logo_url: window.lastUploadedLogoUrl || null
        };

        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ route("admin.design.order.store") }}', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
          },
          body: JSON.stringify(payload)
        });
        const data = await res.json().catch(()=>null);
        if (!res.ok) {
          console.error('Save failed', data);
          alert('Save failed: ' + (data?.message || res.status));
          return;
        }
        alert('Saved! Order ID: ' + (data?.order_id || '—'));
      } catch (err) {
        console.error(err);
        alert('Save failed, see console.');
      } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Design';
      }
    });
  }

  // Add-to-cart: uses html2canvas to generate preview_data and posts to shopify add-to-cart URL (existing behavior)
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    syncHidden();
    const size = get('np-size')?.value || '';
    if (!size) { alert('Please select a size.'); return; }
    const variantId = get('np-variant-id')?.value || '';
    if (!variantId) { alert('Please select a valid variant.'); return; }

    try {
      if (window.html2canvasAvailable !== false) {
        try {
          const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
          get('np-preview-hidden').value = canvas.toDataURL('image/png');
        } catch(e) { console.warn('html2canvas failed:', e); }
      }
      const properties = { 'Name': get('np-name-hidden')?.value || '', 'Number': get('np-num-hidden')?.value || '', 'Font': get('np-font-hidden')?.value || '', 'Color': get('np-color-hidden')?.value || '' };
      const qty = Math.max(1, parseInt(get('np-qty')?.value || '1', 10));
      const bodyArr = [];
      bodyArr.push('id=' + encodeURIComponent(variantId));
      bodyArr.push('quantity=' + encodeURIComponent(qty));
      for (const k in properties) bodyArr.push('properties[' + encodeURIComponent(k) + ']=' + encodeURIComponent(properties[k]));
      const body = bodyArr.join('&');
      const resp = await fetch('/cart/add', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' });
      const shopfront = (window.shopfrontUrl || '').replace(/\/+$/,'');
      const redirectUrl = shopfront + '/cart/' + variantId + ':' + qty;
      window.location.href = redirectUrl;
    } catch (err) {
      console.error('Add to cart error', err);
      alert('Something went wrong adding to cart.');
    }
  });

  // Add Team button: pass prefill params to team.create route
  addTeam?.addEventListener('click', function(e){
    e.preventDefault();
    const params = new URLSearchParams();
    const productId = get('np-product-id')?.value || null;
    if (productId) params.set('product_id', productId);
    if (get('np-name-hidden')?.value) params.set('prefill_name', get('np-name-hidden').value);
    if (get('np-num-hidden')?.value) params.set('prefill_number', get('np-num-hidden').value);
    if (get('np-font-hidden')?.value) params.set('prefill_font', get('np-font-hidden').value);
    if (get('np-color-hidden')?.value) params.set('prefill_color', encodeURIComponent(get('np-color-hidden').value || ''));
    const sizeVal = get('np-size')?.value || '';
    if (sizeVal) params.set('prefill_size', sizeVal);
    try { if (window.layoutSlots && Object.keys(window.layoutSlots||{}).length) params.set('layoutSlots', encodeURIComponent(JSON.stringify(window.layoutSlots))); } catch(e){}
    if (window.lastUploadedLogoUrl) params.set('prefill_logo', encodeURIComponent(window.lastUploadedLogoUrl));
    const base = "{{ route('team.create') }}";
    window.location.href = base + (params.toString() ? ('?' + params.toString()) : '');
  });

})();
</script>

<script>
/* Upload handling: upload to /designer/upload-temp (returns public url) and display local preview */
(async function(){
  const uploadEl = document.getElementById('np-upload-image');
  const stage = document.getElementById('np-stage');
  const baseImg = document.getElementById('np-base');
  const previewHidden = document.getElementById('np-preview-hidden');
  const removeBtn = document.getElementById('np-user-image-reset');
  const scaleRange = document.getElementById('np-user-image-scale');
  const scaleLabel = document.getElementById('np-user-image-scale-label');

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

  function placeUserImage(slot){
    if (!userImg) return;
    const s = computeStageSizeLocal();
    if (!s) return;
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
      userImg.style.zIndex = 300;
      return;
    }
    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200)*s.imgW);
    const centerY = Math.round(s.offsetTop + ((slot.top_pct||50)/100) * s.imgH + ((slot.height_pct||0)/200)*s.imgH);
    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));
    const scaledW = Math.round(areaWpx * (userImgScale));
    const scaledH = Math.round(areaHpx * (userImgScale));
    userImg.style.left = centerX + 'px';
    userImg.style.top  = centerY + 'px';
    userImg.style.width = scaledW + 'px';
    userImg.style.height = scaledH + 'px';
    let tx = 'translate(-50%,-50%)';
    if (slot.rotation) tx += ' rotate(' + slot.rotation + 'deg)';
    tx += ' scale(' + userImgScale + ')';
    userImg.style.transform = tx;
    userImg.style.zIndex = 300;
  }

  async function uploadTempFile(file) {
    try {
      const fd = new FormData();
      fd.append('file', file);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content') || '';
      const resp = await fetch('{{ route("designer.upload_temp") }}', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-CSRF-TOKEN': token }
      });
      if (!resp.ok) throw new Error('upload failed: ' + resp.status);
      const json = await resp.json();
      return json.url || null;
    } catch (err) {
      console.warn('uploadTempFile error', err);
      return null;
    }
  }

  async function handleFile(file) {
    if (!file) return;
    if (!/^image\//.test(file.type)) { alert('Please upload an image file (PNG, JPG, SVG).'); return; }
    const maxMB = 6;
    if (file.size > maxMB * 1024 * 1024) { alert('Please use an image smaller than ' + maxMB + ' MB.'); return; }

    let publicUrl = null;
    try {
      publicUrl = await uploadTempFile(file);
    } catch(e){ console.warn(e); }

    const reader = new FileReader();
    reader.onload = function(ev) {
      const dataUrl = ev.target.result;
      if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg);
      userImg = document.createElement('img');
      userImg.className = 'np-user-image';
      userImg.src = dataUrl;
      userImg.alt = 'User artwork';
      userImg.style.position = 'absolute';
      userImg.style.left = '50%';
      userImg.style.top = '50%';
      userImg.style.width = '100px';
      userImg.style.height = '100px';
      userImg.style.transform = 'translate(-50%,-50%)';
      userImg.style.objectFit = 'cover';
      userImg.style.pointerEvents = 'none';
      stage.appendChild(userImg);

      if (removeBtn) removeBtn.style.display = 'inline-block';
      if (scaleRange) { scaleRange.style.display = 'inline-block'; scaleLabel.style.display = 'inline-block'; scaleRange.value = 100; userImgScale = 1.0; }

      const slot = findPreferredSlot();
      placeUserImage(slot);
      userImg.onload = function(){ placeUserImage(slot); };

      if (publicUrl) {
        window.lastUploadedLogoUrl = publicUrl;
        // optionally set preview hidden as dataURL of stage so Add Team can later reuse
        setTimeout(()=> {
          try {
            html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 })
              .then(canvas => { previewHidden.value = canvas.toDataURL('image/png'); })
              .catch(()=>{});
          } catch(e){}
        }, 180);
      } else {
        window.lastUploadedLogoUrl = null;
      }
    };
    reader.readAsDataURL(file);
  }

  if (uploadEl) uploadEl.addEventListener('change', (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    handleFile(f);
  });

  if (removeBtn) removeBtn.addEventListener('click', function(){
    if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg);
    userImg = null;
    removeBtn.style.display = 'none';
    if (scaleRange) { scaleRange.style.display = 'none'; scaleLabel.style.display = 'none'; }
    if (previewHidden) previewHidden.value = '';
    window.lastUploadedLogoUrl = null;
  });

  if (scaleRange) scaleRange.addEventListener('input', function(){
    const v = parseInt(this.value || '100', 10);
    userImgScale = v / 100;
    placeUserImage(findPreferredSlot());
  });

  window.addEventListener('resize', function(){
    if (userImg) placeUserImage(findPreferredSlot());
    if (document.getElementById('np-base').complete) setTimeout(()=>{ /* re-render masks */ },40);
  });

})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
</body>
</html>
