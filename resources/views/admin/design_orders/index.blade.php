@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Design Orders</h2>
    <div>
      <a href="{{ route('admin.design-orders.index') }}" class="btn btn-sm btn-outline-secondary">Refresh</a>
      {{-- optionally add export button --}}
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Shopify Order</th>
          <th>Product</th>
          <th>Name</th>
          <th>Number</th>
          <th>Preview</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
        <tr>
          <td>{{ $row->id }}</td>
          <td>{{ $row->shopify_order_id ?: '—' }}</td>

          {{-- product name (controller should supply product_name) --}}
          <td>
            {{ $row->product_name ?? $row->product_id ?? '—' }}
            @if(!empty($row->shopify_product_id))
              <div class="text-muted small">shopify: {{ $row->shopify_product_id }}</div>
            @endif
          </td>

          {{-- name/number — view expects 'name' and 'number' keys (controller will alias these) --}}
          <td>{{ $row->name ?? $row->name_text ?? '—' }}</td>
          <td>{{ $row->number ?? $row->number_text ?? '—' }}</td>

          {{-- preview image handling: support full URL or storage path --}}
          <td>
            @if(!empty($row->preview_image))
              @php
                $preview = $row->preview_image;
                // if stored as /storage/..., use asset(); otherwise assume full URL
                if (str_starts_with($preview, '/storage') || str_starts_with($preview, 'storage')) {
                  $previewUrl = asset($preview);
                } else {
                  $previewUrl = $preview;
                }
              @endphp
              <a href="{{ $previewUrl }}" target="_blank" title="Open preview">
                <img src="{{ $previewUrl }}" width="56" height="56" style="object-fit:cover; border-radius:4px;" alt="preview">
              </a>
            @else
              <span class="text-muted small">—</span>
            @endif
          </td>

          <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y, H:i') }}</td>
          <td>
            <a href="{{ route('admin.design-orders.show', $row->id) }}" class="btn btn-sm btn-primary">View</a>
          </td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted">No design orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- pagination (works if $rows is a Paginator) --}}
  @if(method_exists($rows, 'links'))
    <div class="d-flex justify-content-center">
      {{ $rows->links() }}
    </div>
  @endif
</div>
@endsection
