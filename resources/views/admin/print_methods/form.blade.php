@extends('layouts.app')

@section('title', ($method->exists ? 'Edit' : 'New').' Print Method')

@section('content')
<h3 class="mb-3">{{ $method->exists ? 'Edit' : 'New' }} Print Method</h3>

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="post"
      action="{{ $method->exists
                ? route('admin.print-methods.update', $method)
                : route('admin.print-methods.store') }}">
  @csrf
  @if($method->exists) @method('PUT') @endif

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Name *</label>
      <input type="text" name="name" class="form-control"
             value="{{ old('name', $method->name) }}" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Code (unique, optional)</label>
      <input type="text" name="code" class="form-control"
             value="{{ old('code', $method->code) }}">
    </div>

    <div class="col-md-6">
      <label class="form-label">Icon URL</label>
      <input type="url" name="icon_url" class="form-control"
             value="{{ old('icon_url', $method->icon_url) }}">
    </div>

    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="ACTIVE"   @selected(old('status',$method->status)=='ACTIVE')>ACTIVE</option>
        <option value="INACTIVE" @selected(old('status',$method->status)=='INACTIVE')>INACTIVE</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label">Sort order</label>
      <input type="number" name="sort_order" class="form-control"
             value="{{ old('sort_order', $method->sort_order ?? 0) }}">
    </div>

    <div class="col-12">
      <label class="form-label">Description</label>
      <textarea name="description" rows="3" class="form-control">{{ old('description', $method->description) }}</textarea>
    </div>
  </div>

  <div class="mt-4">
    <a href="{{ route('admin.print-methods.index') }}" class="btn btn-outline-secondary">Back</a>
    <button class="btn btn-primary">Save</button>
  </div>
</form>
@endsection
