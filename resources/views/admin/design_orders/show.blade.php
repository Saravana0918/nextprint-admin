@extends('layouts.admin')
@section('content')
<h3>Design Order #{{ $row->id }}</h3>
<p><strong>Shopify Order:</strong> {{ $row->shopify_order_id }}</p>
<p><strong>Product:</strong> {{ $row->product_id }} / {{ $row->variant_id }}</p>
<p><strong>Name:</strong> {{ $row->customer_name }}</p>
<p><strong>Number:</strong> {{ $row->customer_number }}</p>
<p><strong>Font:</strong> {{ $row->font }}</p>
<p><strong>Color:</strong> {{ $row->color }}</p>
@if($row->preview_src)
  <p><img src="{{ $row->preview_src }}" style="max-width:300px"></p>
@endif
@if($row->download_url)
  <p><a href="{{ $row->download_url }}" target="_blank">Download URL</a></p>
@endif

<h4>Payload (raw)</h4>
<pre style="white-space:pre-wrap; background:#f8f9fa; padding:12px;">{{ json_encode($row->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
@endsection
