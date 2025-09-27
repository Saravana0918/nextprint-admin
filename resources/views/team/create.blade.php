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
          <div id="player-stage" style="position:relative; display:inline-block; margin: 0 auto;">
            <img id="player-base" src="{{ $product->image_url ?? asset('images/placeholder.png') }}"
                 alt="{{ $product->name }}" class="img-fluid"
                 style="width:100%; height:100%; object-fit:contain; display:block;">

            <!-- Overlays (will be positioned by JS) -->
            <div id="overlay-name" style="
                position:absolute;
                left:50%;
                transform:translateX(-50%);
                font-weight:800;
                font-family: 'Bebas Neue', 'Arial Black', sans-serif;
                color:#D4AF37;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
                font-size:16px;
            ">NAME</div>

            <div id="overlay-number" style="
                position:absolute;
                left:50%;
                transform:translateX(-50%);
                font-weight:900;
                font-family: 'Bebas Neue', 'Arial Black', sans-serif;
                color:#D4AF37;
                text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                pointer-events:none;
                white-space:nowrap;
                z-index:30;
                font-size:28px;
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

{{-- expose server-provided prefill to JS --}}
<script>
  window.prefill = {!! json_encode($prefill ?? ['name'=>'','number'=>'','font'=>'','color'=>'','size'=>'']) !!};
</script>

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
      <select name="players[][size]" class="form-select w-25 player-size">
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
  /* small visual for active row */
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
    const maxAllowed = Math.max(10, Math.floor(stageRect.width * 0.12)); // upper cap
    fs = Math.min(fs, maxAllowed);
    el.style.fontSize = fs + 'px';

    // shrink until fits horizontally
    let attempts = 0;
    while (el.scrollWidth > boxW && fs > 6 && attempts < 60) {
      fs = Math.max(6, Math.floor(fs * 0.9));
      el.style.fontSize = fs + 'px';
      attempts++;
    }
    // ensure not taller than box
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

  // expose global helper so row code can call preview easily
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

  /* ------------------ Row creation + wiring ------------------ */

  function createRow(values = {}) {
    const node = template.content.cloneNode(true);
    list.appendChild(node);

    // get last appended row
    const rows = list.querySelectorAll('.player-row');
    const last = rows[rows.length - 1];
    const numEl = last.querySelector('.player-number');
    const nameEl = last.querySelector('.player-name');
    const sizeEl = last.querySelector('.player-size');

    // set values if provided
    if (values.number) numEl.value = values.number.toString().slice(0,3);
    if (values.name) nameEl.value = values.name.toString().toUpperCase().slice(0,12);
    if (values.size) sizeEl.value = values.size;

    // wire behaviour for this row's inputs/buttons
    enforceInputLimits(numEl);
    enforceInputLimits(nameEl);

    // remove button
    last.querySelectorAll('.btn-remove').forEach(btn=>{
      btn.addEventListener('click', () => {
        last.remove();
        // if removed row was active, clear preview or revert to first row
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
        window.setPlayerPreview(name, num);
      });
    });

    // focus -> set active + immediate preview
    nameEl.addEventListener('focus', () => {
      list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
      last.classList.add('preview-active');
      const name = (nameEl.value || '').toUpperCase().slice(0,12);
      const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
      window.setPlayerPreview(name, num);
    });
    numEl.addEventListener('focus', () => {
      list.querySelectorAll('.player-row').forEach(r=>r.classList.remove('preview-active'));
      last.classList.add('preview-active');
      const name = (nameEl.value || '').toUpperCase().slice(0,12);
      const num  = (numEl.value || '').replace(/\D/g,'').slice(0,3);
      window.setPlayerPreview(name, num);
    });

    // live update while typing but only if this row is active
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
    // name: uppercase + maxlength 12
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

    // number: digits only, maxlength 3
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

  // add a new blank row
  addBtn.addEventListener('click', () => createRow());

  // create initial row(s) depending on prefill
  const pf = window.prefill || {};
  if ((pf.name && pf.name.length) || (pf.number && pf.number.length)) {
    // create a prefilled row
    const r = createRow({
      name: pf.name ? pf.name.toString().toUpperCase().slice(0,12) : '',
      number: pf.number ? pf.number.toString().replace(/\D/g,'').slice(0,3) : '',
      size: pf.size || ''
    });
    r.classList.add('preview-active');
    // show preview immediately
    if (typeof window.setPlayerPreview === 'function') {
      window.setPlayerPreview(pf.name ? pf.name.toString().toUpperCase().slice(0,12) : '', pf.number ? pf.number.toString().slice(0,3) : '');
    }
  } else {
    // no prefill => create a single blank row
    createRow();
  }

  // final form validation on submit
  form.addEventListener('submit', function(evt) {
    const rows = list.querySelectorAll('.player-row');
    if (rows.length === 0) {
      evt.preventDefault();
      alert('Please add at least one player.');
      return false;
    }
    const errors = [];
    rows.forEach((row, idx) => {
      const nameEl = row.querySelector('.player-name');
      const numEl  = row.querySelector('.player-number');
      const name = (nameEl?.value || '').trim();
      const num  = (numEl?.value || '').trim();

      if (!name) errors.push(`Row ${idx+1}: Name is required.`);
      else if (name.length > 12) errors.push(`Row ${idx+1}: Name must be 12 characters or fewer.`);

      if (!num) errors.push(`Row ${idx+1}: Number is required.`);
      else if (!/^\d{1,3}$/.test(num)) errors.push(`Row ${idx+1}: Number must be 1 to 3 digits.`);
    });

    if (errors.length) {
      evt.preventDefault();
      alert('Please fix these issues:\n\n' + errors.join('\n'));
      return false;
    }
    // OK => allow submit
    return true;
  });

});
</script>

@endsection
