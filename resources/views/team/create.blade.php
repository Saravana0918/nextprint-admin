@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-start gap-4 main-flex">
    <!-- RIGHT (on desktop) but shown FIRST on mobile): product preview (thumbnail) -->
    <div class="preview-col" style="width:520px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center" style="position:relative;">
          <!-- Stage container (position:relative) -->
          <div id="player-stage" style="position:relative; display:inline-block;">
            <img id="player-base" src="{{ $product->image_url ?? asset('images/placeholder.png') }}"
                 alt="{{ $product->name }}" class="img-fluid"
                 style="object-fit:contain; display:block;">

            <!-- Overlays (will be positioned by JS) -->
            <div id="overlay-name" style="
                position:absolute;
                left:50%;
                transform:translateX(-50%);
                font-weight:800;
                font-family: 'Oswald', sans-serif;
                color:#ffffff;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
                font-size:28px;
            ">NAME</div>

            <div id="overlay-number" style="
                position:absolute;
                left:50%;
                transform:translateX(-50%);
                font-weight:900;
                font-family: 'Oswald', sans-serif;
                color:#ffffff;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
                font-size:48px;
            ">NUMBER</div>
          </div>

          <!-- product meta below -->
          <h5 class="card-title mt-3">{{ $product->name }}</h5>
          <p class="text-muted">Price: ₹ {{ number_format($product->min_price ?? 0, 2) }}</p>
        </div>
      </div>
    </div>

    <!-- LEFT: form area (will appear below preview on mobile) -->
    <div class="flex-grow-1 form-col">
      <h3 class="mb-3">Add Team Players for: {{ $product->name ?? 'Product' }}</h3>

      @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @endif

      <form method="post" action="{{ route('team.store') }}" id="team-form">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id ?? '' }}">

        <div class="mb-3">
          <button type="button" id="btn-add-row" class="btn btn-primary">ADD NEW</button>
        </div>

        <div id="players-list" class="mb-4">
          <!-- JS will insert rows here -->
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-success btn-block" style="width:100%;">Save Team</button>
          <a href="{{ url()->previous() }}" class="btn btn-secondary btn-block" style="width:100%; margin-top:8px;">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- expose server-provided prefill to JS --}}
<script>
  window.prefill = {!! json_encode($prefill ?? ['name'=>'','number'=>'','font'=>'','color'=>'','size'=>'']) !!};
</script>

<!-- template for player row -->
<template id="player-row-template">
  <div class="card mb-2 p-2 player-row">
    <div class="d-flex gap-2 align-items-start row-controls">
      <!-- number: maxlength=3, inputmode numeric -->
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00"
             maxlength="3" inputmode="numeric" pattern="\d*" />
      <!-- name: maxlength=12 -->
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME"
             maxlength="12" />
      <select name="players[][size]" class="form-select w-25 player-size">
        <option value="">Size</option>
        <option value="XS">XS</option>
        <option value="S">S</option>
        <option value="M">M</option>
        <option value="L">L</option>
        <option value="XL">XL</option>
      </select>

      <!-- hidden per-row font/color (will be filled by JS) -->
      <input type="hidden" name="players[][font]" class="player-font">
      <input type="hidden" name="players[][color]" class="player-color">

      <button type="button" class="btn btn-danger btn-remove">Remove</button>
      <button type="button" class="btn btn-outline-primary btn-preview ml-2">Preview</button>
    </div>
  </div>
</template>

<style>
/* Desktop default makes two columns (preview right, form left) */
.main-flex { align-items: flex-start; }

/* small visual for active row */
.player-row.preview-active {
  box-shadow: 0 0 0 3px rgba(20,120,220,0.08);
  border-color: rgba(20,120,220,0.12);
}

/* === Mobile / tablet responsive tweaks === */
@media (max-width: 991px) {
  /* stack columns vertically and make preview appear first */
  .main-flex { flex-direction: column !important; }
  .preview-col { order: 1; width: 100% !important; margin-bottom: 1rem; }
  .form-col { order: 2; width: 100% !important; }

  /* make preview card body padding comfortable */
  .preview-col .card-body { padding: 0.75rem 1rem; }

  /* limit stage width on mobile so overlay sizing works predictably */
  #player-stage { width: 320px !important; height: 420px !important; display:block !important; margin:0 auto 1rem !important; }
  #player-base  { width: 320px !important; height: 420px !important; object-fit:contain !important; }

  .row-controls {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
    align-items: stretch !important;
  }


  /* make inputs full width and stack nicely */
  .player-row .player-number,
  .player-row .player-name,
  .player-row .player-size {
    width: 100% !important;
    display: block !important;
    margin: 0 !important;
    font-size: 18px !important;        /* larger readable text */
    height: 48px !important;           /* taller tap target */
    padding: 10px 12px !important;     /* comfortable padding */
    border-radius: 6px !important;
    box-sizing: border-box !important;
  }

  .player-row .player-size {
    -webkit-appearance: none;
    appearance: none;
    background-position: right 12px center;
    background-repeat: no-repeat;
  }

  /* move buttons below inputs (stack) */
   .player-row .btn-remove,
  .player-row .btn-preview {
    width: 100% !important;
    display: block !important;
    margin-top: 6px !important;
    font-size: 16px !important;
    padding: 10px 12px !important;
  }

    .player-row.preview-active {
    padding: 0.8rem !important;
    border: 1px solid rgba(20,120,220,0.12) !important;
    border-radius: 8px !important;
  }

  /* submit buttons full width */
   .mt-3 .btn { display:block; width:100%; font-size:16px; padding:12px 14px; }

  /* overlay text sizing for mobile */
   #overlay-name { font-size: 22px !important; text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important; }
  #overlay-number { font-size: 38px !important; text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important; }

  html, body { overflow-x: hidden; }
}

/* Slight smaller adjustments for very small phones */
@media (max-width: 420px) {
  #player-stage { width: 280px !important; height: 380px !important; }
  #player-base  { width: 280px !important; height: 380px !important; }
  .player-row .player-number,
  .player-row .player-name,
  .player-row .player-size { font-size: 16px !important; height:44px !important; padding:8px 10px !important; }
  .player-row .btn-remove,
  .player-row .btn-preview { font-size:15px !important; padding:10px 12px !important; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const list = document.getElementById('players-list');
  const template = document.getElementById('player-row-template');
  const addBtn = document.getElementById('btn-add-row');
  const form = document.getElementById('team-form');

  const stage = document.getElementById('player-stage');
  const img = document.getElementById('player-base');
  const ovName = document.getElementById('overlay-name');
  const ovNum  = document.getElementById('overlay-number');

  // Slot positions (percent of stage height)
  const nameSlot = { top_pct: 18,  height_pct: 8,  width_pct: 85 };
  const numSlot  = { top_pct: 54, height_pct: 12, width_pct: 70 };

  function computeStageRect() { return stage.getBoundingClientRect(); }

  function fitTextToBox(el, boxW, boxH, options = {}) {
    const heightFactor = options.heightFactor || 0.8;
    let fs = Math.max(8, Math.floor(boxH * heightFactor));
    const stageRect = computeStageRect();
    const maxAllowed = Math.max(12, Math.floor(stageRect.width * 0.18));
    fs = Math.min(fs, maxAllowed);
    el.style.fontSize = fs + 'px';
    let attempts = 0;
    while (el.scrollWidth > boxW && fs > 6 && attempts < 80) {
      fs = Math.max(6, Math.floor(fs * 0.92));
      el.style.fontSize = fs + 'px';
      attempts++;
    }
    if (parseInt(el.style.fontSize) > boxH) {
      el.style.fontSize = Math.floor(boxH * 0.95) + 'px';
    }
  }

  function placeOverlay(el, slot, text, opts) {
    const rect = computeStageRect();
    const w = Math.max(8, Math.round((slot.width_pct/100) * rect.width));
    const h = Math.max(8, Math.round((slot.height_pct/100) * rect.height));
    const topPx = Math.round((slot.top_pct/100) * rect.height);
    el.style.top = topPx + 'px';
    el.style.left = '50%';
    el.style.transform = 'translateX(-50%)';
    el.textContent = text || '';
    fitTextToBox(el, w, h, opts);
  }

  function refreshPreview(nameText, numText) {
    const name = (nameText || '').toString().toUpperCase();
    const num  = (numText || '').toString().replace(/\D/g,'');
    placeOverlay(ovName, nameSlot, name || 'NAME', { heightFactor: 0.65 });
    placeOverlay(ovNum, numSlot, num || 'NUMBER',  { heightFactor: 0.60 });
  }

  window.setPlayerPreview = function(name, number) {
    ovName.dataset.value = (name || '').toUpperCase();
    ovNum.dataset.value  = (number || '').toString().replace(/\D/g,'');
    refreshPreview(ovName.dataset.value, ovNum.dataset.value);
  };

  function onStageChange() {
    const n = ovName.dataset.value || ovName.textContent;
    const m = ovNum.dataset.value  || ovNum.textContent;
    refreshPreview(n, m);
  }

  if (img.complete) onStageChange();
  img.addEventListener('load', onStageChange);
  window.addEventListener('resize', onStageChange);

  // apply prefill styles (font/color) from designer
  (function applyPrefillStyles(){
    try {
      const pf = window.prefill || {};
      const fontMap = {
        'oswald': "'Oswald', sans-serif",
        'bebas': "'Bebas Neue', sans-serif",
        'anton': "'Anton', sans-serif",
      };
      if (!ovName || !ovNum) return;
      if (pf.color) {
        let c = pf.color;
        try { c = decodeURIComponent(c); } catch(e){}
        ovName.style.color = c;
        ovNum.style.color  = c;
        window._team_prefill_color = c;
      } else {
        window._team_prefill_color = '';
      }
      if (pf.font) {
        const key = pf.font.toString().toLowerCase();
        const family = fontMap[key] || pf.font;
        ovName.style.fontFamily = family;
        ovNum.style.fontFamily  = family;
        window._team_prefill_font = pf.font;
      } else {
        window._team_prefill_font = '';
      }
      if (typeof window.setPlayerPreview === 'function') {
        window.setPlayerPreview((pf.name||'').toString().toUpperCase(), (pf.number||'').toString().replace(/\D/g,''));
      }
    } catch(err) {
      console.error('applyPrefillStyles', err);
    }
  })();

  // Row creation + wiring
  function createRow(values = {}) {
    const node = template.content.cloneNode(true);
    list.appendChild(node);

    const rows = list.querySelectorAll('.player-row');
    const last = rows[rows.length - 1];
    const numEl = last.querySelector('.player-number');
    const nameEl = last.querySelector('.player-name');
    const sizeEl = last.querySelector('.player-size');

    // hidden inputs in template
    const fontInput = last.querySelector('.player-font');
    const colorInput = last.querySelector('.player-color');

    if (values.number) numEl.value = values.number.toString().slice(0,3);
    if (values.name) nameEl.value = values.name.toString().toUpperCase().slice(0,12);
    if (values.size) sizeEl.value = values.size;

    // set font/color hidden inputs: prefer row values, else fallback to prefill
    const preFont = (values.font || window._team_prefill_font || '').toString();
    const preColor = (values.color || window._team_prefill_color || '').toString();
    if (fontInput) fontInput.value = preFont;
    if (colorInput) colorInput.value = preColor;

    enforceInputLimits(numEl);
    enforceInputLimits(nameEl);

    // remove button
    last.querySelectorAll('.btn-remove').forEach(btn=>{
      btn.addEventListener('click', () => {
        last.remove();
        const any = list.querySelector('.player-row.preview-active');
        if (!any) {
          ovName.dataset.value = '';
          ovNum.dataset.value = '';
          onStageChange();
        }
      });
    });

    // preview button
    last.querySelectorAll('.btn-preview').forEach(btn=>{
      btn.addEventListener('click', () => {
        list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
        last.classList.add('preview-active');
        const name = (nameEl.value || '').toUpperCase().slice(0,12);
        const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);

        // apply row font/color to overlays
        const rowFont = fontInput?.value || window._team_prefill_font || '';
        const rowColor = colorInput?.value || window._team_prefill_color || '';

        const fontMap = {
          'oswald': "'Oswald', sans-serif",
          'bebas': "'Bebas Neue', sans-serif",
          'anton': "'Anton', sans-serif",
        };
        if (rowFont) {
          const fam = fontMap[rowFont.toString().toLowerCase()] || rowFont;
          ovName.style.fontFamily = fam;
          ovNum.style.fontFamily  = fam;
        }
        if (rowColor) {
          ovName.style.color = rowColor;
          ovNum.style.color  = rowColor;
        }

        window.setPlayerPreview(name, num);
      });
    });

    // focus -> set active + immediate preview
    nameEl.addEventListener('focus', () => {
      list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
      last.classList.add('preview-active');
      const name = (nameEl.value || '').toUpperCase().slice(0,12);
      const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
      const rowFont = fontInput?.value || window._team_prefill_font || '';
      const rowColor = colorInput?.value || window._team_prefill_color || '';
      const fontMap = {
        'oswald': "'Oswald', sans-serif",
        'bebas': "'Bebas Neue', sans-serif",
        'anton': "'Anton', sans-serif",
      };
      if (rowFont) {
        const fam = fontMap[rowFont.toString().toLowerCase()] || rowFont;
        ovName.style.fontFamily = fam; ovNum.style.fontFamily  = fam;
      }
      if (rowColor) { ovName.style.color = rowColor; ovNum.style.color  = rowColor; }

      window.setPlayerPreview(name, num);
    });
    numEl.addEventListener('focus', () => {
      list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
      last.classList.add('preview-active');
      const name = (nameEl.value || '').toUpperCase().slice(0,12);
      const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
      const rowFont = fontInput?.value || window._team_prefill_font || '';
      const rowColor = colorInput?.value || window._team_prefill_color || '';
      const fontMap = {
        'oswald': "'Oswald', sans-serif",
        'bebas': "'Bebas Neue', sans-serif",
        'anton': "'Anton', sans-serif",
      };
      if (rowFont) {
        const fam = fontMap[rowFont.toString().toLowerCase()] || rowFont;
        ovName.style.fontFamily = fam; ovNum.style.fontFamily  = fam;
      }
      if (rowColor) { ovName.style.color = rowColor; ovNum.style.color  = rowColor; }

      window.setPlayerPreview(name, num);
    });

    // live update while typing (if active row)
    nameEl.addEventListener('input', () => {
      if (last.classList.contains('preview-active')) {
        const name = (nameEl.value || '').toUpperCase().slice(0,12);
        const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
        window.setPlayerPreview(name, num);
      }
    });
    numEl.addEventListener('input', () => {
      if (last.classList.contains('preview-active')) {
        const name = (nameEl.value || '').toUpperCase().slice(0,12);
        const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
        window.setPlayerPreview(name, num);
      }
    });

    return last;
  }

  function enforceInputLimits(input) {
    if (!input) return;
    if (input.classList.contains('player-name')) {
      input.addEventListener('input', (e) => {
        const v = (e.target.value || '').toUpperCase().slice(0, 12);
        if (e.target.value !== v) e.target.value = v;
      });
      input.addEventListener('paste', (ev) => {
        ev.preventDefault();
        const pasted = (ev.clipboardData.getData('text') || '').toUpperCase().slice(0,12);
        document.execCommand('insertText', false, pasted);
      });
    }
    if (input.classList.contains('player-number')) {
      input.addEventListener('input', (e) => {
        const v = (e.target.value || '').replace(/\D/g,'').slice(0,3);
        if (e.target.value !== v) e.target.value = v;
      });
      input.addEventListener('paste', (ev) => {
        ev.preventDefault();
        const pasted = (ev.clipboardData.getData('text') || '').replace(/\D/g,'').slice(0,3);
        document.execCommand('insertText', false, pasted);
      });
    }
  }

  addBtn.addEventListener('click', () => createRow());

  // create initial row(s) from prefill
  const pf = window.prefill || {};
  if ((pf.name && pf.name.length) || (pf.number && pf.number.length)) {
    const r = createRow({
      name: pf.name ? pf.name.toString().toUpperCase().slice(0,12) : '',
      number: pf.number ? pf.number.toString().replace(/\D/g,'').slice(0,3) : '',
      size: pf.size || '',
      font: pf.font || '',
      color: (pf.color ? decodeURIComponent(pf.color) : '') || '',
    });
    r.classList.add('preview-active');
    if (typeof window.setPlayerPreview === 'function') {
      window.setPlayerPreview(pf.name ? pf.name.toString().toUpperCase().slice(0,12) : '', pf.number ? pf.number.toString().slice(0,3) : '');
    }
  } else {
    createRow();
  }

  // final validation before submit
  form.addEventListener('submit', function(evt) {
    const rows = list.querySelectorAll('.player-row');
    if (rows.length === 0) {
      evt.preventDefault(); alert('Please add at least one player.'); return false;
    }
    const errors = [];
    rows.forEach((row, idx) => {
      const nameEl = row.querySelector('.player-name');
      const numEl  = row.querySelector('.player-number');
      const name = (nameEl?.value || '').trim();
      const num  = (numEl?.value || '').trim();
      if (!name) errors.push(`Row ${idx+1}: Name is required.`);
      else if (name.length > 12) errors.push(`Row ${idx+1}: Name must be 12 chars or fewer.`);
      if (!num) errors.push(`Row ${idx+1}: Number is required.`);
      else if (!/^\d{1,3}$/.test(num)) errors.push(`Row ${idx+1}: Number must be 1 to 3 digits.`);
    });
    if (errors.length) {
      evt.preventDefault();
      alert('Please fix these issues:\n\n' + errors.join('\n'));
      return false;
    }
    return true;
  });

  /* ===== Mobile stage sizing helper ===== */
  function adjustStageForViewport() {
    try {
      const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
      const stageEl = document.getElementById('player-stage');
      const imgEl = document.getElementById('player-base');

      if (!stageEl || !imgEl) return;

      if (vw <= 991) {
        const w = (vw <= 420) ? 280 : 320;
        const h = (vw <= 420) ? 380 : 420;
        stageEl.style.width = w + 'px';
        stageEl.style.height = h + 'px';
        imgEl.style.width = w + 'px';
        imgEl.style.height = h + 'px';
      } else {
        stageEl.style.width = '';
        stageEl.style.height = '';
        imgEl.style.width = '';
        imgEl.style.height = '';
      }
      onStageChange();
    } catch(e) {
      console.error('adjustStageForViewport error', e);
    }
  }

  adjustStageForViewport();
  window.addEventListener('resize', function() {
    adjustStageForViewport();
    setTimeout(onStageChange, 120);
  });
  window.addEventListener('orientationchange', function() {
    setTimeout(adjustStageForViewport, 150);
  });

});
</script>

@endsection
