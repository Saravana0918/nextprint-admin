@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-start gap-4">
    <!-- LEFT: form area -->
    <div class="flex-grow-1">
      <h3>Add Team Players for: {{ $product->name ?? 'Product' }}</h3>

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
          <button type="submit" class="btn btn-success">Save Team</button>
          <a href="{{ url()->previous() }}" class="btn btn-secondary">Back</a>
        </div>
      </form>
    </div>

    <!-- RIGHT: product preview (thumbnail) -->
    <div style="width:520px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center" style="position:relative;">
          <!-- Stage container (position:relative) -->
          <div id="player-stage" style="position:relative; display:inline-block;">
            <img id="player-base" src="{{ $product->image_url ?? asset('images/placeholder.png') }}"
                 alt="{{ $product->name }}" class="img-fluid" style="width:100%; height:100%; object-fit:contain; display:block;">
            <!-- Overlays -->
            <div id="overlay-name" style="
                position:absolute;
                top:38px;
                left:50%;
                transform:translateX(-50%);
                font-weight:800;
                font-family: 'Bebas Neue', sans-serif;
                color:#D4AF37;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                font-size:18px;
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
            ">NAME</div>

            <div id="overlay-number" style="
                position:absolute;
                bottom:58px;
                left:50%;
                transform:translateX(-50%);
                font-weight:900;
                font-family: 'Bebas Neue', sans-serif;
                color:#D4AF37;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                font-size:28px;
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
            ">NUMBER</div>
          </div>

          <!-- product meta below -->
          <h5 class="card-title mt-3">{{ $product->name }}</h5>
          <p class="text-muted">Price: ₹ {{ number_format($product->min_price ?? 0, 2) }}</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- template for player row -->
<template id="player-row-template">
  <div class="card mb-2 p-2 player-row">
    <div class="d-flex gap-2 align-items-start">
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00" />
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME" />
      <select name="players[][size]" class="form-select w-25">
        <option value="">Size</option>
        <option value="XS">XS</option>
        <option value="S">S</option>
        <option value="M">M</option>
        <option value="L">L</option>
        <option value="XL">XL</option>
      </select>
      <button type="button" class="btn btn-danger btn-remove">Remove</button>
      <button type="button" class="btn btn-outline-primary btn-preview ml-2">Preview</button>
    </div>
  </div>
</template>

<style>
/* overlay base: keep reasonably small by default; JS will re-calc */
#player-stage { position: relative; display:inline-block; }
#player-base { width:100%; height:100%; object-fit:contain; display:block; }

/* overlay defaults (smaller than before) */
#overlay-name {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  color: #D4AF37;
  text-shadow: 0 3px 6px rgba(0,0,0,0.6);
  font-weight: 800;
  pointer-events: none;
  white-space: nowrap;
  z-index: 30;
  font-family: 'Bebas Neue', Arial, sans-serif;
  font-size: 16px;   /* smaller default */
  top: 8%;
}

#overlay-number {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  color: #D4AF37;
  text-shadow: 0 3px 6px rgba(0,0,0,0.6);
  font-weight: 900;
  pointer-events: none;
  white-space: nowrap;
  z-index: 30;
  font-family: 'Bebas Neue', Arial, sans-serif;
  font-size: 26px;   /* smaller default */
  top: 62%;
}

/* visual highlight on the active row */
.player-row.preview-active {
  box-shadow: 0 0 0 3px rgba(20,120,220,0.08);
  border-color: rgba(20,120,220,0.12);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const list = document.getElementById('players-list');
  const template = document.getElementById('player-row-template');
  const addBtn = document.getElementById('btn-add-row');

  const stage = document.getElementById('player-stage');
  const img = document.getElementById('player-base');
  const ovName = document.getElementById('overlay-name');
  const ovNum  = document.getElementById('overlay-number');

  // Tweak these slot values to control how much vertical space name/number get.
  // We reduced height_pct so text will be sized smaller by default.
  const nameSlot = { top_pct: 6,  height_pct: 8,  width_pct: 85 };   // name sits higher, smaller height
  const numSlot  = { top_pct: 58, height_pct: 12, width_pct: 60 };   // number lower, less vertical space

  function computeStageRect() {
    // bounding rect for the stage area (used to compute pixel box for overlays)
    return stage.getBoundingClientRect();
  }

  function fitTextToBox(el, boxW, boxH, text, options = {}) {
    // apply text
    el.textContent = text || (el.id === 'overlay-name' ? 'NAME' : 'NUMBER');

    // start font-size based on box height and optional factor
    const heightFactor = (options.heightFactor !== undefined) ? options.heightFactor : 0.8;
    let fs = Math.max(6, Math.floor(boxH * heightFactor));

    // limit maximum font relative to stage width (prevents huge fonts on big screens)
    const stageRect = computeStageRect();
    const maxAllowed = Math.max(12, Math.floor(stageRect.width * 0.12)); // tweak 0.10-0.14 for different results
    fs = Math.min(fs, maxAllowed);

    el.style.fontSize = fs + 'px';

    // shrink loop until it fits horizontally
    let attempts = 0;
    while (el.scrollWidth > boxW && fs > 6 && attempts < 80) {
      fs = Math.max(6, Math.floor(fs * 0.9));
      el.style.fontSize = fs + 'px';
      attempts++;
    }

    // ensure it doesn't overflow vertically
    if (fs > boxH) {
      fs = Math.floor(boxH * 0.95);
      el.style.fontSize = fs + 'px';
    }
  }

  function placeOverlay(el, slot, text, opts) {
    const rect = computeStageRect();
    const w = Math.max(8, Math.round((slot.width_pct/100) * rect.width));
    const h = Math.max(8, Math.round((slot.height_pct/100) * rect.height));
    const topPx = Math.round((slot.top_pct/100) * rect.height);

    // position top relative to stage container
    el.style.top = topPx + 'px';
    el.style.left = '50%';
    el.style.transform = 'translateX(-50%)';

    fitTextToBox(el, w, h, text, opts || {});
  }

  function refreshPreview(nameText, numText) {
    const name = (nameText || '').toString().toUpperCase();
    const num  = (numText || '').toString().replace(/\D/g,'');

    // We pass smaller heightFactor so text stays smaller than before.
    placeOverlay(ovName, nameSlot, name || 'NAME', { heightFactor: 0.65 });
    placeOverlay(ovNum, numSlot, num || 'NUMBER',  { heightFactor: 0.72 });
  }

  // Expose for buttons to call
  window.setPlayerPreview = function(name, number) {
    ovName.dataset.value = (name || '').toUpperCase();
    ovNum.dataset.value  = (number || '').toString().replace(/\D/g,'');
    refreshPreview(ovName.dataset.value, ovNum.dataset.value);
  };

  // when image loads or window resizes, reflow overlays
  function onStageChange() {
    const n = ovName.dataset.value || ovName.textContent;
    const m = ovNum.dataset.value  || ovNum.textContent;
    refreshPreview(n, m);
  }

  if (img.complete) onStageChange();
  img.addEventListener('load', onStageChange);
  window.addEventListener('resize', onStageChange);

  // add a new row
  function addRow() {
    const node = template.content.cloneNode(true);
    list.appendChild(node);
    wireRowEvents();
  }

  // wire events for existing + newly added rows
  function wireRowEvents() {
    // Remove buttons
    list.querySelectorAll('.btn-remove').forEach(btn=>{
      if (!btn.dataset.wired) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', e => {
          const row = e.target.closest('.player-row');
          if (row) row.remove();
        });
      }
    });

    // Preview buttons
    list.querySelectorAll('.btn-preview').forEach(btn=>{
      if (!btn.dataset.wired) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', e => {
          const row = e.target.closest('.player-row');
          if (!row) return;
          // mark active
          list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
          row.classList.add('preview-active');

          const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
          const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
          window.setPlayerPreview(name, num);
        });
      }
    });

    // Focus & live update handlers
    list.querySelectorAll('.player-name, .player-number').forEach(inp=>{
      if (!inp.dataset.wired) {
        inp.dataset.wired = '1';
        inp.addEventListener('focus', (e) => {
          const row = e.target.closest('.player-row');
          if (!row) return;
          list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
          row.classList.add('preview-active');

          const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
          const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
          window.setPlayerPreview(name, num);
        });

        inp.addEventListener('input', (e) => {
          const row = e.target.closest('.player-row');
          if (!row) return;
          if (row.classList.contains('preview-active')) {
            const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
            const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
            window.setPlayerPreview(name, num);
          }
        });
      }
    });
  }

  // init handlers
  addBtn.addEventListener('click', addRow);
  // initial row
  addRow();
});
</script>

@endsection
