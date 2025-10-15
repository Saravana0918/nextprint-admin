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
    .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; border-radius:8px; padding:8px; box-sizing: border-box; overflow: visible; }
    .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }
    .np-mask { position:absolute; pointer-events:none; z-index:40; transform-origin:center center; image-rendering:optimizeQuality; }
    .np-overlay { position: absolute; color: #D4AF37; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1.5px; text-align: center; text-shadow: 0 3px 10px rgba(0,0,0,0.65);  pointer-events: none; white-space: nowrap; line-height: 1; transform-origin: center center;
      z-index: 9999; }
    .np-overlay::before, .np-overlay::after { content: none; }
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
    text-transform: uppercase; letter-spacing: 1px; } .np-input::placeholder { color: #fff;
    opacity: 1; }
  .np-max { position: absolute; right: 2px; bottom: -18px; font-size: 10px; color: #fff; opacity: 0.9;
    font-weight: 700; letter-spacing: 0.4px; }
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

  // originalLayoutSlots may be present (controller passes full set). Normalize similarly.
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
      <!-- Desktop inputs (your original markup) -->
        <div class="desktop-only">
          <!-- keep your existing desktop inputs here -->
          <input id="np-name" type="text" maxlength="12" class="form-control mb-2 text-center" placeholder="YOUR NAME">
          <input id="np-num"  type="text" maxlength="3" inputmode="numeric" class="form-control mb-2 text-center" placeholder="09">
          <!-- other desktop-only elements (font picker, color dots, etc.) -->
        </div>

        <!-- Mobile inputs (what you added) -->
        <div class="mobile-only">
          <div class="np-input-group">
            <div class="np-field">
              <input id="np-name" type="text" maxlength="11" class="np-input" placeholder="YOUR NAME">
              <span class="np-max">MAX. 11</span>
            </div>

            <div class="np-field">
              <input id="np-num" type="text" maxlength="2" inputmode="numeric" class="np-input" placeholder="09">
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
      @else
      <!-- uploader not available for this product (controller decided no artwork region) -->
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

        @php
          $sizeOptions = [];
          if (!empty($product) && $product->relationLoaded('variants') && $product->variants->count()) {
              $sizeOptions = $product->variants->pluck('option_value')->map(fn($x)=>trim((string)$x))->unique()->values()->all();
          }
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
        <button id="save-design-btn" type="button" class="btn btn-outline-primary" style="margin-right:8px;">Save Design (Save)</button>
        <button id="np-atc-btn" type="submit" class="btn btn-primary d-none"> Add to Cart </button>
        <a href="#" class="btn btn-success" id="btn-add-team" style="margin-left:8px;">Add Team Players</a>
      </form>
    </div>
  </div>
</div>

@php
  // variant map
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
  // Prefer preview_src (admin uploaded) first, then product image_url, else placeholder
  $img = $product->preview_src ?? ($product->image_url ?? asset('images/placeholder.png'));
@endphp
<script>
  // filtered slots (name+number) — used by existing overlay logic
  window.layoutSlots = {!! json_encode($slotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  // original full layout (may include artwork/logo slots & masks) — prefer this for uploader placement
  window.originalLayoutSlots = {!! json_encode($originalSlotsForJs ?? [], JSON_NUMERIC_CHECK) !!};
  // flags from controller (ensure these variables exist in controller)
  window.showUpload = {{ !empty($showUpload) ? 'true' : 'false' }};
  window.hasArtworkSlot = {{ !empty($hasArtworkSlot) ? 'true' : 'false' }};

  window.personalizationSupported = {{ !empty($layoutSlots) ? 'true' : 'false' }};
  window.variantMap = {!! json_encode($variantMap, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!} || {};
  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";

  console.info('layoutSlots (filtered):', window.layoutSlots);
  console.info('originalLayoutSlots (full):', window.originalLayoutSlots);
  console.info('showUpload:', window.showUpload, 'hasArtworkSlot:', window.hasArtworkSlot);
</script>
<script>
/* MUST be placed before any code that calls findPreferredSlot() */
window.findPreferredSlot = function(){
  try {
    const orig = (typeof window.originalLayoutSlots === 'object' && window.originalLayoutSlots) ? window.originalLayoutSlots : {};
    const filtered = (typeof window.layoutSlots === 'object' && window.layoutSlots) ? window.layoutSlots : {};
    const useSlots = Object.keys(orig).length ? orig : filtered;
    const keys = Object.keys(useSlots);
    if (!keys.length) return null;

    const arr = keys.map(k => ({ key: k, slot: useSlots[k] }));

    // 1) Prefer slot that has a mask (SVG) and is on the left side (front)
    const masked = arr.filter(i => i.slot && (i.slot.mask || i.slot.mask_svg_path));
    if (masked.length) {
      const front = masked.filter(i => parseFloat(i.slot.left_pct || 0) < 50);
      if (front.length) { console.log('Using FRONT masked slot', front[0].key); return front[0].slot; }
      console.log('Using first masked slot', masked[0].key);
      return masked[0].slot;
    }

    // 2) Prefer named artwork/logo slots
    const preferNames = ['logo','artwork','team_logo','graphic','image','badge','patch'];
    for (const p of preferNames) if (useSlots[p]) { console.log('Using named slot', p); return useSlots[p]; }

    // 3) Prefer any non-name/number slot on left half
    const others = arr.filter(i => {
      const keyLower = (i.key||'').toLowerCase();
      const slotKey = (i.slot?.slot_key||'').toLowerCase();
      return keyLower!=='name' && keyLower!=='number' && slotKey!=='name' && slotKey!=='number';
    });
    const left = others.filter(i => parseFloat(i.slot.left_pct || 0) < 50);
    if (left.length) { console.log('Using generic front slot', left[0].key); return left[0].slot; }

    // 4) fallback
    console.log('Fallback slot used');
    if (useSlots['number']) return useSlots['number'];
    if (useSlots['name']) return useSlots['name'];
    return useSlots[keys[0]] || null;
  } catch (e) {
    console.warn('findPreferredSlot failed', e);
    return null;
  }
};
</script>


<script>
(function(){
  const $ = id => document.getElementById(id);

// helper: find visible input (prefer mobile if small screen)
function findVisibleInput(id) {
  // prefer an input inside .mobile-only on mobile
  try {
    if (window.innerWidth <= 767) {
      const m = document.querySelector('.mobile-only #' + id);
      if (m) return m;
    }
    // otherwise prefer desktop explicitly
    const d = document.querySelector('.desktop-only #' + id);
    if (d) return d;
  } catch(e) { /* ignore */ }
  // fallback to any id match
  return document.getElementById(id);
}

// grab elements (use visible ones for main listeners)
// but also keep references to both mobile+desktop to sync values
const nameEl  = findVisibleInput('np-name');
const numEl   = findVisibleInput('np-num');
const fontEl  = findVisibleInput('np-font') || $('np-font');
const colorEl = findVisibleInput('np-color') || $('np-color');

const pvName  = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');

// also find possible alternate inputs (both mobile & desktop) so we can attach listeners to all
const altNameEls = Array.from(document.querySelectorAll('#np-name')); // may include duplicate ids: select all
const altNumEls  = Array.from(document.querySelectorAll('#np-num'));
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
    const s = computeStageSize(); if(!s) return;
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
    el.style.zIndex = (slotKey === 'number' ? 99995 : 99994);

    const text = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
    const chars = Math.max(1, text.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile ? 1.05 : 1.00) : 1.00));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let fontSize = Math.floor(Math.min(heightCandidate, widthCap) * (slotKey === 'number' ? 0.98 : 1.0));
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

  // Renders stored masks (if any) as images positioned on-stage
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

  function applyLayout(){
    if (!baseImg || !baseImg.complete) return;
    if (layout && layout.name) placeOverlay(pvName, layout.name, 'name'); else { pvName.style.left='50%'; pvName.style.top='45%'; pvName.style.transform='translate(-50%,-50%)'; }
    if (layout && layout.number) placeOverlay(pvNum, layout.number, 'number'); else { pvNum.style.left='50%'; pvNum.style.top='65%'; pvNum.style.transform='translate(-50%,-50%)'; }
    renderMasks();
  }

  // place user image inside chosen slot (cover)
  function placeUserImage(slot){
    if (!userImg) return;
    const s = computeStageSize(); if (!s) return;

    // no slot → center cover stage image
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

    // Cover the area — userImgScale multiplies area dimension
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
    userImg.style.zIndex = 300; // below overlays but above masks
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
      $('np-variant-id').value = window.variantMap[k] || window.variantMap[k.toUpperCase()] || window.variantMap[k.toLowerCase()] || '';
    } else { if ($('np-variant-id')) $('np-variant-id').value = ''; }
  }

  // events
  // attach input listeners to every name input found (desktop + mobile)
altNameEls.forEach(el => {
  el.addEventListener('input', ()=> {
    // sync value to all matching inputs immediately (keeps desktop/mobile values identical)
    altNameEls.forEach(x => { if (x !== el) x.value = el.value; });
    syncPreview(); syncHidden(); updateATCState();
  });
});

// attach input listeners to every number input found
altNumEls.forEach(el => {
  el.addEventListener('input', (e) => {
    e.target.value = (e.target.value || '').replace(/\D/g,'').slice(0,3);
    // propagate sanitized value to all number inputs
    altNumEls.forEach(x => { if (x !== el) x.value = e.target.value; });
    syncPreview(); syncHidden(); updateATCState();
  });
});
  if (fontEl) fontEl.addEventListener('change', ()=>{ applyFont(fontEl.value); syncHidden(); syncPreview(); });
  if (colorEl) colorEl.addEventListener('input', ()=>{ if(pvName) pvName.style.color = colorEl.value; if(pvNum) pvNum.style.color = colorEl.value; syncHidden(); });

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

  function updateATCState(){ if (!btn) return; btn.disabled = false; }

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
    try { sizeEl.dispatchEvent(new Event('change')); } catch(e) {}
  }

  if (addTeam) addTeam.addEventListener('click', function(e) {
  e.preventDefault();
  const productId = $('np-product-id')?.value || null;
  const params = new URLSearchParams();
  if (productId) params.set('product_id', productId);
  if (nameEl?.value) params.set('prefill_name', nameEl.value);
  if (numEl?.value) params.set('prefill_number', numEl.value.replace(/\D/g,'')); 
  if (fontEl?.value) params.set('prefill_font', fontEl.value);
  if (colorEl?.value) params.set('prefill_color', encodeURIComponent(colorEl.value));
  const sizeVal = $('np-size')?.value || '';
  if (sizeVal) params.set('prefill_size', sizeVal);
  try { if (window.layoutSlots && Object.keys(window.layoutSlots || {}).length) params.set('layoutSlots', encodeURIComponent(JSON.stringify(window.layoutSlots))); } catch (err) {}
  // NEW: include public logo URL (if available)
  try {
    if (window.lastUploadedLogoUrl) params.set('prefill_logo', encodeURIComponent(window.lastUploadedLogoUrl));
  } catch(e){}

  const base = "{{ route('team.create') }}";
  window.location.href = base + (params.toString() ? ('?' + params.toString()) : '');
});



  // init
  applyFont(fontEl?.value || 'bebas');
  if (pvName && colorEl) pvName.style.color = colorEl.value;
  if (pvNum && colorEl) pvNum.style.color = colorEl.value;
  syncPreview(); syncHidden(); updateATCState();

  baseImg.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('orientationchange', ()=> setTimeout(applyLayout, 200));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  // add to cart submit (keeps your original behavior)
  form?.addEventListener('submit', async function(evt){
    evt.preventDefault();
    const size = $('np-size')?.value || '';
    if (!size) { alert('Please select a size.'); return; }
    if (!NAME_RE.test(nameEl.value||'') || !NUM_RE.test(numEl.value||'')) { alert('Please enter valid Name and Number'); return; }
    syncHidden();
    const variantId = (document.getElementById('np-variant-id') || { value: '' }).value;
    if (!variantId || !/^\d+$/.test(variantId)) { alert('Variant not selected or invalid. Please re-select size.'); return; }
    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }
    try {
      try {
        const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
        const dataUrl = canvas.toDataURL('image/png');
        $('np-preview-hidden').value = dataUrl;
      } catch(e) { console.warn('html2canvas failed, continuing without preview:', e); }
      const properties = { 'Name': $('np-name-hidden')?.value || '', 'Number': $('np-num-hidden')?.value || '', 'Font': $('np-font-hidden')?.value || '', 'Color': $('np-color-hidden')?.value || '' };
      const qty = Math.max(1, parseInt($('np-qty')?.value || '1', 10));
      const bodyArr = [];
      bodyArr.push('id=' + encodeURIComponent(variantId));
      bodyArr.push('quantity=' + encodeURIComponent(qty));
      for (const k in properties) { bodyArr.push('properties[' + encodeURIComponent(k) + ']=' + encodeURIComponent(properties[k])); }
      const body = bodyArr.join('&');
      const resp = await fetch('/cart/add', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body, credentials: 'same-origin' });
      const shopfront = (window.shopfrontUrl || '').replace(/\/+$/,'');
      const redirectUrl = shopfront + '/cart/' + variantId + ':' + qty;
      window.location.href = redirectUrl;
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
(function(){
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

  // ---- upload helper: sends file to server, returns public URL ----
async function uploadFileToServer(file) {
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
    console.warn('uploadFileToServer error', err);
    return null;
  }
}

// ---- main file handler: uploads + shows preview ----
async function handleFile(file) {
  if (!file) return;
  if (!/^image\//.test(file.type)) { alert('Please upload an image file (PNG, JPG, SVG).'); return; }
  const maxMB = 6;
  if (file.size > maxMB * 1024 * 1024) { alert('Please use an image smaller than ' + maxMB + ' MB.'); return; }

  // 1) Upload to server to get public URL
  let publicUrl = null;
  try {
    const fd = new FormData();
    fd.append('file', file);
    // CSRF token if needed (Laravel blade provides it in the page)
    const token = document.querySelector('input[name="_token"]')?.value;
    const resp = await fetch('/designer/upload-temp', {
      method: 'POST',
      headers: (token ? { 'X-CSRF-TOKEN': token } : {}),
      body: fd,
      credentials: 'same-origin'
    });
    const json = await resp.json().catch(()=>null);
    if (resp.ok && json && json.url) {
      publicUrl = json.url;
    } else {
      console.warn('upload-temp failed or did not return url', json);
    }
  } catch (err) {
    console.warn('upload-temp error', err);
  }

  // 2) If server didn't return public URL, fallback to local dataURL
  const reader = new FileReader();
  reader.onload = function(ev) {
    const dataUrl = ev.target.result;

    // create userImg on stage (same as before)
    if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg);
    userImg = document.createElement('img');
    userImg.className = 'np-user-image';
    userImg.src = dataUrl; // display immediately from dataURL
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

    // 3) If we have publicUrl, store it on window for add-team link
    if (publicUrl) {
      window.lastUploadedLogoUrl = publicUrl; // used by Add Team Players
      // also set the hidden preview (optional)
      try {
        setTimeout(()=> {
          html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 })
           .then(canvas => { previewHidden.value = canvas.toDataURL('image/png'); })
           .catch(()=>{});
        }, 180);
      } catch(e){}
    } else {
      // no public URL; keep using dataURL locally but won't be accessible on team page
      window.lastUploadedLogoUrl = null;
    }
  };
  reader.readAsDataURL(file);
}


  if (uploadEl) uploadEl.addEventListener('change', function(e){ const f = e.target.files && e.target.files[0]; if (!f) return; handleFile(f); });
  if (removeBtn) removeBtn.addEventListener('click', function(){ if (userImg && userImg.parentNode) userImg.parentNode.removeChild(userImg); userImg = null; removeBtn.style.display = 'none'; if (scaleRange) { scaleRange.style.display = 'none'; scaleLabel.style.display = 'none'; } if (previewHidden) previewHidden.value = ''; });
  if (scaleRange) scaleRange.addEventListener('input', function(){ const v = parseInt(this.value || '100', 10); userImgScale = v / 100; const slot = findPreferredSlot(); placeUserImage(slot); });

  window.addEventListener('resize', function(){ if (userImg) placeUserImage(findPreferredSlot()); if (document.getElementById('np-base').complete) setTimeout(()=>{ renderMasks(); },40); });
  document.fonts?.ready.then(()=> { if (userImg) placeUserImage(findPreferredSlot()); setTimeout(()=>{ renderMasks(); },80); });

  function renderMasks(){
    try {
      // call the same renderMasks in the other script if loaded
      if (typeof window.layoutSlots !== 'undefined') {
        const evt = new Event('renderMasksCustom');
        window.dispatchEvent(evt);
      }
    } catch(e){}
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(async function(){
  const saveBtn = document.getElementById('save-design-btn'); // your Save button
  const stage = document.getElementById('np-stage');
  const previewHidden = document.getElementById('np-preview-hidden');
  const atcBtn = document.getElementById('np-atc-btn');
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  async function makePreviewDataURL(){
    try {
      // ensure html2canvas script is loaded (you already include it)
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor: null, scale: window.devicePixelRatio || 1 });
      return canvas.toDataURL('image/png');
    } catch (err) {
      console.warn('html2canvas failed:', err);
      return null;
    }
  }

  saveBtn?.addEventListener('click', async function(e){
    e.preventDefault();
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    // sync hidden values (name/number/font/color/variant are kept in sync by your other code)
    // const name = document.getElementById('np-name-hidden').value etc - but hidden inputs already exist
    // Generate preview (prefer dataURL)
    const previewDataUrl = await makePreviewDataURL();

    // gather payload
    const payload = {
      product_id: parseInt(new URL(location.href).searchParams.get('product_id')) || document.getElementById('np-product-id')?.value || null,
      shopify_product_id: document.getElementById('np-shopify-product-id')?.value || null,
      variant_id: document.getElementById('np-variant-id')?.value || null,
      name: document.getElementById('np-name-hidden')?.value || null,
      number: document.getElementById('np-num-hidden')?.value || null,
      font: document.getElementById('np-font-hidden')?.value || null,
      color: document.getElementById('np-color-hidden')?.value || null,
      size: document.getElementById('np-size')?.value || null,
      quantity: parseInt(document.getElementById('np-qty')?.value || '1', 10),
      preview_src: previewDataUrl // send dataURL (server will save it)
    };

    try {
      const res = await fetch('{{ route("admin.design.order.store") }}', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });

      const data = await res.json().catch(()=>({}));

      if (!res.ok) {
        console.error('Save failed', data);
        alert('Save failed: ' + (data.message || res.status));
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Design (Save)';
        return;
      }

      // Success: server returned preview_url (public URL) ideally
      const previewUrl = data.preview_url || data.preview_src || previewDataUrl || null;
      if (previewUrl && previewHidden) {
        previewHidden.value = previewUrl;
      }

      // enable / unhide Add to Cart button
      if (atcBtn) {
        atcBtn.disabled = false;
        atcBtn.classList.remove('d-none'); // if you hide it with d-none initially
        atcBtn.textContent = 'Add to Cart';
      }

      alert('Design saved — ID ' + (data.order_id || '—'));
    } catch (err) {
      console.error('Save error', err);
      alert('Save failed, see console.');
    } finally {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Design (Save)';
    }
  });
})();
</script>
</body>
</html>
