@extends('layouts.app')

@section('content')
<div class="container py-4">
  <a href="{{ route('admin.design-orders.index') }}" class="btn btn-sm btn-outline-secondary mb-3">Back</a>

  <div class="row">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Design Order #{{ $order->id }}</h5>
          <p class="mb-1"><strong>Product:</strong> {{ $order->product_name ?? $order->product_id }}</p>
          <p class="mb-1"><strong>Shopify Order:</strong> {{ $order->shopify_order_id ?? '—' }}</p>
          <p class="mb-1"><strong>Name / Number:</strong> {{ $order->name_text ?? '—' }} / {{ $order->number_text ?? '—' }}</p>
          <p class="mb-1"><strong>Font / Color:</strong> {{ $order->font ?? '—' }} / {{ $order->color ?? '—' }}</p>
          <p class="mb-1"><strong>Size / Qty:</strong> {{ $order->size ?? '—' }} / {{ $order->quantity ?? 1 }}</p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6>Preview</h6>
          @if(!empty($order->preview_image))
            @php
              $preview = $order->preview_image;
              $previewUrl = (str_starts_with($preview, '/storage') || str_starts_with($preview, 'storage')) ? asset($preview) : $preview;
            @endphp
            <a href="{{ $previewUrl }}" target="_blank">
              <img src="{{ $previewUrl }}" alt="preview" class="img-fluid border rounded">
            </a>
          @else
            <div class="text-muted">No preview available</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-body">
          <h6>Team Players ({{ $players->count() }})</h6>
          @if($players->isEmpty())
            <div class="text-muted">No players saved.</div>
          @else
            <table class="table table-sm">
              <thead>
                <tr><th>#</th><th>Name</th><th>Number</th><th>Size</th><th>Preview</th></tr>
              </thead>
              <tbody>
                @foreach($players as $p)
                  <tr>
                    <td>{{ $p->id }}</td>
                    <td>{{ $p->name }}</td>
                    <td>{{ $p->number }}</td>
                    <td>{{ $p->size }}</td>
                    <td>
                      @if(!empty($p->preview_image))
                        <img src="{{ (str_starts_with($p->preview_image,'/storage') ? asset($p->preview_image) : $p->preview_image) }}" width="48" style="object-fit:cover;border-radius:4px;">
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
