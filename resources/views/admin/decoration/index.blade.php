{{-- resources/views/admin/decoration/index.blade.php --}}
@extends('layouts.admin')

@section('content')
<h3>Manage decoration areas</h3>

@if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
@if($errors->any())
  <div class="alert alert-danger mb-3">
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="post" action="{{ route('admin.decoration.store') }}" enctype="multipart/form-data"
      class="card p-3 mb-3" style="max-width:900px">
  @csrf
  <div class="row g-2 align-items-end">

    <div class="col-3">
      <label class="form-label">Category</label>
      <select name="category" class="form-control">
        <option value="without_bleed" @selected(old('category')==='without_bleed')>Without bleed Mark</option>
        <option value="custom"        @selected(old('category')==='custom')>Custom</option>
        <option value="regular"       @selected(old('category')==='regular')>Regular</option>
      </select>
    </div>

    <div class="col-3">
      <label class="form-label">Name</label>
      <input name="name" class="form-control" placeholder="Front / Heart / A4" value="{{ old('name') }}" required>
    </div>

    <div class="col-2">
      <label class="form-label">Width (mm)</label>
      <input name="width_mm" type="number" class="form-control" value="{{ old('width_mm') }}" required>
    </div>

    <div class="col-2">
      <label class="form-label">Height (mm)</label>
      <input name="height_mm" type="number" class="form-control" value="{{ old('height_mm') }}" required>
    </div>

    <div class="col-2">
      <label class="form-label">Preview (SVG)</label>
      <input type="file" name="svg" accept="image/svg+xml" class="form-control">
    </div>

    {{-- NEW: Type + Max chars --}}
    <div class="col-3">
      <label class="form-label">Type (Usage)</label>
      <select name="slot_key" id="slot_key" class="form-control">
        <option value=""        @selected(old('slot_key')==='')>— Generic —</option>
        <option value="name"    @selected(old('slot_key')==='name')>Name</option>
        <option value="number"  @selected(old('slot_key')==='number')>Number</option>
      </select>
      <div class="form-text">Choose “Name” or “Number” if this template is for text placement.</div>
    </div>

    <div class="col-2">
      <label class="form-label">Max chars (optional)</label>
      <input name="max_chars" id="max_chars" type="number" min="1" step="1"
             class="form-control" value="{{ old('max_chars') }}" placeholder="e.g. 12">
    </div>

    <div class="col-12">
      <button class="btn btn-primary">✓ Save</button>
    </div>
  </div>
</form>

<table class="table">
  <thead>
    <tr>
      <th>Category</th><th>Name</th><th>Size (mm)</th><th>Type</th><th>SVG</th><th>Action</th>
    </tr>
  </thead>
  <tbody>
  @foreach($items as $it)
    <tr>
      <td>{{ ucfirst(str_replace('_',' ', $it->category)) }}</td>
      <td>{{ $it->name }}</td>
      <td>{{ $it->width_mm }} × {{ $it->height_mm }}</td>
      <td>
        @if($it->slot_key === 'name')  <span class="badge bg-secondary">Name</span>
        @elseif($it->slot_key === 'number') <span class="badge bg-secondary">Number</span>
        @else — @endif
      </td>
      <td>
        @if($it->svg_path)
          <a target="_blank" href="{{ url('files/'.$it->svg_path) }}">view</a>
        @endif
      </td>
      <td>
        <form method="post" action="{{ route('admin.decoration.destroy', $it) }}"
              onsubmit="return confirm('Delete this template?');">
          @csrf @method('DELETE')
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  @endforeach
  </tbody>
</table>


{{ $items->links() }}

{{-- Small helper to auto-fill max chars --}}
<script>
  document.getElementById('slot_key')?.addEventListener('change', function(){
    const f = document.getElementById('max_chars');
    if (this.value === 'name'   && !f.value) f.value = 12;
    if (this.value === 'number' && !f.value) f.value = 3;
  });
</script>
@endsection
