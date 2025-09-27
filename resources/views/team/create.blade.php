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
    <!-- RIGHT: product preview (thumbnail) -->
<div style="width:320px; flex-shrink:0;">
  <div class="card">
    <div class="card-body text-center" style="position:relative;">
      <!-- Stage container (position:relative) -->
      <div id="player-stage" style="position:relative; display:inline-block; width:220px; height:320px;">
        <img id="player-base" src="{{ $product->image_url ?? asset('images/placeholder.png') }}"
             alt="{{ $product->name }}" class="img-fluid" style="width:100%; height:100%; object-fit:contain;">
        <!-- Overlays -->
        <div id="overlay-name" style="
            position:absolute;
            top:38px;              /* adjust to place near neck */
            left:50%;
            transform:translateX(-50%);
            font-weight:800;
            font-family: 'Bebas Neue', sans-serif;
            color:#D4AF37;
            text-shadow: 0 3px 6px rgba(0,0,0,0.6);
            font-size:20px;
            pointer-events:none;
            white-space:nowrap;
        ">NAME</div>

        <div id="overlay-number" style="
            position:absolute;
            bottom:58px;           /* adjust to place on back lower */
            left:50%;
            transform:translateX(-50%);
            font-weight:900;
            font-family: 'Bebas Neue', sans-serif;
            color:#D4AF37;
            text-shadow: 0 3px 6px rgba(0,0,0,0.6);
            font-size:30px;
            pointer-events:none;
            white-space:nowrap;
        ">NUMBER</div>
      </div>

      <!-- product meta below -->
      <h5 class="card-title mt-3">{{ $product->name }}</h5>
      <p class="text-muted">Price: ₹ {{ number_format($product->min_price ?? 0, 2) }}</p>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  const list = document.getElementById('players-list');
  const template = document.getElementById('player-row-template');
  const addBtn = document.getElementById('btn-add-row');

  // overlay elements
  const overlayName = document.getElementById('overlay-name');
  const overlayNum  = document.getElementById('overlay-number');

  function addRow() {
    const node = template.content.cloneNode(true);
    list.appendChild(node);
    wireRowEvents();
  }

  function wireRowEvents() {
    // wire remove buttons
    list.querySelectorAll('.btn-remove').forEach(btn=>{
      if (!btn.dataset.wired) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', e => {
          const row = e.target.closest('.player-row');
          row.remove();
          // optional: clear overlay if removed active row
        });
      }
    });

    // wire preview buttons (show this row on jersey)
    list.querySelectorAll('.btn-preview').forEach(btn=>{
      if (!btn.dataset.wired) {
        btn.dataset.wired = '1';
        btn.addEventListener('click', e => {
          const row = e.target.closest('.player-row');
          const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
          const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
          overlayName.textContent = name || 'NAME';
          overlayNum.textContent = num || 'NUMBER';
        });
      }
    });

    // wire live typing sync on focused row
    list.querySelectorAll('.player-name, .player-number').forEach(inp=>{
      if (!inp.dataset.wired) {
        inp.dataset.wired = '1';
        inp.addEventListener('input', e => {
          // update overlay only if this input's row has been last-previewed OR if input is focused and you want live update
          const row = e.target.closest('.player-row');
          // if this row was marked as active, update immediately
          if (row.classList.contains('preview-active')) {
            const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
            const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
            overlayName.textContent = name || 'NAME';
            overlayNum.textContent = num || 'NUMBER';
          }
        });

        // set preview-active when focus (optional: live preview)
        inp.addEventListener('focus', (e) => {
          list.querySelectorAll('.player-row').forEach(r => r.classList.remove('preview-active'));
          const row = e.target.closest('.player-row');
          if (row) {
            row.classList.add('preview-active');
            // also update overlay when focusing
            const name = (row.querySelector('.player-name')?.value || '').toUpperCase();
            const num  = (row.querySelector('.player-number')?.value || '').replace(/\D/g,'');
            overlayName.textContent = name || 'NAME';
            overlayNum.textContent = num || 'NUMBER';
          }
        });
      }
    });
  }

  addBtn.addEventListener('click', addRow);

  // initial row
  addRow();
});
</script>

@endsection
