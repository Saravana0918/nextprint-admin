{{-- resources/views/team/create.blade.php --}}
@extends('layouts.app')
@section('hide_header', '1')

@section('content')
@php
  // Prefer preview_url from query first (sent from Designer)
$img = request()->query('preview_url')
    ?? ($product->preview_url ?? $product->preview_src ?? $product->image_url ?? asset('images/placeholder.png'));


  // Start with any server-side $prefill passed by controller (if any)
   $prefill = $prefill ?? [];

  // Merge query params passed from designer (URL encoded).
  try {
      $qName   = request()->query('prefill_name');
      $qNum    = request()->query('prefill_number');
      $qFont   = request()->query('prefill_font');
      $qColor  = request()->query('prefill_color');
      $qLogo   = request()->query('prefill_logo');
      $qLayout = request()->query('layoutSlots');

      if ($qName)  $prefill['prefill_name']   = urldecode($qName);
      if ($qNum)   $prefill['prefill_number'] = urldecode($qNum);
      if ($qFont)  $prefill['prefill_font']   = urldecode($qFont);
      if ($qColor) $prefill['prefill_color']  = urldecode($qColor);
      if ($qLogo)  $prefill['prefill_logo']   = urldecode($qLogo);

      if (!empty($qLayout)) {
          $decoded = @json_decode(urldecode($qLayout), true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $layoutSlots = $decoded;
          else {
              // fallback: maybe it wasn't encoded
              $decoded2 = @json_decode($qLayout, true);
              if (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) $layoutSlots = $decoded2;
          }
      }

  } catch(\Throwable $e) {
      // ignore
  }

  $layoutSlots = $layoutSlots ?? null;

  // build size options (preserve order of variants as returned)
  $sizeOptions = [];
  if (!empty($product) && $product->relationLoaded('variants') && $product->variants->count()) {
      foreach ($product->variants as $v) {
          $val = trim((string)($v->option_value ?? $v->option_name ?? ''));
          if ($val === '') continue;
          if (!in_array($val, $sizeOptions, true)) $sizeOptions[] = $val;
      }
  }

  // render HTML for options (safe-escaped)
  $sizeOptionsHtml = '<option value="">Size</option>';
  foreach ($sizeOptions as $opt) {
      $escaped = e($opt);
      $sizeOptionsHtml .= "<option value=\"{$escaped}\">{$escaped}</option>";
  }

  // server-side variantMap used by JS to resolve variant IDs
  $variantMap = [];
  if (!empty($product) && $product->relationLoaded('variants')) {
      foreach ($product->variants as $v) {
          $key = trim((string)($v->option_value ?? $v->option_name ?? ''));
          if ($key === '') continue;
          $variantMap[strtoupper($key)] = (string)($v->shopify_variant_id ?? $v->variant_id ?? '');
      }
  }
@endphp

<!-- copy same stage CSS as designer so measurements match exactly -->
<style>
  /* fonts (ensure same families as designer) */
  .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
  .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
  .font-oswald{font-family:'Oswald', Arial, sans-serif;}
  .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

  /* match designer stage exactly */
  .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; box-sizing: border-box; overflow: visible; }
  .np-stage img { width:100%; height:auto; border-radius:6px; display:block; }

  /* overlay styling (same as designer) */
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

  .preview-col .card-body {
    padding: 0.75rem 1rem;
  }

  .main-flex { align-items: flex-start; }
  @media (max-width: 991px) {
    .main-flex { flex-direction: column !important; }
    .preview-col { order: 1; width: 100% !important; margin-bottom: 1rem; }
    .form-col { order: 2; width: 100% !important; }
  }

  /* === mobile icon button rules & modal styles (added) === */
  /* show text by default (desktop) */
  .btn-remove--icon .btn-icon,
  .btn-preview--icon .btn-icon { display: none; }
  .btn-remove--icon .btn-text,
  .btn-preview--icon .btn-text { display: inline; }

  /* MOBILE: hide text, show icons */
  @media (max-width: 767px) {
    .btn-remove--icon .btn-text,
    .btn-preview--icon .btn-text { display: none; }
    .btn-remove--icon .btn-icon,
    .btn-preview--icon .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
    }

    /* tighten button padding so icons look neat */
    .btn-remove--icon,
    .btn-preview--icon {
      padding: 0.1rem 0.1rem;
      min-width: 38px;
    }

    /* optional: smaller font input sizes on mobile rows */
    .player-name { font-size: 14px; }
    .player-number { font-size: 14px; }
  }

  /* modal basics */
  .np-modal { position: fixed; inset: 0; z-index: 20000; display: none; align-items: center; justify-content: center; }
  .np-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.55); }
  .np-modal-dialog { position: relative; z-index: 20010; max-height: 92vh; overflow: auto; padding: 18px; }
  .np-modal-close { position: absolute; right: 6px; top: 6px; background: rgba(0,0,0,0.4); border: none; font-size: 26px; color: #fff; cursor: pointer; z-index: 20020; border-radius:6px; padding:4px 8px; }
  @media (max-width:767px) {
    .np-modal-dialog { width: calc(100% - 28px); }
  }
  @media (max-width: 767px) {
  .mobile-action-row { display: flex; align-items: center; gap: 6px; padding: 6px 0; flex-wrap: nowrap; width: 100%; }
 .mobile-action-row .btn { padding: .45rem .55rem;    font-size: .85rem; white-space: nowrap; line-height: 1; }
 .mobile-action-row .back-btn { margin-left: auto; }
 .mobile-action-row .btn { min-width: 0; } }

</style>

<div class="container py-4">
  <div class="d-flex align-items-start gap-4 main-flex">
    <!-- PREVIEW COLUMN (uses designer-like .np-stage) -->
    <div class="preview-col" style="width:534px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center" style="position:relative;">
          <div id="player-stage" class="np-stage" aria-hidden="false">
            <img id="player-base"
                 src="{{ $img }}"
                 alt="{{ $product->name ?? 'Product' }}"
                 crossorigin="anonymous"
                 onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">

            {{-- Logo element (populated from designer prefill or empty hidden) --}}
            @if(!empty($prefill['prefill_logo']))
              <img id="player-logo" src="{{ $prefill['prefill_logo'] }}" alt="Logo"
                  style="position:absolute; z-index:300; pointer-events:none; display:block;"
                  crossorigin="anonymous"
                  onerror="this.style.display='none';" />
            @else
              <img id="player-logo" src="" alt="Logo"
                  style="position:absolute; z-index:300; pointer-events:none; display:none;"
                  crossorigin="anonymous" onerror="this.style.display='none';" />
            @endif

            <div id="overlay-name" class="np-overlay font-bebas" aria-hidden="true"
                 style="z-index:30; pointer-events:none; font-weight:800;">NAME</div>

            <div id="overlay-number" class="np-overlay font-bebas" aria-hidden="true"
                 style="z-index:35; pointer-events:none; font-weight:900;">NUMBER</div>
          </div>

          <h5 class="card-title mt-3">{{ $product->name ?? 'Product' }}</h5>
        </div>
      </div>
    </div>

    <!-- FORM COLUMN -->
    <div class="flex-grow-1 form-col">
      <h3 class="mb-3">Add Team Players for: {{ $product->name ?? 'Product' }}</h3>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="post" action="{{ route('team.store') }}" id="team-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id ?? '' }}">
        <input type="hidden" name="shopify_product_id" value="{{ $product->shopify_product_id ?? '' }}">
        {{-- Persist uploaded logo for server-side storage --}}
        <input type="hidden" id="team-prefill-logo" name="team_logo_url" value="{{ $prefill['prefill_logo'] ?? '' }}">
        <input type="hidden" id="team-preview-url" name="preview_url" value="">
        <input type="hidden" id="team-id-hidden" name="team_id" value="">

        <div class="mb-3 mobile-action-row">
          <button type="button" id="btn-add-row" class="btn btn-primary">ADD NEW</button>

          <!-- Save as outline -->
          <button type="button" id="save-team-btn" class="btn btn-outline-primary">Add To Cart</button>

          <!-- Add To Cart (submit) -> initially disabled -->
          <button type="submit" id="team-addtocart-btn" class="btn btn-success d-none" disabled data-label="Buy Now">Buy Now</button>

          <!-- Back button placed at the end; on mobile this will be pushed to right -->
          <a href="{{ url()->previous() }}" class="btn btn-secondary back-btn">Back</a>
        </div>

        <div id="players-list" class="mb-4"></div>

        <input type="hidden" id="team-preview-data" name="team_preview_data" value="">
      </form>
    </div>
  </div>
</div>

{{-- expose server values to JS --}}
<script>
  window.prefill = {!! json_encode($prefill ?? []) !!};
  window.layoutSlots = {!! json_encode($layoutSlots ?? [], JSON_NUMERIC_CHECK) !!};
  window.hasNumberSlot = !!(typeof window.layoutSlots === 'object' && window.layoutSlots && window.layoutSlots.number);
  console.info('hasNumberSlot=', window.hasNumberSlot);
  if (!window.layoutSlots || !window.layoutSlots.number) {
  // hide number input column in each player row template (client side)
  // we already create rows dynamically — hide number inputs on createRow()
  document.querySelectorAll('.player-number').forEach(n => { n.closest('.player-row')?.classList.add('no-number'); n.style.display='none'; });
  // hide overlay number visually
  document.getElementById('overlay-number').style.display = 'none';
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  try {
    if (!window.hasNumberSlot) {
      // hide stage overlay number
      const ovNum = document.getElementById('overlay-number') || document.getElementById('player-overlay-number');
      if (ovNum) ovNum.style.display = 'none';

      // hide any existing inputs for number in list
      document.querySelectorAll('.player-number, input[name="players[][number]"]').forEach(el => {
        if (el) {
          el.style.display = 'none';
          try { el.value = ''; } catch(e){}
          const row = el.closest('.player-row');
          if (row) row.classList.add('no-number');
        }
      });

      // hide any visible header label for Number if present
      document.querySelectorAll('.player-number-label').forEach(l => l.style.display = 'none');

      console.info('Team: number input & overlay hidden because no number slot.');
    } else {
      console.info('Team: number slot exists, keeping number inputs visible.');
    }
  } catch(e) { console.warn('init hide error', e); }
});
</script>

<template id="player-row-template">
  <div class="card mb-2 p-2 player-row">
    <div class="d-flex gap-2 align-items-start row-controls">
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00" maxlength="3" inputmode="numeric" />
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME" maxlength="12" />
      <select name="players[][size]" class="form-select w-25 player-size">
        {!! $sizeOptionsHtml !!}
      </select>
      <input type="hidden" name="players[][font]" class="player-font">
      <input type="hidden" name="players[][color]" class="player-color">

      <!-- Remove button: text on desktop, close icon on mobile -->
      <button type="button" class="btn btn-danger btn-remove btn-remove--icon">
        <span class="btn-text">Remove</span>
        <span class="btn-icon" aria-hidden="true">
          <!-- small close icon SVG -->
          <svg viewBox="0 0 20 20" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 4l12 12M16 4L4 16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </button>

      <!-- Preview button: text on desktop, eye icon on mobile -->
      <button type="button" class="btn btn-outline-primary btn-preview btn-preview--icon">
        <span class="btn-text">Preview</span>
        <span class="btn-icon" aria-hidden="true">
          <!-- eye icon SVG -->
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
      </button>
    </div>
  </div>
</template>

<script>
  window.variantMap = {!! json_encode($variantMap, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!} || {};
  console.info('team variantMap', window.variantMap);
  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const list = document.getElementById('players-list');
  try {
    if (!window.hasNumberSlot) {
      // hide overlay number if present
      const ovNum = document.getElementById('overlay-number');
      if (ovNum) ovNum.style.display = 'none';

      // hide any existing player-number inputs (in case template already rendered)
      document.querySelectorAll('.player-number, input[name="players[][number]"]').forEach(el => {
        try { el.style.display = 'none'; el.value = ''; } catch(e) {}
        const row = el.closest('.player-row');
        if (row) row.classList.add('no-number');
      });

      console.info('Team (init): number UI hidden (no number slot).');
    } else {
      console.info('Team (init): number slot present, number UI visible.');
    }
  } catch(err) { console.warn('init hide error', err); }
  const tpl = document.getElementById('player-row-template');
  const addBtn = document.getElementById('btn-add-row');
  const form = document.getElementById('team-form');

  const stageEl = document.getElementById('player-stage');
  const imgEl = document.getElementById('player-base');
  const ovName = document.getElementById('overlay-name');
  const ovNum  = document.getElementById('overlay-number');
  const logoEl = document.getElementById('player-logo');
  const hiddenTeamLogo = document.getElementById('team-prefill-logo');

  const pf = window.prefill || {};
  const layout = (typeof window.layoutSlots === 'object' && Object.keys(window.layoutSlots || {}).length)
                 ? window.layoutSlots
                 : {
                    name: { left_pct:50, top_pct:25, width_pct:85, height_pct:8, rotation:0 },
                    number: { left_pct:50, top_pct:54, width_pct:70, height_pct:12, rotation:0 }
                 };

  // Map designer font key -> css class (same as designer)
  const fontClassMap = { 'bebas': 'font-bebas', 'anton': 'font-anton', 'oswald': 'font-oswald', 'impact': 'font-impact' };
  window.fontClassMap = fontClassMap; // expose for modal

  function computeStageSize(stage, img) {
    if (!stage || !img) return null;
    const stageRect = stage.getBoundingClientRect();
    const imgRect = img.getBoundingClientRect();
    return {
      offsetLeft: Math.round(imgRect.left - stageRect.left),
      offsetTop: Math.round(imgRect.top - stageRect.top),
      imgW: Math.max(1, imgRect.width),
      imgH: Math.max(1, imgRect.height),
      stageW: Math.max(1, stageRect.width),
      stageH: Math.max(1, stageRect.height)
    };
  }

  function placeOverlay(el, slot, slotKey) {
    if (!el || !slot) return;
    const s = computeStageSize(stageEl, imgEl);
    if (!s) return;

    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200) * s.imgW);
    const centerY = Math.round(s.offsetTop  + ((slot.top_pct||50)/100)  * s.imgH + ((slot.height_pct||0)/200) * s.imgH);
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
    el.style.pointerEvents = 'none';
    el.style.whiteSpace = 'nowrap';
    el.style.overflow = 'hidden';
    el.style.boxSizing = 'border-box';
    el.style.padding = '0 6px';

    // font sizing
    const txt = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
    const chars = Math.max(1, txt.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile?1.05:1.0) : 1.0));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let fs = Math.floor(Math.min(heightCandidate, widthCap));
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fs = Math.max(8, Math.min(fs, maxAllowed));
    fs = Math.floor(fs * 1.08);
    el.style.fontSize = fs + 'px';
    el.style.lineHeight = '1';
    el.style.fontWeight = '700';

    let attempts = 0;
    while (el.scrollWidth > el.clientWidth && fs > 7 && attempts < 30) {
      fs = Math.max(7, Math.floor(fs * 0.92));
      el.style.fontSize = fs + 'px';
      attempts++;
    }
  }

  // place uploaded logo using artwork slot
  function placeLogo(logo, slot) {
    if (!logo) return;
    const s = computeStageSize(stageEl, imgEl);
    if (!s) return;

    if (slot && slot.width_pct) {
      const cx = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200) * s.imgW);
      const cy = Math.round(s.offsetTop  + ((slot.top_pct||50)/100)  * s.imgH + ((slot.height_pct||0)/200) * s.imgH);
      const wpx = Math.round(((slot.width_pct||10)/100) * s.imgW);
      const hpx = Math.round(((slot.height_pct||10)/100) * s.imgH);

      logo.style.display = 'block';
      logo.style.left = (cx - wpx/2) + 'px';
      logo.style.top  = (cy - hpx/2) + 'px';
      logo.style.width = wpx + 'px';
      logo.style.height = hpx + 'px';
      logo.style.transform = 'rotate(' + (slot.rotation || 0) + 'deg)';
    } else {
      // fallback small badge
      logo.style.display = 'block';
      logo.style.position = 'absolute';
      logo.style.left = '20%';
      logo.style.top = '30%';
      logo.style.width = '70px';
      logo.style.height = '70px';
      logo.style.transform = 'translate(37%,-30%)';
    }
  }

  function applyLayout() {
    if (!imgEl || !imgEl.complete) return;

    // apply prefill font & color if present
    const pfFont = (pf.prefill_font || pf.font || '') .toString().toLowerCase();
    const fontClass = fontClassMap[pfFont] || fontClassMap['bebas'];
    if (ovName) ovName.className = 'np-overlay ' + fontClass;
    if (ovNum)  ovNum.className  = 'np-overlay ' + fontClass;

    if (pf.prefill_color || pf.color) {
      try { var c = decodeURIComponent(pf.prefill_color || pf.color || ''); } catch(e){ var c = (pf.prefill_color || pf.color || ''); }
      if (c) { ovName.style.color = c; ovNum.style.color = c; }
    }

    // position overlays
    placeOverlay(ovName, layout.name, 'name');
    placeOverlay(ovNum, layout.number, 'number');

    // choose artwork slot from layoutSlots if present
    let artwork = null;
    try {
      artwork = layout['logo'] || layout['artwork'] || layout['team_logo'] || (Object.values(layout).find(s=> s && (s.mask || (s.slot_key && /logo|artwork|team/i.test(s.slot_key)))) || null);
    } catch(e) { artwork = null; }

    // set logo src if pf exists but server didn't set src attribute
    try {
      if (hiddenTeamLogo && hiddenTeamLogo.value && (!logoEl.src || logoEl.src === location.origin + '/')) {
        logoEl.src = hiddenTeamLogo.value;
        logoEl.style.display = 'block';
      } else if (pf.prefill_logo && (!logoEl.src || logoEl.src === location.origin + '/')) {
        try { logoEl.src = decodeURIComponent(pf.prefill_logo); logoEl.style.display = 'block'; } catch(e){ logoEl.src = pf.prefill_logo; logoEl.style.display = 'block'; }
        if (hiddenTeamLogo) hiddenTeamLogo.value = (pf.prefill_logo ? decodeURIComponent(pf.prefill_logo) : pf.prefill_logo);
      }
    } catch(e){ /* ignore */ }

    if (logoEl) placeLogo(logoEl, artwork);
  }

  (function addReliableRecalc() {
    try {
      if ('ResizeObserver' in window) {
        const ro = new ResizeObserver(() => {
          clearTimeout(window._team_layout_timer);
          window._team_layout_timer = setTimeout(() => applyLayout(), 80);
        });
        if (imgEl) ro.observe(imgEl);
        if (stageEl) ro.observe(stageEl);
      } else {
        window.addEventListener('resize', () => setTimeout(applyLayout, 120));
      }
      window.addEventListener('scroll', () => { clearTimeout(window._team_layout_timer); window._team_layout_timer = setTimeout(() => applyLayout(), 90); }, { passive:true });
      window.addEventListener('orientationchange', () => setTimeout(applyLayout, 180));
      document.addEventListener('visibilitychange', () => { if (!document.hidden) setTimeout(applyLayout, 120); });
      setTimeout(() => { if (imgEl && imgEl.complete) applyLayout(); }, 300);
      setTimeout(() => { if (imgEl && imgEl.complete) applyLayout(); }, 900);
    } catch (err) { console.warn('recalc helper failed', err); }
  })();

  // ---- preview rendering for a row (reusable) ----
  function renderRowPreview(row) {
    if (!row) return;
    const nameEl = row.querySelector('.player-name');
    const numEl  = row.querySelector('.player-number');
    const fontHidden = row.querySelector('.player-font');
    const colorHidden = row.querySelector('.player-color');

    const nm = (nameEl?.value || '').toUpperCase().slice(0,12);
    const nu = (numEl?.value || '').replace(/\D/g,'').slice(0,3);

    // apply per-row font if provided
    if (fontHidden?.value) {
      const fm = fontHidden.value.toLowerCase();
      const cls = fontClassMap[fm] || fontClassMap['bebas'];
      ovName.className = 'np-overlay ' + cls;
      ovNum.className  = 'np-overlay ' + cls;
    } else {
      // revert to designer prefill font if present
      const pfFontLocal = (pf.prefill_font || pf.font || '').toString().toLowerCase();
      const cls = fontClassMap[pfFontLocal] || fontClassMap['bebas'];
      ovName.className = 'np-overlay ' + cls;
      ovNum.className  = 'np-overlay ' + cls;
    }

    // apply per-row color if provided
    if (colorHidden?.value) {
      ovName.style.color = colorHidden.value;
      ovNum.style.color = colorHidden.value;
    }

    ovName.textContent = nm || 'NAME';

    if (window.hasNumberSlot) {
      ovNum.textContent = (nu || '09');
      ovNum.style.display = '';
    } else {
      // make sure overlay hidden
      if (ovNum) { ovNum.textContent = ''; ovNum.style.display = 'none'; }
    }

    list.querySelectorAll('.player-row').forEach(r => r.classList.remove('preview-active'));
    row.classList.add('preview-active');

    applyLayout();
  }

  function enforceLimits(input) {
    if (!input) return;
    if (input.classList.contains('player-number')) {
      input.addEventListener('input', (e) => { e.target.value = (e.target.value || '').replace(/\D/g,'').slice(0,3); });
    }
    if (input.classList.contains('player-name')) {
      input.addEventListener('input', (e) => { e.target.value = (e.target.value || '').toUpperCase().slice(0,12); });
    }
  }

  function createRow(vals = {}) {
    const node = tpl.content.cloneNode(true);
    list.appendChild(node);
    const rows = list.querySelectorAll('.player-row');
    const last = rows[rows.length - 1];
    const numEl = last.querySelector('.player-number');
    const nameEl = last.querySelector('.player-name');
      if (!window.hasNumberSlot && numEl) {
      try {
        numEl.style.display = 'none';
        numEl.value = '';
        last.classList.add('no-number');
      } catch(e) { console.warn('hide new row number failed', e); }
    }
    const sizeEl = last.querySelector('.player-size');
    const fontHidden = last.querySelector('.player-font');
    const colorHidden = last.querySelector('.player-color');

    if (vals.number) numEl.value = vals.number;
    if (vals.name) nameEl.value = vals.name;
    if (vals.size) sizeEl.value = vals.size;
    if (vals.font) fontHidden.value = vals.font;
    if (vals.color) colorHidden.value = vals.color;

    enforceLimits(numEl); enforceLimits(nameEl);

    last.querySelector('.btn-remove').addEventListener('click', ()=> {
      last.remove();
      if (!list.querySelector('.player-row')) { ovName.textContent = ''; ovNum.textContent = ''; applyLayout(); }
      else {
        const any = list.querySelector('.player-row');
        if (any) renderRowPreview(any);
      }
    });

    nameEl.addEventListener('focus', ()=> renderRowPreview(last));
    numEl.addEventListener('focus', ()=> renderRowPreview(last));
    nameEl.addEventListener('input', ()=> { if (last.classList.contains('preview-active')) renderRowPreview(last); });
    numEl.addEventListener('input', ()=> { if (last.classList.contains('preview-active')) renderRowPreview(last); });

    nameEl.focus();
    renderRowPreview(last);

    return last;
  }

  list.addEventListener('click', function(evt) {
    const btn = evt.target.closest('.btn-preview');
    if (!btn) return;
    const row = btn.closest('.player-row');
    if (!row) return;
    renderRowPreview(row);
  });

  addBtn.addEventListener('click', ()=> createRow());

  // initial rows from prefill (if provided)
  if ((pf.prefill_name && pf.prefill_name.length) || (pf.prefill_number && pf.prefill_number.length)) {
    createRow({
      name: (pf.prefill_name || pf.name || '').toString().toUpperCase().slice(0,12),
      number: (pf.prefill_number || pf.number || '').toString().replace(/\D/g,'').slice(0,3),
      font: pf.prefill_font || pf.font || '',
      color: (pf.prefill_color ? decodeURIComponent(pf.prefill_color) : pf.color) || ''
    });
  } else {
    createRow();
  }

  // ensure hiddenTeamLogo kept in sync
  try {
    if (hiddenTeamLogo && pf.prefill_logo) {
      try { hiddenTeamLogo.value = decodeURIComponent(pf.prefill_logo); } catch(e) { hiddenTeamLogo.value = pf.prefill_logo; }
    }
  } catch(e){}

  // final collect & submit
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const rows = list.querySelectorAll('.player-row');
    const players = [];
    rows.forEach(r => {
      const n = r.querySelector('.player-name')?.value || '';
      const num = r.querySelector('.player-number')?.value || '';
      const sz = (r.querySelector('.player-size')?.value || '').toString();
      const f  = r.querySelector('.player-font')?.value || '';
      const c  = r.querySelector('.player-color')?.value || '';
      if (!n && !num) return;

      let variantId = '';
      try {
        if (window.variantMap) {
          variantId = window.variantMap[sz] || window.variantMap[sz.toUpperCase()] || window.variantMap[sz.toLowerCase()] || '';
        }
      } catch(e) { variantId = ''; }

      players.push({
        name: n.toString().toUpperCase().slice(0,12),
        number: num.toString().replace(/\D/g,'').slice(0,3),
        size: sz,
        font: f,
        color: c,
        variant_id: variantId
      });
    });

    if (players.length === 0) { alert('Add at least one player.'); return; }

    const payload = { product_id: form.querySelector('input[name="product_id"]').value || null, players: players, team_logo_url: hiddenTeamLogo?.value || '' };
    try {
      const token = document.querySelector('input[name="_token"]')?.value || '';
      const resp = await fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=>null);
      if (!resp.ok) {
        alert((json && (json.message || json.error)) || 'Server error while adding team players.');
        return;
      }
      if (json.checkoutUrl || json.checkout_url) { window.location.href = json.checkoutUrl || json.checkout_url; return; }
      if (json.success) {
        alert('Team saved successfully.');
        if (json.team_id) window.location.href = '/team/' + json.team_id;
        return;
      }
      alert('Saved. Refresh to continue.');
    } catch(err) {
      console.error(err);
      alert('Network/server error. Check console.');
    }
  });

  // initial applyLayout after fonts and image ready
  document.fonts?.ready.then(()=> setTimeout(()=> { if (imgEl && imgEl.complete) applyLayout(); }, 120));
  if (imgEl) imgEl.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 120));

  /* ----------------- Mobile modal preview integration ----------------- */
  (function() {
    // create modal HTML and append to body (keeps blade tidy)
    const modalHtml = `
      <div id="player-preview-modal" class="np-modal">
        <div class="np-modal-backdrop"></div>
        <div class="np-modal-dialog">
          <button id="np-modal-close" class="np-modal-close" title="Close">&times;</button>
          <div id="np-modal-stage-wrap" style="width:100%; max-width:540px; margin:0 auto; text-align:center;"></div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modal = document.getElementById('player-preview-modal');
    const modalWrap = document.getElementById('np-modal-stage-wrap');
    const modalClose = document.getElementById('np-modal-close');

    function closeModal() {
      if (!modal) return;
      modal.style.display = 'none';
      modalWrap.innerHTML = '';
      document.body.style.overflow = '';
    }

    async function showPreviewForRow(row) {
      if (!modal || !modalWrap || !row) return;
      const stage = document.getElementById('player-stage');
      if (!stage) return;

      const clone = stage.cloneNode(true);
      clone.id = 'player-stage-modal-clone';

      // ensure cloned image reloads
      const modalImg = clone.querySelector('#player-base') || clone.querySelector('img');
      const src = modalImg ? (modalImg.getAttribute('src') || '') : '';
      if (modalImg && src) { modalImg.src = src; modalImg.style.maxWidth = '100%'; modalImg.style.height = 'auto'; }

      const nameVal = (row.querySelector('.player-name')?.value || '').toUpperCase().slice(0,12) || 'NAME';
      const numVal  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'').slice(0,3) || '09';
      const fontVal = (row.querySelector('.player-font')?.value || '') || (window.prefill?.prefill_font || '');
      const colorVal = (row.querySelector('.player-color')?.value || '') || (window.prefill?.prefill_color || '');

      const modalOvName = clone.querySelector('#overlay-name') || clone.querySelector('.np-overlay');
      const modalOvNum  = clone.querySelector('#overlay-number') || (clone.querySelectorAll('.np-overlay')[1] || null);

      if (modalOvName) modalOvName.textContent = nameVal;
      if (window.hasNumberSlot) {
        if (modalOvNum) { modalOvNum.textContent = numVal; modalOvNum.style.display = ''; }
      } else {
        if (modalOvNum) { modalOvNum.textContent = ''; modalOvNum.style.display = 'none'; }
      }

      try {
        const fm = (fontVal || '').toString().toLowerCase();
        const cls = fontClassMap[fm] || fontClassMap['bebas'];
        if (modalOvName) modalOvName.className = 'np-overlay ' + cls;
        if (modalOvNum) modalOvNum.className = 'np-overlay ' + cls;
      } catch(e){}

      try {
        if (colorVal) {
          let c = colorVal;
          try { c = decodeURIComponent(colorVal); } catch(e){ c = colorVal; }
          if (modalOvName) modalOvName.style.color = c;
          if (modalOvNum) modalOvNum.style.color = c;
        }
      } catch(e){}

      modalWrap.appendChild(clone);
      document.body.style.overflow = 'hidden';
      modal.style.display = 'flex';

      // wait for image/fonts then compute placement similar to applyLayout
      const waitImgs = new Promise((resolve) => {
        if (!modalImg) return resolve();
        if (modalImg.complete) return resolve();
        modalImg.addEventListener('load', resolve);
        modalImg.addEventListener('error', resolve);
        setTimeout(resolve, 350);
      });

      (document.fonts?.ready || Promise.resolve()).then(() => {
        waitImgs.then(() => {
          try {
            const modalStage = document.getElementById('player-stage-modal-clone');
            const modalImgEl = modalStage.querySelector('#player-base') || modalStage.querySelector('img');
            const stageRect = modalStage.getBoundingClientRect();
            const imgRect = modalImgEl.getBoundingClientRect();
            const s = {
              offsetLeft: Math.round(imgRect.left - stageRect.left),
              offsetTop: Math.round(imgRect.top - stageRect.top),
              imgW: Math.max(1, imgRect.width),
              imgH: Math.max(1, imgRect.height),
              stageW: Math.max(1, stageRect.width),
              stageH: Math.max(1, stageRect.height)
            };

            const layoutLocal = (typeof window.layoutSlots === 'object' && Object.keys(window.layoutSlots || {}).length) ? window.layoutSlots : layout;

            function modalPlace(el, slot, slotKey){
              if (!el || !slot) return;
              const centerX = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200) * s.imgW);
              const centerY = Math.round(s.offsetTop  + ((slot.top_pct||50)/100)  * s.imgH + ((slot.height_pct||0)/200) * s.imgH);
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
              el.style.pointerEvents = 'none';
              el.style.whiteSpace = 'nowrap';
              el.style.overflow = 'hidden';
              el.style.boxSizing = 'border-box';
              el.style.padding = '0 6px';

              const txt = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
              const chars = Math.max(1, txt.length);
              const isMobile = window.innerWidth <= 767;
              const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile?1.05:1.0) : 1.0));
              const avgCharRatio = 0.48;
              const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
              let fs = Math.floor(Math.min(heightCandidate, widthCap));
              const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
              fs = Math.max(8, Math.min(fs, maxAllowed));
              fs = Math.floor(fs * 1.08);
              el.style.fontSize = fs + 'px';
              el.style.lineHeight = '1';
              el.style.fontWeight = '700';

              let attempts = 0;
              while (el.scrollWidth > el.clientWidth && fs > 7 && attempts < 30) {
                fs = Math.max(7, Math.floor(fs * 0.92));
                el.style.fontSize = fs + 'px';
                attempts++;
              }
            }

            const modalNameEl = modalStage.querySelector('#overlay-name') || modalStage.querySelector('.np-overlay');
            const modalNumEl  = modalStage.querySelector('#overlay-number') || (modalStage.querySelectorAll('.np-overlay')[1] || null);

            modalPlace(modalNameEl, layoutLocal.name, 'name');
            modalPlace(modalNumEl, layoutLocal.number, 'number');

          } catch(err){ console.warn('modal placement failed', err); }
        });
      });
    }

    // delegate click on preview buttons (works for dynamic rows)
    document.addEventListener('click', function(e) {
      const btn = e.target.closest('.btn-preview');
      if (!btn) return;
      const row = btn.closest('.player-row');
      if (!row) return;

      // on desktop keep existing behavior; intercept only on mobile
      if (window.innerWidth > 767) return;

      e.stopPropagation();
      e.preventDefault();
      showPreviewForRow(row);
    }, true);

    // modal close handlers
    modalClose?.addEventListener('click', closeModal);
    modal?.addEventListener('click', function(e){ if (e.target === modal || e.target.classList.contains('np-modal-backdrop')) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal && modal.style.display === 'flex') closeModal(); });

  })();

});
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const saveBtn = document.getElementById('save-team-btn');
  const atcBtn  = document.getElementById('team-addtocart-btn');
  const previewInput = document.getElementById('team-preview-url');
  const stage = document.getElementById('player-stage'); // preview stage element
  const form = document.getElementById('team-form');

  const saveUrl = "{{ route('team.save') }}";
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
               document.querySelector('input[name="_token"]')?.value || '';

   if (!saveBtn) return;

  // Ensure Add-to-cart is hidden/disabled initially
  if (atcBtn) {
    atcBtn.disabled = true;
    atcBtn.classList.add('d-none');
  }

   async function makePreviewDataURL() {
  try {
    // small quick compressed dataURL fallback (used only when server-side upload not required)
    const { blob, canvas } = await makeCompressedBlobForTeam({ scale: 0.72, mime: 'image/png', quality: 0.9 });
    if (!blob) return null;
    // convert to dataURL
    return await new Promise(resolve => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(blob);
    });
  } catch (err) {
    console.warn('makePreviewDataURL error', err);
    return null;
  }
}

  async function uploadBaseArtwork() {
  // hide UI bits that shouldn't be in base if any (safe-guard)
  const overlays = Array.from(document.querySelectorAll('.np-overlay, #overlay-name, #overlay-number, #player-logo'));
  const prevStyles = overlays.map(el => el ? el.style.display || '' : '');
  overlays.forEach(el => { if (el) el.style.display = 'none'; });

  await new Promise(r => setTimeout(r, 80)); // allow reflow

  try {
    // prepare compressed blob (smaller target for base)
    const blob = await prepareTeamUploadBlob(600 * 1024); // ~600KB target
    if (!blob) throw new Error('Base blob creation failed');

    // restore overlays
    overlays.forEach((el, i) => { if (el) el.style.display = prevStyles[i] || ''; });

    // upload and return public URL
    const url = await uploadBlobViaTemp(blob, 'preview_base_' + Date.now() + '.jpg');
    return url;
  } catch (err) {
    // restore overlays on error too
    overlays.forEach((el, i) => { if (el) el.style.display = prevStyles[i] || ''; });
    console.warn('uploadBaseArtwork error', err);
    return null;
  }
}


/* ---------- compressed image helpers (TEAM page) ---------- */
async function makeCompressedBlobForTeam(opts = {}) {
  // opts: { scale, mime, quality }
  const scale = typeof opts.scale === 'number' ? opts.scale : 0.72;
  const mime  = opts.mime || 'image/jpeg';
  const quality = typeof opts.quality === 'number' ? opts.quality : 0.78;
  const node = document.getElementById('player-stage') || document.getElementById('np-stage') || document.body;
  const canvas = await html2canvas(node, { useCORS: true, backgroundColor: null, scale: (window.devicePixelRatio || 1) * scale });
  return new Promise(resolve => canvas.toBlob(blob => resolve({ blob, canvas }), mime, quality));
}

async function prepareTeamUploadBlob(maxBytes = 900000) {
  // try several compress levels until under size
  const attempts = [
    { scale: 0.9, quality: 0.85 },
    { scale: 0.72, quality: 0.78 },
    { scale: 0.6, quality: 0.72 },
    { scale: 0.45, quality: 0.64 }
  ];
  for (const cfg of attempts) {
    try {
      const { blob } = await makeCompressedBlobForTeam(cfg);
      if (!blob) continue;
      if (blob.size <= maxBytes) return blob;
    } catch(e) { /* ignore */ }
  }
  // fallback: return last attempt even if bigger
  const last = await makeCompressedBlobForTeam(attempts[attempts.length - 1]);
  return last.blob;
}

/* upload helper used here */
async function uploadBlobViaTemp(blob, filename = null) {
  if (!blob) return null;
  const fd = new FormData();
  const fname = filename || ('team_preview_' + Date.now() + '.jpg');
  fd.append('file', blob, fname);
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value || '';
  const res = await fetch('{{ route("designer.upload_temp") }}', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: (token ? { 'X-CSRF-TOKEN': token } : {})
  });
  if (!res.ok) {
    const txt = await res.text().catch(()=>null);
    throw new Error('Upload failed: ' + (txt || res.status));
  }
  const json = await res.json().catch(()=>null);
  if (json && json.url) return json.url;
  throw new Error('Upload returned no url');
}


  async function saveDesign() {
  if (!saveBtn) return;
  saveBtn.disabled = true;
  const originalText = saveBtn.textContent || 'Save';
  saveBtn.textContent = 'Saving...';

  try {
    // collect players similarly to existing logic
    const players = Array.from(document.querySelectorAll('#players-list .player-row')).map(r => {
      return {
        id: r.dataset.playerId ?? null,
        name: (r.querySelector('.player-name')?.value || '').toString().toUpperCase().slice(0, 64),
        number: (r.querySelector('.player-number')?.value || '').toString().replace(/\D/g,'').slice(0,3),
        size: r.querySelector('.player-size')?.value || '',
        font: r.querySelector('.player-font')?.value || '',
        color: r.querySelector('.player-color')?.value || ''
      };
    }).filter(p => (p.name && p.name.trim() !== '') || (p.number && p.number.trim() !== ''));

    if (players.length === 0) {
      alert('Please add at least one player before saving.');
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
      return;
    }

    // 1) Create compressed blob (try to keep under ~1.2MB)
    let previewUrl = '';
    try {
      const blob = await prepareTeamUploadBlob(1200 * 1024); // 1.2MB target
      if (blob) {
        previewUrl = await uploadBlobViaTemp(blob, 'team_preview_' + Date.now() + '.jpg');
      }
    } catch (err) {
      console.warn('blob upload failed, falling back to dataURL', err);
      // fallback: try dataURL route (smaller chance of 413 but better than nothing)
      try {
        const dataUrl = await makePreviewDataURL();
        if (dataUrl) {
          // upload dataURL as blob
          const respBlob = await (await fetch(dataUrl)).blob();
          previewUrl = await uploadBlobViaTemp(respBlob, 'team_preview_fallback_' + Date.now() + '.png');
        }
      } catch(e) { console.warn('fallback upload failed', e); }
    }

    // 2) Optional: upload base artwork separately (small thumbnail)
    let basePreviewUrl = null;
    try {
      basePreviewUrl = await uploadBaseArtwork();
    } catch(e) { console.warn('base upload failed', e); basePreviewUrl = null; }

    const payload = {
      product_id: form.querySelector('input[name="product_id"]')?.value || null,
      players: players,
      preview_src: previewUrl || null,
      preview_base: basePreviewUrl || null,
      team_logo_url: document.getElementById('team-prefill-logo')?.value || ''
    };

    // send to server
    const token = document.querySelector('input[name="_token"]')?.value || '';
    const res = await fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token || csrf
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });

    const json = await res.json().catch(()=>null);
    if (!res.ok || !(json && (json.success === true || json.status === 'ok' || json.team_id))) {
      const msg = (json && (json.message || json.error)) || ('HTTP ' + res.status);
      alert('Save failed: ' + msg);
      saveBtn.disabled = false;
      saveBtn.textContent = originalText;
      return;
    }

    // success: set hidden inputs, enable add-to-cart
    const previewInput = document.getElementById('team-preview-url');
    if (previewInput && (json.preview_url || previewUrl)) previewInput.value = json.preview_url || previewUrl;

    const teamIdHidden = document.getElementById('team-id-hidden');
    if (teamIdHidden && json.team_id) teamIdHidden.value = json.team_id;

    if (atcBtn) {
      atcBtn.disabled = false;
      atcBtn.classList.remove('d-none');
      if (atcBtn.getAttribute('data-label')) atcBtn.textContent = atcBtn.getAttribute('data-label');
    }

    try { saveBtn.remove(); } catch(e) { saveBtn.style.display = 'none'; }

    alert(json.message || 'Design saved ✔ You can now click Add To Cart.');
    return json;

  } catch (err) {
    console.error('SaveDesign fatal', err);
    alert('Save failed — see console for details.');
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save';
    return;
  }
}

  saveBtn.addEventListener('click', function(e){
    e.preventDefault();
    saveDesign();
  });
});
</script>


@endsection
