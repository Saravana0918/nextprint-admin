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
    <div style="width:320px; flex-shrink:0;">
      <div class="card">
        <div class="card-body text-center">
          <img src="{{ $product->image_url ?? asset('images/placeholder.png') }}" 
               alt="{{ $product->name }}" class="img-fluid mb-2" style="max-height:260px; object-fit:contain;">
          <h5 class="card-title">{{ $product->name }}</h5>
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
      <input name="players[][number]" class="form-control w-25" placeholder="00" />
      <input name="players[][name]" class="form-control" placeholder="PLAYER NAME" />
      <select name="players[][size]" class="form-select w-25">
        <option value="">Size</option>
        <option value="XS">XS</option>
        <option value="S">S</option>
        <option value="M">M</option>
        <option value="L">L</option>
        <option value="XL">XL</option>
      </select>
      <button type="button" class="btn btn-danger btn-remove">Remove</button>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const addBtn = document.getElementById('btn-add-row');
  const list = document.getElementById('players-list');
  const template = document.getElementById('player-row-template');

  function addRow() {
    const node = template.content.cloneNode(true);
    list.appendChild(node);
    // wire remove
    list.querySelectorAll('.btn-remove').forEach(btn=>{
      btn.onclick = (e)=> e.target.closest('.player-row').remove();
    });
  }

  addBtn.addEventListener('click', addRow);

  // add initial row
  addRow();
});
</script>
@endsection
