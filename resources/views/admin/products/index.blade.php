@extends('layouts.admin')
@section('title','Products')

@push('styles')
<style>
  .preview-img{
    width:50px;height:50px;object-fit:cover;border-radius:6px;background:#f3f4f6
  }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">Products</h3>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.decoration.index') }}" class="btn btn-outline-primary">Manage Decoration Areas</a>
    <a href="{{ route('admin.print-methods.index') }}" class="btn btn-outline-secondary">Print Methods</a>
  </div>
</div>

<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Preview</th>
        <th>Name</th>
        <th>Price</th>
        <th>Vendor</th>
        <th>Status</th>
        <th>Methods</th>
        <th>Action</th>
      </tr>
    </thead>

    <tbody>
      @foreach($rows as $p)
        <tr>
          <td>{{ $p->id }}</td>

          {{-- Preview --}}
          <td>
  @php $src = $p->preview_src ?? null; @endphp
  @if($src)
    <img src="{{ $src }}" alt="preview"
         style="width:50px;height:50px;object-fit:contain"
         loading="lazy" referrerpolicy="no-referrer"
         onerror="this.onerror=null; this.src='{{ asset('images/placeholder.png') }}'">
  @else
    <img src="{{ asset('images/placeholder.png') }}" alt="preview"
         style="width:50px;height:50px;object-fit:contain">
  @endif
</td>


    <td>{{ $p->name }}</td>
    <td>₹{{ $p->min_price }}</td>
    <td>{{ $p->vendor }}</td>
    <td>{{ $p->status }}</td>
    <td>
    @if(!empty($p->methods))
      @foreach(explode(',', $p->methods) as $m)
        <span class="badge bg-secondary" style="margin-right:4px;">
          {{ trim($m) }}
        </span>
      @endforeach
    @else
      <span class="text-muted">—</span>
    @endif
  </td>


    <td class="text-nowrap">
      <a href="{{ route('admin.products.edit', $p->id) }}" class="btn btn-warning btn-sm">Edit</a>

      <form action="{{ route('admin.products.destroy', $p->id) }}"
            method="POST" class="d-inline"
            onsubmit="return confirm('Delete this product?');">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>

      <a href="{{ route('admin.products.decoration', $p->id) }}" class="btn btn-primary btn-sm">
        Decoration Area (Front)
      </a>
      <button type="button" class="btn btn-secondary btn-sm ms-1 btn-settings" data-product-id="{{ $product->id }}" data-product-preview="{{ $product->preview_src ?? '' }}">
        Settings
      </button>
    </td>
  </tr>
@endforeach
</tbody>

  </table>
  <div class="mt-3">
  {{ $rows->links() }}
</div>
</div>
@endsection
