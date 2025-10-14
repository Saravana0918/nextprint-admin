@extends('layouts.admin')
@section('content')

<h3>Design Order @if($first->shopify_order_id) (Shopify: {{ $first->shopify_order_id }}) @else #{{ $first->id }} @endif</h3>

<p><strong>Product:</strong> {{ $first->product_id }}{{ $first->variant_id ? ' / '.$first->variant_id : '' }}</p>

<h4>Players ({{ $orders->count() }})</h4>
<table class="table">
  <thead>
    <tr><th>#</th><th>Name</th><th>Number</th><th>Font</th><th>Color</th><th>Preview</th></tr>
  </thead>
  <tbody>
    @foreach($orders as $o)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $o->customer_name }}</td>
        <td>{{ $o->customer_number }}</td>
        <td>{{ $o->font }}</td>
        <td>{{ $o->color }}</td>
        <td>
          @if($o->preview_src)
            <img src="{{ $o->preview_src }}" style="width:80px;height:80px;object-fit:cover" />
          @elseif(!empty($o->payload['image_url']))
            <img src="{{ $o->payload['image_url'] }}" style="width:80px;height:80px;object-fit:cover" />
          @else
            <img src="{{ asset('images/placeholder.png') }}" style="width:80px;height:80px;object-fit:cover" />
          @endif
        </td>
      </tr>
    @endforeach
  </tbody>
</table>

<p><strong>Created:</strong> {{ $first->created_at }}</p>

<h4>Payload (raw)</h4>
<pre style="white-space:pre-wrap;background:#f8f9fa;padding:12px;">{{ json_encode($first->payload, JSON_PRETTY_PRINT) }}</pre>

@endsection
