@extends('layouts.app')

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Storage;

  // small font key -> friendly name map
  $fontMap = [
    'bebas' => 'Bebas Neue',
    'bebas neue' => 'Bebas Neue',
    'bebas-neue' => 'Bebas Neue',
    'oswald' => 'Oswald',
    'anton' => 'Anton',
    'impact' => 'Impact',
  ];

  // helper: normalize color to hex with leading #
  $normalizeColor = function($c) {
    if (!$c) return null;
    $c = trim((string)$c);
    try { $c = urldecode($c); } catch(\Throwable$e){}
    if ($c === '') return null;
    if ($c[0] !== '#') $c = '#' . ltrim($c, '#');
    return $c;
  };

  $orderFontRaw = trim((string)($order->font ?? ''));
  $orderFontKey = strtolower(preg_replace('/[^a-z0-9]+/',' ', $orderFontRaw));
  $orderFontLabel = $fontMap[$orderFontKey] ?? ($order->font ? $order->font : '—');
  $orderColorHex = $normalizeColor($order->color ?? null);
@endphp

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

          <p class="mb-1">
            <strong>Customer Name / Number:</strong>
            {{ $order->name_text ?? '—' }} / {{ $order->number_text ?? '—' }}
          </p>

          <p class="mb-1">
            <strong>Font / Color:</strong>
            <span title="{{ $orderFontRaw }}">{{ $orderFontLabel }}</span>
            &nbsp;/&nbsp;
            @if($orderColorHex)
              <span class="d-inline-flex align-items-center">
                <span style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;background:{{ $orderColorHex }};margin-right:6px;border-radius:3px;"></span>
                <code style="font-size:15px;">{{ $orderColorHex }}</code>
              </span>
            @else
              <span class="text-muted">—</span>
            @endif
          </p>

          <p class="mb-1"><strong>Quantity:</strong> {{ $order->quantity ?? '1' }}</p>

          <p class="mb-1"><strong>Uploaded Logo URL:</strong>
            @if(!empty($order->uploaded_logo_url))
              <a href="{{ $order->uploaded_logo_url }}" target="_blank">Open logo</a>
            @else
              <span class="text-muted">—</span>
            @endif
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h6 class="mb-2">Preview</h6>

          @php
            $preview = $order->preview_src ?? $order->preview_path ?? null;
            $previewUrl = null;
            if (!empty($preview)) {
              if (Str::startsWith($preview, '/storage') || Str::startsWith($preview, 'storage')) {
                $previewUrl = asset(ltrim($preview, '/'));
              } elseif (Str::startsWith($preview, 'http://') || Str::startsWith($preview, 'https://')) {
                $previewUrl = $preview;
              } else {
                // try disk public existence (preview may be "team_previews/xxx.png")
                if (Storage::disk('public')->exists($preview)) {
                  $previewUrl = asset('storage/' . ltrim($preview, '/'));
                } else {
                  // maybe saved already as '/storage/...'
                  $previewUrl = $preview;
                }
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
                  @foreach($players as $index => $p)
                    @php
                      $pObj = is_object($p) ? $p : (object)$p;
                      $pId = $pObj->id ?? ($index+1);
                      $pName = $pObj->name ?? '';
                      $pNumber = $pObj->number ?? '';
                      $pSize = $pObj->size ?? '';
                      $pFontRaw = $pObj->font ?? $order->font ?? '';
                      $pFontKey = strtolower(preg_replace('/[^a-z0-9]+/',' ', trim((string)$pFontRaw)));
                      $pFontLabel = $fontMap[$pFontKey] ?? ($pFontRaw ?: '—');

                      $pColor = $pObj->color ?? null;
                      if ($pColor) { $pColor = (strpos($pColor, '#') === 0) ? $pColor : '#' . ltrim($pColor, '#'); }

                      $pPreview = $pObj->preview_src ?? null;
                      $pUrl = null;
                      if (!empty($pPreview)) {
                        if (Str::startsWith($pPreview, '/storage') || Str::startsWith($pPreview, 'storage')) {
                          $pUrl = asset(ltrim($pPreview, '/'));
                        } elseif (Str::startsWith($pPreview, 'http://') || Str::startsWith($pPreview, 'https://')) {
                          $pUrl = $pPreview;
                        } else {
                          $pUrl = Storage::disk('public')->exists($pPreview) ? asset('storage/'.$pPreview) : $pPreview;
                        }
                      }

                      $pCreated = $pObj->created_at ?? null;
                    @endphp

                    <tr>
                      <td>{{ $pId }}</td>
                      <td>{{ $pName }}</td>
                      <td>{{ $pNumber }}</td>
                      <td>{{ $pSize }}</td>
                      <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                          <span>{{ $pFontLabel }}</span>
                          @if($pColor)
                            <span style="display:inline-block;width:14px;height:14px;background:{{ $pColor }};border:1px solid #ccc;border-radius:3px;"></span>
                            <small class="text-muted">{{ $pColor }}</small>
                          @endif
                        </div>
                      </td>
                      <td style="width:90px;">
                        @if($pUrl)
                          <img src="{{ $pUrl }}" width="56" height="56" style="object-fit:cover; border-radius:4px;">
                        @else
                          <span class="text-muted small">—</span>
                        @endif
                      </td>
                      <td class="text-muted small">
                        @if($pCreated)
                          {{ \Carbon\Carbon::parse($pCreated)->format('d M Y') }}
                        @else
                          {{ \Carbon\Carbon::parse($order->created_at)->format('d M Y') }}
                        @endif
                      </td>
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
              @if(!empty($order->preview_src) || !empty($order->preview_path) || !empty($order->preview_url))
                <a href="{{ route('admin.design-orders.download', ['id' => $order->id]) }}" class="btn btn-sm btn-outline-primary">Download package</a>
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
