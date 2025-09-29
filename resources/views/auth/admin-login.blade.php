@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card p-4">
        <h4 class="mb-3">Admin Login</h4>

        @if($errors->any())
          <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('admin.login.post') }}">
          @csrf
          <div class="mb-3">
            <label>Email</label>
            <input name="email" value="{{ old('email') }}" class="form-control" />
          </div>
          <div class="mb-3">
            <label>Password</label>
            <input name="password" type="password" class="form-control" />
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Login</button>
            <a href="{{ url('/') }}" class="btn btn-secondary">Home</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
