@extends('layouts.app') {{-- உங்க base layout ஏதுனா அதையே use pannunga --}}

@section('title', 'Print Methods')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">Print Methods</h3>

  <a href="{{ route('admin.print-methods.create') }}" class="btn btn-primary">
    + New print method
  </a>
</div>

<form method="get" class="mb-3">
  <div class="input-group" style="max-width:420px">
    <input type="text" name="q" class="form-control" placeholder="Search name/code"
           value="{{ request('q') }}">
    <button class="btn btn-outline-secondary">Search</button>
  </div>
</form>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead>
      <tr>
        <th style="width:42px">#</th>
        <th>Name</th>
        <th>Code</th>
        <th>Status</th>
        <th style="width:90px">Sort</th>
        <th style="width:260px">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $i => $m)
        <tr>
          <td>{{ $m->id }}</td>
          <td>
            @if($m->icon_url)
              <img src="{{ $m->icon_url }}" alt="" style="width:22px;height:22px;object-fit:cover;border-radius:4px;margin-right:6px">
            @endif
            {{ $m->name }}
          </td>
          <td><code>{{ $m->code }}</code></td>
          <td>
            <span class="badge {{ $m->status === 'ACTIVE' ? 'bg-success' : 'bg-secondary' }}">
              {{ $m->status }}
            </span>
          </td>
          <td>{{ $m->sort_order }}</td>
          <td class="text-nowrap">
            <a href="{{ route('admin.print-methods.edit', $m) }}" class="btn btn-sm btn-warning">Edit</a>

            <form action="{{ route('admin.print-methods.toggle', $m) }}" method="post" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-outline-secondary">
                {{ $m->status === 'ACTIVE' ? 'Disable' : 'Enable' }}
              </button>
            </form>

            <form action="{{ route('admin.print-methods.clone', $m) }}" method="post" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-info">Clone</button>
            </form>

            <form action="{{ route('admin.print-methods.destroy', $m) }}" method="post"
                  class="d-inline" onsubmit="return confirm('Delete this print method?')">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-muted">No print methods yet.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div>
  {{ $rows->withQueryString()->links() }}
</div>
@endsection
