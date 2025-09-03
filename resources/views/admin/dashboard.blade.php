@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
  <div class="row g-3">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-1">Syndron UI wired to Laravel ✅</h4>
           <a href="{{ route('admin.products') }}" class="btn btn-primary mt-3">Go to Products</a>
        </div>
      </div>
    </div>
  </div>
@endsection
