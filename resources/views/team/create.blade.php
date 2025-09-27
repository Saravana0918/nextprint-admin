@extends('layouts.app')

@section('content')
<div class="container py-4">
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

    <div id="players-list">
      <!-- initial example row inserted by JS -->
    </div>

    <div class="mt-3">
      <button type="submit" class="btn btn-success">Save Team</button>
      <a href="{{ url()->previous() }}" class="btn btn-secondary">Back</a>
    </div>
  </form>
</div>

<!-- template for player row (hidden) -->
<template id="player-row-template">
  <div class="player-row card mb-2 p-2 d-flex align-items-center">
    <div style="width:60px">
      <input name="players[][number]" class="form-control player-number" placeholder="00" />
    </div>
    <div class="mx-2 flex-grow-1">
      <input name="players[][name]" class="form-control player-name" placeholder="PLAYER NAME" />
    </div>
    <div style="width:110px">
      <select name="players[][size]" class="form-control player-size">
        <option value="">Size</option>
        <option value="XS">XS</option>
        <option value="S">S</option>
        <option value="M">M</option>
        <option value="L">L</option>
        <option value="XL">XL</option>
      </select>
    </div>
    <div class="ms-2">
      <button type="button" class="btn btn-danger btn-remove">Remove</button>
    </div>
  </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const addBtn = document.getElementById('btn-add-row');
  const list = document.getElementById('players-list');
  const template = document.getElementById('player-row-template').content;

  function addRow(data = {}) {
    const clone = template.cloneNode(true);
    const row = clone.querySelector('.player-row');

    // fill defaults if provided
    if (data.number) row.querySelector('.player-number').value = data.number;
    if (data.name)   row.querySelector('.player-name').value = data.name;
    if (data.size)   row.querySelector('.player-size').value = data.size;

    row.querySelector('.btn-remove').addEventListener('click', () => {
      row.remove();
    });

    list.appendChild(row);
  }

  addBtn.addEventListener('click', ()=> addRow());

  // add 1 initial row
  addRow();

  // optional: validate on submit
  document.getElementById('team-form').addEventListener('submit', function(e){
    const rows = document.querySelectorAll('.player-row');
    if (rows.length === 0) {
      e.preventDefault();
      alert('Add at least one player.');
      return;
    }
    // additional client-side validation if you want
  });
});
</script>
@endsection
