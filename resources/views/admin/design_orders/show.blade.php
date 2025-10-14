@extends('layouts.app')

@section('content')
@php use Illuminate\Support\Str; @endphp

<div class="container py-4">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
      <a href="{{ route('admin.design-orders.index') }}" class="btn btn-sm btn-outline-secondary">← Back to Orders</a>
    </div>
    <div>
      <span class="badge bg-secondary">Order #{{ $order->id }}</span>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title mb-2">
            {{ $order->product_name ?? ('Product #' . ($order->product_id ?? '—')) }}
          </h5>

          <p class="mb-1"><strong>Shopify Product:</strong> {{ $order->shopify_product_id ?? '—' }}</p>
          <p class="mb-1"><strong>Shopify Line Item:</strong> {{ $order->shopify_line_item_id ?? '—' }}</p>

          <p class="mb-1">
            <strong>Customer Name / Number:</strong>
            {{ $order->name_text ?? '—' }} / {{ $order->number_text ?? '—' }}
          </p>

          <p class="mb-1"><strong>Font / Color:</strong> {{ $order->font ?? '—' }} / {{ $order->color ?? '—' }}</p>
          <p class="mb-1"><strong>Variant ID:</strong> {{ $order->variant_id ?? '—' }}</p>
          <p class="mb-1"><strong>Quantity:</strong> {{ $order->quantity ?? '1' }}</p>

          <p class="mb-1"><strong>Uploaded Logo URL:</strong>
            @if(!empty($order->uploaded_logo_url))
              <a href="{{ $order->uploaded_logo_url }}" target="_blank">Open logo</a>
            @else
              <span class="text-muted">—</span>
            @endif
          </p>

          <p class="mb-1"><strong>Saved Payload:</strong></p>
          <pre style="white-space:pre-wrap; max-height:220px; overflow:auto; background:#f8f9fa; padding:8px; border-radius:6px;">
{{ json_encode(json_decode($order->raw_payload ?? '{}'), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}
          </pre>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6 class="mb-2">Preview</h6>

          @php
            $preview = $order->preview_src ?? null;
            $previewUrl = null;
            if (!empty($preview)) {
              if (Str::startsWith($preview, '/storage') || Str::startsWith($preview, 'storage')) {
                $previewUrl = asset($preview);
              } else {
                $previewUrl = $preview;
              }
            }
          @endphp

          @if($previewUrl)
            <a href="{{ $previewUrl }}" target="_blank">
              <img src="{{ $previewUrl }}" alt="preview" class="img-fluid border rounded">
            </a>
            <div class="mt-2 text-muted small">Click image to open in a new tab.</div>
          @else
            <div class="text-muted">No preview available</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-6">
      {{-- Team players card --}}
      <div class="card mb-3">
        <div class="card-body">
          <h6>Team Players ({{ $players->count() }})</h6>
          @if($players->isEmpty())
            <div class="text-muted">No players saved for this order.</div>
          @else
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Number</th>
                    <th>Size</th>
                    <th>Font</th>
                    <th>Preview</th>
                    <th>Saved</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($players as $p)
                    @php
                      $pPreview = $p->preview_image ?? $p->preview_src ?? null;
                      $pUrl = null;
                      if (!empty($pPreview)) {
                        if (Str::startsWith($pPreview, '/storage') || Str::startsWith($pPreview, 'storage')) {
                          $pUrl = asset($pPreview);
                        } else {
                          $pUrl = $pPreview;
                        }
                      }
                    @endphp
                    <tr>
                      <td>{{ $p->id }}</td>
                      <td>{{ $p->name }}</td>
                      <td>{{ $p->number }}</td>
                      <td>{{ $p->size }}</td>
                      <td>{{ $p->font ?? ($order->font ?? '—') }}</td>
                      <td style="width:90px;">
                        @if($pUrl)
                          <img src="{{ $pUrl }}" width="56" height="56" style="object-fit:cover; border-radius:4px;">
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>
                      <td class="text-muted small">{{ \Carbon\Carbon::parse($p->created_at ?? now())->format('d M Y') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>

      {{-- Metadata --}}
      <div class="card">
        <div class="card-body">
          <h6>Metadata</h6>
          <ul class="list-unstyled small mb-0">
            <li><strong>Stored at:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y, H:i') }}</li>
            <li><strong>Status:</strong> {{ $order->status ?? '—' }}</li>
            <li><strong>Download URL:</strong>
              @if(!empty($order->download_url))
                <a href="{{ $order->download_url }}" target="_blank">Download</a>
              @else
                <span class="text-muted">—</span>
              @endif
            </li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
