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
    <div style="width:450px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center" style="position:relative;">
          <!-- Stage container (position:relative) -->
          <div id="player-stage">
            <img id="player-base" src="{{ $product->image_url ?? asset('images/placeholder.png') }}"
                 alt="{{ $product->name }}" class="img-fluid" style="width:100%; height:100%; object-fit:contain; display:block;">
            <!-- Overlays -->
            <div id="overlay-name" class="overlay-text">NAME</div>
            <div id="overlay-number" class="overlay-text">NUMBER</div>
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
/* overlay base styles */
.overlay-text {
  position: absolute;
  left: 48%;
  transform: translateX(-50%);
  color: #D4AF37;
  text-shadow: 0 3px 6px rgba(0,0,0,0.6);
  font-weight: 800;
  pointer-events: none;
  white-space: nowrap;
  line-height: 1;
  z-index: 30;
  font-family: 'Bebas Neue', Arial, sans-serif;
}

/* sensible defaults (these will be recalculated by JS) */
#overlay-name { font-size: 20px; top: 15%; }
#overlay-number { font-size: 36px; top: 60%; }

/* ensure stage doesn't clip overlays */
#player-stage {
  display: inline-block;
  width: 320px;    
  height: 320px;  
  position: relative;
  overflow: visible;
}

#player-base {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

/* small visual for active row */
.player-row.preview-active { box-shadow: 0 0 0 3px rgba(20,120,220,0.08); border-color: rgba(20,120,220,0.12); }
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

  // Layout "slots" as percentage of stage (tweak these to match your artwork)
  const nameSlot = { top_pct: 5,  height_pct: 12, width_pct: 85 };   // moved a little up, wider
  const numSlot  = { top_pct: 62, height_pct: 20, width_pct: 60 };   // slightly larger number box

  function computeStageRect() {
    return stage.getBoundingClientRect();
  }

  function fitTextToBox(el, boxW, boxH, text, options = {}) {
    // Put text then compute font-size
    el.textContent = text || (el.id === 'overlay-name' ? 'NAME' : 'NUMBER');

    // Choose starting font size relative to box height
    const start = Math.floor(boxH * (options.heightFactor || 0.8));
    let fs = Math.max(6, start);
    el.style.fontSize = fs + 'px';

    // Reduce until fits width or reaches minimum
    let attempts = 0;
    while (el.scrollWidth > boxW && fs > 6 && attempts < 60) {
      fs = Math.max(6, Math.floor(fs * 0.92));
      el.style.fontSize = fs + 'px';
      attempts++;
    }

    // Ensure it doesn't exceed box height
    if (fs > boxH) {
      fs = Math.floor(boxH);
      el.style.fontSize = fs + 'px';
    }
  }

  function placeOverlay(el, slot, text, opts) {
    const rect = computeStageRect();
    const w = Math.max(8, Math.round((slot.width_pct/100) * rect.width));
    const h = Math.max(8, Math.round((slot.height_pct/100) * rect.height));
    const topPx = Math.round((slot.top_pct/100) * rect.height);

    // set top in px relative to stage
    el.style.top = topPx + 'px';

    // center horizontally
    el.style.left = '50%';
    el.style.transform = 'translateX(-50%)';

    fitTextToBox(el, w, h, text, opts || {});
  }

  function refreshPreview(nameText, numText) {
    // sanitize
    const name = (nameText || '').toUpperCase();
    const num  = (numText || '').toString().replace(/\D/g,'');

    // apply for name and number with different height factors
    placeOverlay(ovName, nameSlot, name || 'NAME', { heightFactor: 0.9 });
    placeOverlay(ovNum, numSlot, num || 'NUMBER',  { heightFactor: 0.95 });
  }

  // Expose helper to use from row preview buttons
  window.setPlayerPreview = function(name, number) {
    ovName.dataset.value = (name || '').toUpperCase();
    ovNum.dataset.value  = (number || '').toString().replace(/\D/g,'');
    refreshPreview(ovName.dataset.value, ovNum.dataset.value);
  };

  // react to image load & window resize to reflow overlays
  function onStageChange() {
    // re-render using current stored dataset values if present
    const n = ovName.dataset.value || ovName.textContent;
    const m = ovNum.dataset.value  || ovNum.textContent;
    refreshPreview(n, m);
  }

  if (img.complete) onStageChange();
  img.addEventListener('load', onStageChange);
  window.addEventListener('resize', onStageChange);

  // ADD row
  function addRow() {
    const node = template.content.cloneNode(true);
    list.appendChild(node);
    wireRowEvents();
  }

  // Wire up events for rows (remove, preview, live sync)
  function wireRowEvents() {
    // .btn-remove
    list.querySelectorAll('.btn-remove').forEach(btn=>{
      if (!btn.dataset.wired) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', e => {
          const row = e.target.closest('.player-row');
          if (row) row.remove();
        });
      }
    });

    // .btn-preview
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
          // set preview
          window.setPlayerPreview(name, num);
        });
      }
    });

    // inputs: focus -> make that row active + immediate preview
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

        // live update only if that row is active
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

  // init
  addBtn.addEventListener('click', addRow);
  // start with one row
  addRow();
});
</script>

@endsection
