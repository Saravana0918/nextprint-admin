@extends('layouts.app')

@section('content')
@php use Illuminate\Support\Str; @endphp

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
          <th>Shopify Product</th>
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

            <td style="min-width:140px">
              @if(!empty($row->shopify_product_id))
                <div>#{{ $row->shopify_product_id }}</div>
              @else
                <div class="text-muted">—</div>
              @endif
            </td>

            <td style="min-width:200px">
              {{ $row->product_name ?? $row->product_id ?? '—' }}
            </td>

            {{-- Use name_text / number_text (DB columns) --}}
            <td>{{ $row->name_text ?? '—' }}</td>
            <td>{{ $row->number_text ?? '—' }}</td>

            <td style="width:90px">
              @php
                $preview = $row->preview_src ?? null;
                $previewUrl = null;
                if (!empty($preview)) {
                  if (Str::startsWith($preview, '/storage') || Str::startsWith($preview, 'storage')) {
                    $previewUrl = asset($preview);
                  } else {
                    // allow absolute http(s) or data:image
                    $previewUrl = $preview;
                  }
                }
              @endphp

              @if($previewUrl)
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
