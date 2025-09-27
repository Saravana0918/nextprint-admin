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
      <!-- number: maxlength=3, inputmode numeric -->
      <input name="players[][number]" class="form-control w-25 player-number" placeholder="00"
             maxlength="3" inputmode="numeric" pattern="\d*" />
      <!-- name: maxlength=12 -->
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME"
             maxlength="12" />
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
  const form = document.getElementById('team-form');

  const stage = document.getElementById('player-stage');
  const img = document.getElementById('player-base');
  const ovName = document.getElementById('overlay-name');
  const ovNum  = document.getElementById('overlay-number');

  // slot values
  const nameSlot = { top_pct: 18,  height_pct: 8,  width_pct: 85 };
  const numSlot  = { top_pct: 54, height_pct: 12, width_pct: 60 };

  function computeStageRect() {
    return stage.getBoundingClientRect();
  }

  function fitTextToBox(el, boxW, boxH, text, options = {}) {
    el.textContent = text || (el.id === 'overlay-name' ? 'NAME' : 'NUMBER');
    const heightFactor = (options.heightFactor !== undefined) ? options.heightFactor : 0.8;
    let fs = Math.max(6, Math.floor(boxH * heightFactor));
    const stageRect = computeStageRect();
    const maxAllowed = Math.max(12, Math.floor(stageRect.width * 0.12));
    fs = Math.min(fs, maxAllowed);
    el.style.fontSize = fs + 'px';

    let attempts = 0;
    while (el.scrollWidth > boxW && fs > 6 && attempts < 80) {
      fs = Math.max(6, Math.floor(fs * 0.9));
      el.style.fontSize = fs + 'px';
      attempts++;
    }
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
    el.style.top = topPx + 'px';
    el.style.left = '50%';
    el.style.transform = 'translateX(-50%)';
    fitTextToBox(el, w, h, text, opts || {});
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

  // add row
  function addRow() {
    const node = template.content.cloneNode(true);
    list.appendChild(node);
    wireRowEvents();
  }

  function enforceInputLimits(input) {
    if (!input) return;
    // name: enforce uppercase + maxlength 12
    if (input.classList.contains('player-name')) {
      input.addEventListener('input', (e) => {
        // uppercase and cut to 12 chars
        const v = (e.target.value || '').toString().toUpperCase().slice(0, 12);
        if (e.target.value !== v) e.target.value = v;
      });
      // prevent paste longer than 12
      input.addEventListener('paste', (ev) => {
        ev.preventDefault();
        const pasted = (ev.clipboardData.getData('text') || '').toUpperCase().slice(0,12);
        document.execCommand('insertText', false, pasted);
      });
    }

    // number: allow digits only, maxlength 3
    if (input.classList.contains('player-number')) {
      input.addEventListener('input', (e) => {
        let v = (e.target.value || '').replace(/\D/g,'').slice(0,3);
        if (e.target.value !== v) e.target.value = v;
      });
      input.addEventListener('paste', (ev) => {
        ev.preventDefault();
        const pasted = (ev.clipboardData.getData('text') || '').replace(/\D/g,'').slice(0,3);
        document.execCommand('insertText', false, pasted);
      });
    }
  }

  // wire events for rows
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
          list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
          row.classList.add('preview-active');

          const name = (row.querySelector('.player-name')?.value || '').toUpperCase().slice(0,12);
          const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'').slice(0,3);
          window.setPlayerPreview(name, num);
        });
      }
    });

    // inputs: focus & live update + enforce limits
    list.querySelectorAll('.player-name, .player-number').forEach(inp=>{
      if (!inp.dataset.wiredInput) {
        inp.dataset.wiredInput = '1';
        enforceInputLimits(inp);

        inp.addEventListener('focus', (e) => {
          const row = e.target.closest('.player-row');
          if (!row) return;
          list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
          row.classList.add('preview-active');

          const name = (row.querySelector('.player-name')?.value || '').toUpperCase().slice(0,12);
          const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'').slice(0,3);
          window.setPlayerPreview(name, num);
        });

        inp.addEventListener('input', (e) => {
          const row = e.target.closest('.player-row');
          if (!row) return;
          if (row.classList.contains('preview-active')) {
            const name = (row.querySelector('.player-name')?.value || '').toUpperCase().slice(0,12);
            const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'').slice(0,3);
            window.setPlayerPreview(name, num);
          }
        });
      }
    });
  }

  // initial add
  addBtn.addEventListener('click', addRow);
  addRow();

  // FINAL form submit validation: ensure each player row respects limits
  form.addEventListener('submit', function(evt) {
    const rows = list.querySelectorAll('.player-row');
    const errors = [];
    rows.forEach((row, idx) => {
      const nameEl = row.querySelector('.player-name');
      const numEl  = row.querySelector('.player-number');
      const name = (nameEl?.value || '').trim();
      const num  = (numEl?.value || '').trim();

      if (!name) {
        errors.push(`Row ${idx+1}: Name is required (max 12 chars).`);
      } else if (name.length > 12) {
        errors.push(`Row ${idx+1}: Name must be 12 characters or fewer.`);
      }

      if (!num) {
        errors.push(`Row ${idx+1}: Number is required (1–3 digits).`);
      } else if (!/^\d{1,3}$/.test(num)) {
        errors.push(`Row ${idx+1}: Number must be 1 to 3 digits.`);
      }
    });

    if (errors.length) {
      evt.preventDefault();
      alert('Please fix these issues:\n\n' + errors.join('\n'));
      return false;
    }

    // otherwise submit normally
    return true;
  });

});
</script>

@endsection
