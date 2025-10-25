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
        // sizeOptions and variantMap come from controller now
        // fallback: if not provided, build from product just-in-time
        if (empty($sizeOptions) && !empty($product) && $product->relationLoaded('variants') && $product->variants->count()) {
            $sizeOptions = [];
            foreach ($product->variants as $v) {
                $label = trim((string)($v->option_value ?? $v->option_name ?? $v->title ?? ''));
                $variantId = (string)($v->shopify_variant_id ?? $v->variant_id ?? $v->id ?? '');
                if ($label === '' || $variantId === '') continue;
                $sizeOptions[] = ['label' => $label, 'variant_id' => $variantId];
            }
        }
      @endphp

      <div class="mb-2">
        <select id="np-size" name="size" class="form-select" required>
          <option value="">Select Size</option>
          @if(!empty($sizeOptions) && is_array($sizeOptions))
            @foreach($sizeOptions as $opt)
              {{-- $opt = ['label' => 'XL', 'variant_id' => '45229...'] --}}
              <option value="{{ $opt['variant_id'] }}">{{ $opt['label'] }}</option>
            @endforeach
          @endif
        </select>
      </div>

        <div class="mb-2">
          <input id="np-qty" name="quantity" type="number" min="1" value="1" class="form-control">
        </div>
        <div class="d-flex align-items-center mb-3">
    <!-- LEFT group -->
    <div class="d-flex align-items-center">
      <button id="save-design-btn" type="button" class="btn btn-primary me-2" style="padding: .25rem .5rem;font-size: .96rem;">
        Add To Cart
      </button>

      <button id="np-share-img-btn" type="button" class="btn btn-outline-light ms-2" style="padding:.25rem .6rem; display:none;">
        Share Image
      </button>

      <button id="np-atc-btn" type="submit" class="btn btn-primary d-none" style="margin: 2px;" disabled>
        Add to Cart
      </button>
    </div>

    <!-- RIGHT group -->
    <div>
      <a href="#" class="btn btn-success" id="btn-add-team">Add Team Players</a>
    </div>
  </div>
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
/* SAFER: hide only the number INPUTs (and small wrappers) — do NOT hide .desktop-only column */
(function(){
  const hasNumber = !!(window.layoutSlots && window.layoutSlots.number);

  if (!hasNumber) {
    // 1) Desktop: hide the actual input element only (do not hide the .desktop-only wrapper)
    const desktopNum = document.querySelector('.desktop-only #np-num');
    if (desktopNum) {
      // hide input itself and its immediate margin wrapper (if any)
      desktopNum.style.display = 'none';
      // if bootstrap .mb-2 wrapper exists (input had class mb-2), hide that wrapper only if it's the direct parent
      const p = desktopNum.parentElement;
      if (p && p.classList && p.classList.contains('mb-2')) p.style.display = 'none';
      try { desktopNum.value = ''; } catch(e){}
    }

    // 2) Mobile: hide the np-field that wraps the mobile number input
    const mobileField = document.querySelector('#np-num-mobile')?.closest('.np-field');
    if (mobileField) mobileField.style.display = 'none';
    const mobileInput = document.getElementById('np-num-mobile');
    if (mobileInput) { try { mobileInput.value = ''; } catch(e){} }

    // 3) Hidden form field: clear + disable (so submission won't include stale number)
    const hidden = document.getElementById('np-num-hidden') || document.querySelector('input[name="number_text"]');
    if (hidden) {
      try { hidden.value = ''; hidden.disabled = true; } catch(e){}
    }

    // 4) Hide the preview overlay for number (defensive)
    const pvNum = document.getElementById('np-prev-num');
    if (pvNum) pvNum.style.display = 'none';

    console.info('Designer: number inputs safely hidden (no number slot).');
  } else {
    // ensure inputs visible when number slot exists (undo any previous hiding)
    const desktopNum = document.querySelector('.desktop-only #np-num');
    if (desktopNum) { desktopNum.style.display = ''; if (desktopNum.parentElement?.classList?.contains('mb-2')) desktopNum.parentElement.style.display = ''; }
    const mobileField = document.querySelector('#np-num-mobile')?.closest('.np-field');
    if (mobileField) mobileField.style.display = '';
    const hidden = document.getElementById('np-num-hidden') || document.querySelector('input[name="number_text"]');
    if (hidden) { hidden.disabled = false; }
    if (document.getElementById('np-prev-num')) document.getElementById('np-prev-num').style.display = '';
    console.info('Designer: number inputs enabled (number slot present).');
  }
})();
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

function findVisibleInput(id) {
  try {
    if (window.innerWidth <= 767) {
      // prefer mobile id when on small screens
      const m = document.querySelector('.mobile-only #' + id + '-mobile');
      if (m) return m;
    }
    const d = document.querySelector('.desktop-only #' + id);
    if (d) return d;
  } catch(e) {}
  // fallback: try desktop then mobile then any
  return document.querySelector('#' + id) || document.querySelector('#' + id + '-mobile') || null;
}

// grab elements (use visible ones for main listeners)
// but also keep references to both mobile+desktop to sync values
const nameEl  = findVisibleInput('np-name');
const numEl   = findVisibleInput('np-num');
const fontEl  = findVisibleInput('np-font') || $('np-font');
const colorEl = findVisibleInput('np-color') || $('np-color');

const pvName  = $('np-prev-name'), pvNum = $('np-prev-num'), baseImg = $('np-base'), stage = $('np-stage');

  const altNameEls = Array.from(document.querySelectorAll('#np-name, #np-name-mobile')).filter(Boolean);
  const altNumEls  = Array.from(document.querySelectorAll('#np-num, #np-num-mobile')).filter(Boolean);

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

  // NAME
  if (layout && layout.name) {
    pvName.style.display = 'flex';
    placeOverlay(pvName, layout.name, 'name');
  } else {
    // hide name overlay if no slot
    pvName.style.display = 'none';
  }

  // NUMBER
  if (layout && layout.number) {
    pvNum.style.display = 'flex';
    placeOverlay(pvNum, layout.number, 'number');
  } else {
    // hide number overlay if no slot
    pvNum.style.display = 'none';
  }

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

  // SIZE / VARIANT handling (robust: supports option.value being variant id OR label)
  const sizeVal = $('np-size')?.value || '';
  const variantInput = $('np-variant-id');
  if (!variantInput) return;

  if (!sizeVal) {
    variantInput.value = '';
    return;
  }

  // if sizeVal looks like a numeric variant id, use it directly
  if (/^\d+$/.test(sizeVal)) {
    variantInput.value = sizeVal;
    return;
  }

  // otherwise try to map via variantMap (keys likely are labels)
  if (window.variantMap && typeof window.variantMap === 'object') {
    const key = sizeVal.toString();
    variantInput.value = window.variantMap[key] || window.variantMap[key.toUpperCase()] || window.variantMap[key.toLowerCase()] || '';
  } else {
    variantInput.value = '';
  }
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
  if (fontEl) fontEl.addEventListener('change', ()=>{ 
  applyFont(fontEl.value); 
  window.selectedFontName = fontEl.options[fontEl.selectedIndex]?.text || fontEl.value;
  syncHidden(); syncPreview(); 
});
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
    // value already variant id (because blade set option.value = variant_id)
    const selectedVariantId = (sizeEl.value || '').toString().trim();
    const vInput = document.getElementById('np-variant-id');
    if (vInput) vInput.value = selectedVariantId;

    // optional: enable ATC if saved & valid
    syncHidden();
    syncPreview();
    updateATCState();
  });
  // trigger once to initialize
  try { sizeEl.dispatchEvent(new Event('change')); } catch(e) {}
}

  if (addTeam) addTeam.addEventListener('click', function(e) {
  e.preventDefault();
  const productId = $('np-product-id')?.value || null;
  // designer: when user clicks "Add Team Players"
const params = new URLSearchParams();
const name = (document.querySelector('#np-name')?.value || '').toUpperCase();
const number = (document.querySelector('#np-num')?.value || '').replace(/\D/g,'');
const font = (document.getElementById('np-font')?.value || '');
const color = (document.getElementById('np-color')?.value || '');
if (productId) params.set('product_id', productId);
if (name) params.set('prefill_name', encodeURIComponent(name));
if (number) params.set('prefill_number', encodeURIComponent(number));
if (font) params.set('prefill_font', encodeURIComponent(font));
if (color) params.set('prefill_color', encodeURIComponent(color));
// layoutSlots might be large JSON -> encode once
if (window.layoutSlots) params.set('layoutSlots', encodeURIComponent(JSON.stringify(window.layoutSlots)));
// logo must be public URL (see step 2)
if (window.lastUploadedLogoUrl) params.set('prefill_logo', encodeURIComponent(window.lastUploadedLogoUrl));

const base = "{{ route('team.create') }}"; // blade variable in designer
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
    if (!NAME_RE.test((nameEl?.value||''))) {
  alert('Please enter a valid Name'); return;
  }
  if (window.layoutSlots && window.layoutSlots.number) {
    if (!NUM_RE.test((numEl?.value||''))) { alert('Please enter a valid Number'); return; }
  }
    syncHidden();
    const variantId = (document.getElementById('np-variant-id') || { value: '' }).value;
    if (!variantId || !/^\d+$/.test(variantId.toString())) {
      alert('Variant not selected or invalid. Please re-select size.');
      return;
    }
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
  const saveBtn = document.getElementById('save-design-btn');
  const atcBtn  = document.getElementById('np-atc-btn');
  const previewHidden = document.getElementById('np-preview-hidden');
  const stage = document.getElementById('np-stage');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Ensure ATC button is hidden/disabled initially (so users must Save first)
  if (atcBtn) {
    atcBtn.disabled = true;
    // hide visually if you prefer: atcBtn.classList.add('d-none');
  }

  async function makePreviewDataURL(){
    try {
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      return canvas.toDataURL('image/png');
    } catch (err) {
      console.warn('html2canvas failed', err);
      return null;
    }
  }

  // helper: normalize color
function normalizeColorHex(c) {
  if (!c) return '#000000';
  c = c.toString().trim();
  if (c.charAt(0) !== '#') c = '#' + c;
  return c;
}

async function uploadBaseArtwork() {
  const nameEl = document.getElementById('np-prev-name');
  const numEl = document.getElementById('np-prev-num');
  const overlays = [nameEl, numEl];
  const originalDisplay = overlays.map(el => el ? el.style.display || '' : null);
  overlays.forEach(el => { if (el) el.style.display = 'none'; });

  await new Promise(r => setTimeout(r, 80));

  const previewNode = document.getElementById('np-stage');
  const canvas = await html2canvas(previewNode, {useCORS: true, scale: 2, backgroundColor: null});
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 0.92));

  overlays.forEach((el, idx) => { if (el && originalDisplay[idx] !== null) el.style.display = originalDisplay[idx]; });

  const form = new FormData();
  form.append('file', blob, 'preview_base_' + Date.now() + '.png');

  const res = await fetch('{{ route("designer.upload_temp") }}', { method: 'POST', body: form, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf } });
  if (!res.ok) {
    const txt = await res.text().catch(()=>null);
    throw new Error('Base upload failed: ' + (txt || res.status));
  }
  const data = await res.json().catch(()=>null);
  if (!data || !data.url) throw new Error('upload-temp did not return url');
  return data.url;
}

async function generateFullPreview() {
  const previewNode = document.getElementById('np-stage');
  const canvas = await html2canvas(previewNode, {useCORS: true, scale: 2, backgroundColor: null});
  const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 0.92));
  const form = new FormData();
  form.append('file', blob, 'preview_full_' + Date.now() + '.png');

  const res = await fetch('{{ route("designer.upload_temp") }}', { method: 'POST', body: form, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf } });
  if (!res.ok) {
    const txt = await res.text().catch(()=>null);
    throw new Error('Full preview upload failed: ' + (txt || res.status));
  }
  const data = await res.json().catch(()=>null);
  if (!data || !data.url) throw new Error('upload-temp did not return url');
  return data.url;
}

async function doSave() {
  try {
    const fullPreviewUrl = await generateFullPreview();
    let previewBaseUrl = null;
    try {
      previewBaseUrl = await uploadBaseArtwork();
    } catch (err) {
      console.warn('uploadBaseArtwork failed, continuing with full preview only', err);
      previewBaseUrl = null;
    }

    const selectedFontFamily = window.selectedFontName || 'Bebas Neue'; // exact family name registered
    const selectedColor = normalizeColorHex(window.selectedColor || '#000000');

    const payload = {
      preview_src: fullPreviewUrl,
      preview_base: previewBaseUrl,
      name_text: document.getElementById('input-name') ? document.getElementById('input-name').value : '',
      number_text: document.getElementById('input-number') ? document.getElementById('input-number').value : '',
      font: selectedFontFamily,
      color: selectedColor,
      // add other fields you send
    };

    const res = await fetch('/designer/save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.Laravel ? window.Laravel.csrfToken : '' },
      body: JSON.stringify(payload)
    });

    const result = await res.json();
    if (!res.ok) throw new Error(result.message || 'Save failed');
    alert('Saved!');
    return result;
  } catch (err) {
    console.error('doSave error', err);
    alert('Save failed: ' + (err.message || err));
    throw err;
  }
}


  async function doSave() {
    if (!saveBtn) return;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    // optional: ensure hidden fields are in sync before sending (your other JS should do this too)
    // document.getElementById('np-name-hidden').value = document.getElementById('np-name').value.toUpperCase();

    const previewDataUrl = await makePreviewDataURL();

    const payload = {
      product_id: document.getElementById('np-product-id')?.value || null,
      shopify_product_id: document.getElementById('np-shopify-product-id')?.value || null,
      variant_id: document.getElementById('np-variant-id')?.value || null,
      name: document.getElementById('np-name-hidden')?.value || null,
      number: document.getElementById('np-num-hidden')?.value || null,
      font: document.getElementById('np-font-hidden')?.value || null,
      color: document.getElementById('np-color-hidden')?.value || null,
      size: document.getElementById('np-size')?.value || null,
      quantity: parseInt(document.getElementById('np-qty')?.value || '1', 10),
      preview_src: previewDataUrl // send dataURL, server should save and return public URL
    };

    try {
      const res = await fetch('{{ route("admin.design.order.store") }}', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });

      const data = await res.json().catch(()=>({}));

      if (!res.ok || !data || data.success !== true) {
        console.error('Save failed', data);
        alert('Save failed: ' + (data.message || res.status || 'Unknown'));
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Design (Save)';
        return;
      }

      // success
      const previewUrl = data.preview_url || data.preview_src || previewDataUrl || null;
      if (previewUrl && previewHidden) previewHidden.value = previewUrl;

      // enable and show Add-to-cart
      if (atcBtn) {
        atcBtn.disabled = false;
        atcBtn.classList.remove('d-none'); // if you hid it with d-none earlier
        // optionally change text
        atcBtn.textContent = 'Buy Now';
      }

      // Remove the Save button from UI (or hide it)
      // Option A (remove completely):
      saveBtn.remove();

      // Option B (if you prefer hide): use this instead of remove()
      // saveBtn.style.display = 'none';
      // or saveBtn.classList.add('d-none');

      // Inform user
      alert('Design saved ✔ — you can now Add to Cart.');

    } catch (err) {
      console.error('Save error', err);
      alert('Save failed — check console.');
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Design (Save)';
    }
  }

  // Attach listener
  if (saveBtn) {
    saveBtn.addEventListener('click', function(e){
      e.preventDefault();
      doSave();
    }, { passive: false });
  }
})();
</script>
<script>
(async function(){
  const shareBtn = document.getElementById('np-share-img-btn');
  const stage = document.getElementById('np-stage');
  const previewHidden = document.getElementById('np-preview-hidden');
  const uploadUrl = "{{ route('designer.upload_temp') }}"; // blade route, already present in page
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Show share button when we have a preview (either saved or live)
  function updateShareVisibility() {
    try {
      if (!shareBtn) return;
      // show if previewHidden has a value OR we can render stage
      const previewExists = (previewHidden && previewHidden.value) || false;
      shareBtn.style.display = previewExists ? 'inline-block' : 'inline-block';
      // optionally hide until saved: previewExists ? show : hide
    } catch(e){}
  }
  updateShareVisibility();

  // utility: convert canvas to blob
  function canvasToBlob(canvas, type='image/png', quality=0.92) {
    return new Promise(resolve => canvas.toBlob(resolve, type, quality));
  }

  // upload blob to server using existing upload_temp endpoint
  async function uploadBlobGetUrl(blob, filename='preview.png') {
    try {
      const fd = new FormData();
      fd.append('file', blob, filename);
      const res = await fetch(uploadUrl, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: (csrf ? { 'X-CSRF-TOKEN': csrf } : {})
      });
      if (!res.ok) {
        const txt = await res.text().catch(()=>null);
        throw new Error('Upload failed: ' + (txt || res.status));
      }
      const json = await res.json().catch(()=>null);
      if (json && json.url) return json.url;
      throw new Error('Upload did not return URL');
    } catch(err){
      console.warn('uploadBlobGetUrl error', err);
      return null;
    }
  }

  async function makeCanvasBlob() {
    try {
      // ensure overlays are visible when capturing if you need them
      const canvas = await html2canvas(stage, { useCORS:true, backgroundColor:null, scale: window.devicePixelRatio || 1 });
      const blob = await canvasToBlob(canvas, 'image/png', 0.92);
      return { blob, canvas };
    } catch(e){
      console.error('makeCanvasBlob failed', e);
      return { blob: null, canvas: null };
    }
  }

  async function shareImageFlow() {
    try {
      shareBtn.disabled = true;
      shareBtn.textContent = 'Preparing...';

      const { blob, canvas } = await makeCanvasBlob();
      if (!blob) throw new Error('Could not create image');

      // 1) Try native file sharing (if supported)
      const canShareFiles = navigator.canShare && navigator.canShare({ files: [new File([blob], 'preview.png', { type: blob.type })] });
      if (canShareFiles) {
        const file = new File([blob], 'nextprint-preview.png', { type: blob.type });
        try {
          await navigator.share({
            files: [file],
            title: document.title || 'My Jersey Preview',
            text: 'Check out my customized jersey!'
          });
          shareBtn.textContent = 'Share Image';
          shareBtn.disabled = false;
          return;
        } catch (err) {
          // user cancelled or failed — continue to fallback
          console.warn('navigator.share(files) failed', err);
        }
      }

      // 2) Upload to server to get public URL, then share URL (works well for WhatsApp/Fb)
      const publicUrl = await uploadBlobGetUrl(blob, 'nextprint-preview-' + Date.now() + '.png');
      if (publicUrl) {
        // if navigator.share supports url-only:
        if (navigator.share) {
          try {
            await navigator.share({ title: document.title || 'My Jersey Preview', text: 'Check my design', url: publicUrl });
            shareBtn.textContent = 'Share Image';
            shareBtn.disabled = false;
            return;
          } catch (err) {
            console.warn('navigator.share(url) failed', err);
          }
        }

        // fallback: open WhatsApp web share with text+url
        const text = encodeURIComponent('Check my customized jersey: ');
        const wa = 'https://api.whatsapp.com/send?text=' + text + encodeURIComponent(publicUrl);
        // Open in new tab — user will complete share
        window.open(wa, '_blank');
        shareBtn.textContent = 'Share Image';
        shareBtn.disabled = false;
        return;
      }

      // 3) Final fallback: offer download of image (user can share manually)
      // create temporary link to download
      const dataUrl = canvas ? canvas.toDataURL('image/png') : null;
      if (dataUrl) {
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = 'nextprint-preview.png';
        document.body.appendChild(a);
        a.click();
        a.remove();
        alert('Preview downloaded. You can now share the saved image with friends.');
      } else {
        alert('Unable to prepare image for sharing.');
      }

    } catch (err) {
      console.error('shareImageFlow error', err);
      alert('Share failed: ' + (err.message || err));
    } finally {
      if (shareBtn) { shareBtn.disabled = false; shareBtn.textContent = 'Share Image'; }
    }
  }

  // attach
  if (shareBtn) {
    shareBtn.addEventListener('click', function(e){
      e.preventDefault();
      shareImageFlow();
    });
  }

  // optional: show share button after save (if save returns preview_url)
  // if your save handler sets previewHidden.value, observe it:
  const hidden = previewHidden;
  if (hidden) {
    const obs = new MutationObserver(() => updateShareVisibility());
    obs.observe(hidden, { attributes: true, attributeFilter: ['value'] });
  }

})();
</script>

</body>
</html>
