@extends('layouts.admin')

@section('content')
<h2 class="mb-3">Edit product: {{ $product->name }}</h2>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('admin.products.update', $product) }}">
  @csrf
  @method('PUT')

  <div class="mb-3">
    <label class="form-label">Name</label>
    <input name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Price (₹)</label>
    <input name="price" type="number" step="0.01" class="form-control"
           value="{{ old('price', $product->price) }}">
  </div>

  <div class="mb-3">
    <label class="form-label">SKU</label>
    <input name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
  </div>

  <div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="ACTIVE"   @selected(old('status',$product->status)==='ACTIVE')>ACTIVE</option>
      <option value="INACTIVE" @selected(old('status',$product->status)==='INACTIVE')>INACTIVE</option>
    </select>
  </div>

  <div class="form-group">
    <label>Select Print Methods</label>
    <select name="print_method_ids[]" multiple size="6" class="form-control">
      @foreach($methods as $m)
        <option value="{{ $m->id }}"
          @selected( $product->printMethods->pluck('id')->contains($m->id) )>
          {{ $m->name }}
        </option>
      @endforeach
    </select>
    </div>

  <button class="btn btn-primary">Save</button>
  <a href="{{ route('admin.products') }}" class="btn btn-outline-secondary">Cancel</a>
</form>
@endsection
