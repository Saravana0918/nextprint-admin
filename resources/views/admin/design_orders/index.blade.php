@extends('layouts.app')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Design Orders</h2>
    <div>
      <a href="{{ route('admin.design-orders.index') }}" class="btn btn-sm btn-outline-secondary">Refresh</a>
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

            <td style="min-width:200px">
              {{ $row->product_name ?? $row->product_id ?? '—' }}
              @if(!empty($row->shopify_product_id))
                <div class="text-muted small">shopify: {{ $row->shopify_product_id }}</div>
              @endif
            </td>

            <td>{{ $row->name ?? $row->customer_name ?? '—' }}</td>
            <td>{{ $row->number ?? $row->customer_number ?? '—' }}</td>

            <td style="width:80px">
              @if(!empty($row->preview_image) || !empty($row->preview_src))
                @php
                  // support both alias names: preview_image (alias) or preview_src (original)
                  $preview = $row->preview_image ?? $row->preview_src ?? null;
                  $previewUrl = $preview;
                  if ($preview && (Str::startsWith($preview, '/storage') || Str::startsWith($preview, 'storage'))) {
                    $previewUrl = asset($preview);
                  }
                @endphp

                @if($previewUrl)
                  <a href="{{ $previewUrl }}" target="_blank" title="Open preview">
                    <img src="{{ $previewUrl }}" width="56" height="56" style="object-fit:cover; border-radius:4px;" alt="preview">
                  </a>
                @else
                  <span class="text-muted small">—</span>
                @endif
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
          <tr>
            <td colspan="8" class="text-center text-muted">No design orders found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($rows, 'links'))
    <div class="d-flex justify-content-center">
      {{ $rows->links() }}
    </div>
  @endif
</div>
@endsection
