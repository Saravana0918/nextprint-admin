@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h2>Design Orders</h2>
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
        <td>{{ $row->shopify_order_id }}</td>
        <td>{{ $row->product_id }} / {{ $row->product_name }}</td>
        <td>{{ $row->name }}</td>
        <td>{{ $row->number }}</td>
        <td>
          @if(!empty($row->preview_image))
            <img src="{{ $row->preview_image }}" width="50" alt="preview">
          @endif
        </td>
        <td>{{ $row->created_at }}</td>
        <td><a href="{{ route('admin.design-orders.show', $row->id) }}" class="btn btn-sm btn-link">View</a></td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-center text-muted">No design orders found.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
