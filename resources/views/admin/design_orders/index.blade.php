@extends('layouts.admin')
@section('content')
<h3>Design Orders</h3>
<table class="table">
  <thead><tr>
    <th>ID</th><th>Shopify Order</th><th>Product</th><th>Name</th><th>Number</th><th>Preview</th><th>Created</th><th></th>
  </tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr>
      <td>{{ $r->id }}</td>
      <td>{{ $r->shopify_order_id }}</td>
      <td>{{ $r->product_id }} / {{ $r->variant_id }}</td>
      <td>{{ $r->customer_name }}</td>
      <td>{{ $r->customer_number }}</td>
      <td>
        @if($r->preview_src)
          <img src="{{ $r->preview_src }}" style="width:60px;height:60px;object-fit:cover" />
        @elseif(!empty($r->payload['image_url']))
          <img src="{{ $r->payload['image_url'] }}" style="width:60px;height:60px;object-fit:cover" />
        @endif
      </td>
      <td>{{ optional($r->created_at)->format('Y-m-d H:i') }}</td>
      <td><a href="{{ route('admin.design-orders.show', $r->id) }}">View</a></td>
    </tr>
  @endforeach
  </tbody>
</table>
{{ $rows->links() }}
@endsection
