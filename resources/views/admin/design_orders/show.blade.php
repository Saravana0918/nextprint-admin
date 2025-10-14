@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h3>Design Order: {{ $first->shopify_order_id }}</h3>
  <p><strong>Product:</strong> {{ $first->product_id }}</p>
  <p><strong>Created:</strong> {{ $first->created_at }}</p>

  <h4 class="mt-4">Players</h4>
  <table class="table table-bordered align-middle">
    <thead>
      <tr>
        <th>Name</th>
        <th>Number</th>
        <th>Preview</th>
      </tr>
    </thead>
    <tbody>
      @foreach($players as $p)
      <tr>
        <td>{{ $p->name }}</td>
        <td>{{ $p->number }}</td>
        <td>
          @if(!empty($p->preview_image))
            <img src="{{ $p->preview_image }}" width="60" alt="preview">
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <a href="{{ route('admin.design-orders.index') }}" class="btn btn-secondary">Back</a>
</div>
@endsection
