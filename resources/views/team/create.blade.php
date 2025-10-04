{{-- resources/views/team/create.blade.php --}}
@extends('layouts.app')
@section('hide_header', '1')

@section('content')
@php
  $img = $product->image_url ?? ($product->preview_src ?? asset('images/placeholder.png'));
  $prefill = $prefill ?? [];
  $layoutSlots = $layoutSlots ?? null;
@endphp

<!-- copy same stage CSS as designer so measurements match exactly -->
<style>
  /* fonts (ensure same families as designer) */
  .font-bebas{font-family:'Bebas Neue', Impact, 'Arial Black', sans-serif;}
  .font-anton{font-family:'Anton', Impact, 'Arial Black', sans-serif;}
  .font-oswald{font-family:'Oswald', Arial, sans-serif;}
  .font-impact{font-family:Impact, 'Arial Black', sans-serif;}

  /* match designer stage exactly */
  .np-stage { position: relative; width: 100%; max-width: 534px; margin: 0 auto; background:#fff; border-radius:8px; padding:8px; min-height: 320px; box-sizing: border-box; overflow: visible; }
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

  /* responsive rules - same breakpoints as designer */
  @media (max-width: 767px) {
    /* mobile look from designer - keep stage width fixed so overlay mapping predictable */
    .np-stage { width: 320px !important; height: 420px !important; }
    .np-stage img { width: 320px !important; height: 420px !important; object-fit: contain !important; }
  }

  /* page specific layout */
  .main-flex { align-items: flex-start; }
  @media (max-width: 991px) {
    .main-flex { flex-direction: column !important; }
    .preview-col { order: 1; width: 100% !important; margin-bottom: 1rem; }
    .form-col { order: 2; width: 100% !important; }
  }
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
</script>

<!-- row template (same as you had) -->
<template id="player-row-template">
  <div class="card mb-2 p-2 player-row">
    <div class="d-flex gap-2 align-items-start row-controls">
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00" maxlength="3" inputmode="numeric" />
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME" maxlength="12" />
      <select name="players[][size]" class="form-select w-25 player-size">
        <option value="">Size</option><option value="XS">XS</option><option value="S">S</option><option value="M">M</option><option value="L">L</option><option value="XL">XL</option>
      </select>
      <input type="hidden" name="players[][font]" class="player-font">
      <input type="hidden" name="players[][color]" class="player-color">
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

    // font sizing logic (same as designer)
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

  function applyLayout() {
    if (!imgEl || !imgEl.complete) return;

    // prefill text
    if (pf.prefill_name || pf.name) ovName.textContent = (pf.prefill_name || pf.name || '').toString().toUpperCase();
    if (pf.prefill_number || pf.number) ovNum.textContent = (pf.prefill_number || pf.number || '').toString().replace(/\D/g,'').slice(0,3);

    // prefill font & color
    if (pf.prefill_font || pf.font) {
      const map = {bebas: "Bebas Neue, sans-serif", oswald: "Oswald, sans-serif", anton: "Anton, sans-serif", impact: "Impact, Arial"};
      const key = (pf.prefill_font || pf.font).toString().toLowerCase();
      const fam = map[key] || (pf.prefill_font || pf.font) || '';
      if (fam) { ovName.style.fontFamily = fam; ovNum.style.fontFamily = fam; }
    }
    if (pf.prefill_color || pf.color) {
      try { var c = decodeURIComponent(pf.prefill_color || pf.color || ''); } catch(e){ var c = (pf.prefill_color || pf.color || ''); }
      if (c) { ovName.style.color = c; ovNum.style.color = c; }
    }

    placeOverlay(ovName, layout.name, 'name');
    placeOverlay(ovNum, layout.number, 'number');
  }

  // debug helper: open console and call teamOverlayDebug()
  window.teamOverlayDebug = function() {
    console.log('layoutSlots', layout);
    console.log('stageRect', stageEl?.getBoundingClientRect(), 'imgRect', imgEl?.getBoundingClientRect());
    console.log('compute', computeStageSize(stageEl, imgEl));
  };

  // trigger layout at right times
  if (imgEl) {
    if (imgEl.complete) setTimeout(applyLayout, 80);
    else imgEl.addEventListener('load', ()=> setTimeout(applyLayout, 80));
  }
  window.addEventListener('resize', ()=> setTimeout(applyLayout, 120));
  document.fonts?.ready.then(()=> setTimeout(applyLayout, 120));

  /* ===== rows & preview wiring (unchanged) ===== */
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
    const fontHidden = last.querySelector('.player-font');
    const colorHidden = last.querySelector('.player-color');

    if (vals.number) numEl.value = vals.number;
    if (vals.name) nameEl.value = vals.name;
    if (vals.font) fontHidden.value = vals.font;
    if (vals.color) colorHidden.value = vals.color;

    enforceLimits(numEl); enforceLimits(nameEl);

    last.querySelector('.btn-remove').addEventListener('click', ()=> {
      last.remove();
      if (!list.querySelector('.player-row')) { ovName.textContent = ''; ovNum.textContent = ''; applyLayout(); }
    });

    last.querySelector('.btn-preview').addEventListener('click', ()=> {
      list.querySelectorAll('.player-row').forEach(r => r.classList.remove('preview-active'));
      last.classList.add('preview-active');
      const nm = (nameEl.value || '').toUpperCase().slice(0,12);
      const nu = (numEl.value || '').replace(/\D/g,'').slice(0,3);
      if (fontHidden.value) {
        const fm = fontHidden.value.toLowerCase();
        const familyMap = {bebas: "Bebas Neue, sans-serif", oswald: "Oswald, sans-serif", anton:"Anton, sans-serif"};
        const fam = familyMap[fm] || fontHidden.value;
        ovName.style.fontFamily = fam; ovNum.style.fontFamily = fam;
      }
      if (colorHidden.value) { ovName.style.color = colorHidden.value; ovNum.style.color = colorHidden.value; }
      ovName.textContent = nm || 'NAME';
      ovNum.textContent = nu || '09';
      applyLayout();
    });

    nameEl.addEventListener('focus', ()=> { last.classList.add('preview-active'); ovName.textContent = (nameEl.value||'').toUpperCase().slice(0,12); ovNum.textContent = (numEl.value||'').replace(/\D/g,'').slice(0,3); applyLayout(); });
    numEl.addEventListener('focus', ()=> { last.classList.add('preview-active'); ovName.textContent = (nameEl.value||'').toUpperCase().slice(0,12); ovNum.textContent = (numEl.value||'').replace(/\D/g,'').slice(0,3); applyLayout(); });

    nameEl.addEventListener('input', ()=> { if (last.classList.contains('preview-active')) { ovName.textContent = (nameEl.value||'').toUpperCase().slice(0,12); applyLayout(); } });
    numEl.addEventListener('input', ()=> { if (last.classList.contains('preview-active')) { ovNum.textContent = (numEl.value||'').replace(/\D/g,'').slice(0,3); applyLayout(); } });

    return last;
  }

  addBtn.addEventListener('click', ()=> createRow());

  if ((pf.prefill_name && pf.prefill_name.length) || (pf.prefill_number && pf.prefill_number.length)) {
    createRow({
      name: (pf.prefill_name || pf.name || '').toString().toUpperCase().slice(0,12),
      number: (pf.prefill_number || pf.number || '').toString().replace(/\D/g,'').slice(0,3),
      font: pf.prefill_font || pf.font || '',
      color: (pf.prefill_color ? decodeURIComponent(pf.prefill_color) : pf.color) || ''
    });
    const first = list.querySelector('.player-row');
    if (first) first.classList.add('preview-active');
  } else {
    createRow();
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const rows = list.querySelectorAll('.player-row');
    const players = [];
    rows.forEach(r => {
      const n = r.querySelector('.player-name')?.value || '';
      const num = r.querySelector('.player-number')?.value || '';
      const sz = r.querySelector('.player-size')?.value || '';
      const f  = r.querySelector('.player-font')?.value || '';
      const c  = r.querySelector('.player-color')?.value || '';
      if (!n && !num) return;
      players.push({ name: n.toString().toUpperCase().slice(0,12), number: num.toString().replace(/\D/g,'').slice(0,3), size: sz, font: f, color: c });
    });

    if (players.length === 0) { alert('Add at least one player.'); return; }

    const payload = { product_id: form.querySelector('input[name="product_id"]').value || null, players: players };
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
      if (json.checkoutUrl || json.checkout_url) {
        window.location.href = json.checkoutUrl || json.checkout_url;
        return;
      }
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

});
</script>

@endsection
