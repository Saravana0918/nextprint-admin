{{-- resources/views/team/create.blade.php --}}
@extends('layouts.app')
@section('hide_header', '1')

@section('content')
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
  $prefill = $prefill ?? [];
  $layoutSlots = $layoutSlots ?? null;

  // Build a server-side variant map (UPPERCASE keys -> numeric shopify variant id)
  $variantMap = [];
  if (!empty($product)) {
      if (! $product->relationLoaded('variants')) {
          try { $product->load('variants'); } catch (\Throwable $e) { /* ignore */ }
      }
      $variants = $product->variants ?? \App\Models\ProductVariant::where('product_id', $product->id)->get();
      foreach ($variants as $v) {
          $k = trim((string)($v->option_value ?? $v->option_name ?? ''));
          if ($k === '') continue;
          // ensure numeric id if stored as gid
          $rawId = (string)($v->shopify_variant_id ?? $v->variant_id ?? '');
          if (preg_match('/(\d+)$/', $rawId, $m)) $rawId = $m[1];
          $variantMap[strtoupper($k)] = $rawId;
      }
  }
@endphp

<style>
/* (Use same CSS as you had; trimmed here for brevity) */
.font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
.np-stage{ position: relative; width:100%; max-width:534px; margin:0 auto; background:#fff; border-radius:8px; padding:8px; min-height:320px; box-sizing:border-box; overflow:visible;}
.np-overlay{ position:absolute; color:#D4AF37; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; text-align:center; text-shadow:0 3px 10px rgba(0,0,0,0.65); pointer-events:none; white-space:nowrap; line-height:1; transform-origin:center center; z-index:9999;}
/* ... keep your existing styles ... */
</style>

<div class="container py-4">
  <div class="d-flex align-items-start gap-4 main-flex">
    <div class="preview-col" style="width:534px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center" style="position:relative;">
          <div id="player-stage" class="np-stage" aria-hidden="false">
            <img id="player-base" src="{{ $img }}" alt="{{ $product->name ?? 'Product' }}" crossorigin="anonymous" onerror="this.onerror=null;this.src='{{ asset('images/placeholder.png') }}'">
            <div id="overlay-name" class="np-overlay font-bebas" aria-hidden="true" style="z-index:30; font-weight:800;">NAME</div>
            <div id="overlay-number" class="np-overlay font-bebas" aria-hidden="true" style="z-index:35; font-weight:900;">NUMBER</div>
          </div>
          <h5 class="card-title mt-3">{{ $product->name ?? 'Product' }}</h5>
        </div>
      </div>
    </div>

    <div class="flex-grow-1 form-col">
      <h3 class="mb-3">Add Team Players for: {{ $product->name ?? 'Product' }}</h3>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="post" action="{{ route('team.store') }}" id="team-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id ?? '' }}">
        <input type="hidden" name="shopify_product_id" value="{{ $product->shopify_product_id ?? '' }}">

        <div class="mb-3">
          <button type="button" id="btn-add-row" class="btn btn-primary">ADD NEW</button>
          <button type="submit" class="btn btn-success">Add To Cart</button>
          <a href="{{ url()->previous() }}" class="btn btn-secondary">Back</a>
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

  // server-built variant map (UPPERCASE keys) -> numeric id
  window.variantMap = {!! json_encode($variantMap, JSON_UNESCAPED_SLASHES|JSON_NUMERIC_CHECK) !!} || {};
  console.info('team.variantMap:', window.variantMap);

  window.shopfrontUrl = "{{ env('SHOPIFY_STORE_FRONT_URL', 'https://nextprint.in') }}";
</script>

<template id="player-row-template">
  <div class="card mb-2 p-2 player-row">
    <div class="d-flex gap-2 align-items-start row-controls">
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00" maxlength="3" inputmode="numeric" />
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME" maxlength="12" />
      <select name="players[][size]" class="form-select w-25 player-size">
        <option value="">Size</option>
        <option value="XS">XS</option><option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option>
        <option value="2XL">2XL</option><option value="3XL">3XL</option>
      </select>

      <!-- hidden fields per-row -->
      <input type="hidden" name="players[][font]" class="player-font" />
      <input type="hidden" name="players[][color]" class="player-color" />
      <input type="hidden" name="players[][variant_id]" class="player-variant" />

      <button type="button" class="btn btn-danger btn-remove">Remove</button>
      <button type="button" class="btn btn-outline-primary btn-preview">Preview</button>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const list = document.getElementById('players-list');
  const tpl = document.getElementById('player-row-template');
  const addBtn = document.getElementById('btn-add-row');
  const form = document.getElementById('team-form');

  const stageEl = document.getElementById('player-stage');
  const imgEl = document.getElementById('player-base');
  const ovName = document.getElementById('overlay-name');
  const ovNum  = document.getElementById('overlay-number');

  const pf = window.prefill || {};
  const layout = (typeof window.layoutSlots === 'object' && Object.keys(window.layoutSlots || {}).length)
                 ? window.layoutSlots
                 : {
                    name: { left_pct:50, top_pct:25, width_pct:85, height_pct:8, rotation:0 },
                    number: { left_pct:50, top_pct:54, width_pct:70, height_pct:12, rotation:0 }
                 };

  function computeStageSize() {
    if (!stageEl || !imgEl) return null;
    const stageRect = stageEl.getBoundingClientRect();
    const imgRect = imgEl.getBoundingClientRect();
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
    const s = computeStageSize();
    if (!s) return;

    const centerX = Math.round(s.offsetLeft + ((slot.left_pct||50)/100) * s.imgW + ((slot.width_pct||0)/200) * s.imgW);
    const centerY = Math.round(s.offsetTop  + ((slot.top_pct||50)/100)  * s.imgH + ((slot.height_pct||0)/200) * s.imgH);
    const areaWpx = Math.max(8, Math.round(((slot.width_pct||10)/100) * s.imgW));
    const areaHpx = Math.max(8, Math.round(((slot.height_pct||10)/100) * s.imgH));

    Object.assign(el.style, {
      position: 'absolute',
      left: centerX + 'px',
      top: centerY + 'px',
      width: areaWpx + 'px',
      height: areaHpx + 'px',
      transform: 'translate(-50%,-50%) rotate(' + ((slot.rotation||0)) + 'deg)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      pointerEvents: 'none',
      whiteSpace: 'nowrap',
      overflow: 'hidden',
      boxSizing: 'border-box',
      padding: '0 6px'
    });

    // simple font sizing (same approach as designer)
    const txt = (el.textContent || '').toString().trim() || (slotKey === 'number' ? '09' : 'NAME');
    const chars = Math.max(1, txt.length);
    const isMobile = window.innerWidth <= 767;
    const heightCandidate = Math.floor(areaHpx * (slotKey === 'number' ? (isMobile?1.05:1.0) : 1.0));
    const avgCharRatio = 0.48;
    const widthCap = Math.floor((areaWpx * 0.95) / (chars * avgCharRatio));
    let fs = Math.floor(Math.min(heightCandidate, widthCap));
    const maxAllowed = Math.max(14, Math.floor(s.stageW * (isMobile ? 0.45 : 0.32)));
    fs = Math.max(8, Math.min(fs, maxAllowed));
    el.style.fontSize = Math.floor(fs * 1.08) + 'px';
    el.style.lineHeight = '1';
    el.style.fontWeight = '700';

    let attempts = 0;
    while (el.scrollWidth > el.clientWidth && attempts < 30) {
      const cur = parseInt(el.style.fontSize, 10) || fs;
      const next = Math.max(7, Math.floor(cur * 0.92));
      el.style.fontSize = next + 'px';
      attempts++;
    }
  }

  function applyLayout() {
    if (!imgEl || !imgEl.complete) return;
    placeOverlay(ovName, layout.name, 'name');
    placeOverlay(ovNum, layout.number, 'number');
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
    } catch (err) { console.warn('recalc helper failed', err); }
  })();

  function renderRowPreview(row) {
    if (!row) return;
    const nameEl = row.querySelector('.player-name');
    const numEl  = row.querySelector('.player-number');
    const fontHidden = row.querySelector('.player-font');
    const colorHidden = row.querySelector('.player-color');

    const nm = (nameEl?.value || '').toUpperCase().slice(0,12);
    const nu = (numEl?.value || '').replace(/\D/g,'').slice(0,3);

    if (fontHidden?.value) {
      const familyMap = {bebas: "Bebas Neue, sans-serif", oswald: "Oswald, sans-serif", anton:"Anton, sans-serif", impact:"Impact, Arial"};
      const fam = familyMap[fontHidden.value.toLowerCase()] || fontHidden.value;
      ovName.style.fontFamily = fam;
      ovNum.style.fontFamily = fam;
    }
    if (colorHidden?.value) {
      ovName.style.color = colorHidden.value;
      ovNum.style.color = colorHidden.value;
    }

    ovName.textContent = nm || 'NAME';
    ovNum.textContent = nu || '09';
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

  function updateRowVariant(row) {
    const sizeEl = row.querySelector('.player-size');
    const variantInput = row.querySelector('.player-variant');
    if (!sizeEl || !variantInput) return;
    const sizeVal = (sizeEl.value || '').toString();
    let v = '';
    try {
      if (window.variantMap && sizeVal) {
        v = window.variantMap[sizeVal] || window.variantMap[sizeVal.toUpperCase()] || window.variantMap[sizeVal.toLowerCase()] || '';
      }
    } catch(e) { v = ''; }
    variantInput.value = v || '';
    // debug
    //console.debug('updated row variant', sizeVal, v);
  }

  function wireRowEvents(row) {
    const numEl = row.querySelector('.player-number');
    const nameEl = row.querySelector('.player-name');
    const sizeEl = row.querySelector('.player-size');
    const removeBtn = row.querySelector('.btn-remove');
    const previewBtn = row.querySelector('.btn-preview');

    enforceLimits(numEl); enforceLimits(nameEl);

    if (sizeEl) {
      sizeEl.addEventListener('change', ()=> {
        updateRowVariant(row);
        if (row.classList.contains('preview-active')) renderRowPreview(row);
      });
      // update initial
      try { sizeEl.dispatchEvent(new Event('change')); } catch(e) {}
    }

    if (removeBtn) removeBtn.addEventListener('click', ()=> {
      row.remove();
      if (!list.querySelector('.player-row')) { ovName.textContent=''; ovNum.textContent=''; applyLayout(); }
      else {
        const any = list.querySelector('.player-row');
        if (any) renderRowPreview(any);
      }
    });

    if (previewBtn) previewBtn.addEventListener('click', ()=> renderRowPreview(row));

    // focus listeners to preview
    nameEl.addEventListener('focus', ()=> renderRowPreview(row));
    numEl.addEventListener('focus', ()=> renderRowPreview(row));
    nameEl.addEventListener('input', ()=> { if (row.classList.contains('preview-active')) renderRowPreview(row); });
    numEl.addEventListener('input', ()=> { if (row.classList.contains('preview-active')) renderRowPreview(row); });
  }

  function createRow(vals = {}) {
    const node = tpl.content.cloneNode(true);
    list.appendChild(node);
    const rows = list.querySelectorAll('.player-row');
    const last = rows[rows.length - 1];

    // populate initial values if provided
    const numEl = last.querySelector('.player-number');
    const nameEl = last.querySelector('.player-name');
    const sizeEl = last.querySelector('.player-size');
    const fontHidden = last.querySelector('.player-font');
    const colorHidden = last.querySelector('.player-color');
    const variantHidden = last.querySelector('.player-variant');

    if (vals.number) numEl.value = vals.number;
    if (vals.name) nameEl.value = vals.name;
    if (vals.size) sizeEl.value = vals.size;
    if (vals.font) fontHidden.value = vals.font;
    if (vals.color) colorHidden.value = vals.color;
    if (vals.variant_id) variantHidden.value = vals.variant_id;

    wireRowEvents(last);

    // autofocus name field and preview
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

  // prefill rows if provided
  if ((pf.prefill_name && pf.prefill_name.length) || (pf.prefill_number && pf.prefill_number.length)) {
    createRow({
      name: (pf.prefill_name || pf.name || '').toString().toUpperCase().slice(0,12),
      number: (pf.prefill_number || pf.number || '').toString().replace(/\D/g,'').slice(0,3),
      font: pf.prefill_font || pf.font || '',
      color: (pf.prefill_color ? decodeURIComponent(pf.prefill_color) : pf.color) || '',
      size: pf.prefill_size || ''
    });
  } else {
    createRow();
  }

  // submit handler -> we use JSON AJAX like your flow
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const rows = list.querySelectorAll('.player-row');
    const players = [];

    rows.forEach(r => {
      const name = (r.querySelector('.player-name')?.value || '').toString().toUpperCase().slice(0,12);
      const number = (r.querySelector('.player-number')?.value || '').toString().replace(/\D/g,'').slice(0,3);
      const size = (r.querySelector('.player-size')?.value || '') .toString();
      const font = (r.querySelector('.player-font')?.value || '');
      const color = (r.querySelector('.player-color')?.value || '');
      const variant = (r.querySelector('.player-variant')?.value || '').toString();

      if (!name && !number) return; // skip empty row

      players.push({
        name: name,
        number: number,
        size: size,
        font: font,
        color: color,
        variant_id: variant
      });
    });

    if (players.length === 0) { alert('Add at least one player.'); return; }

    // ensure all players have variant_id (to avoid Shopify errors)
    const missing = players.filter(p => !p.variant_id);
    if (missing.length > 0) {
      console.warn('Missing variant ids for rows:', missing);
      alert('One or more players do not have a variant selected for their size. Please choose a valid size for each player (or update variant mapping).');
      return;
    }

    const payload = {
      product_id: form.querySelector('input[name="product_id"]').value || null,
      shopify_product_id: form.querySelector('input[name="shopify_product_id"]').value || null,
      players: players
    };

    console.info('Players payload before add:', players);

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
        console.error('Server returned error', resp.status, json);
        alert((json && (json.message || json.error)) || 'Server error while adding team players.');
        return;
      }

      // if controller returned checkout URL -> redirect
      if (json && (json.checkoutUrl || json.checkout_url)) {
        window.location.href = json.checkoutUrl || json.checkout_url;
        return;
      }

      if (json && json.success) {
        alert('Team saved successfully.');
        if (json.team_id) window.location.href = '/team/' + json.team_id;
        else location.reload();
        return;
      }

      alert('Saved. Refresh to continue.');
    } catch(err) {
      console.error(err);
      alert('Network/server error. Check console.');
    }
  });

  // layout/preview initialisation
  document.fonts?.ready.then(()=> setTimeout(()=> { if (imgEl && imgEl.complete) applyLayout(); }, 120));
  if (imgEl) imgEl.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 120));
});
</script>

@endsection
