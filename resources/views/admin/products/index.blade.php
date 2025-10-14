@extends('layouts.admin')
@section('title','Products')

@push('styles')
<style>
  .preview-img{
    width:50px;height:50px;object-fit:cover;border-radius:6px;background:#f3f4f6
  }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">Products</h3>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.decoration.index') }}" class="btn btn-outline-primary">Manage Decoration Areas</a>
    <a href="{{ route('admin.print-methods.index') }}" class="btn btn-outline-secondary">Print Methods</a>
  </div>
</div>

<div class="table-responsive">
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Preview</th>
        <th>Name</th>
        <th>Price</th>
        <th>Vendor</th>
        <th>Status</th>
        <th>Methods</th>
        <th>Action</th>
      </tr>
    </thead>

    <tbody>
      @foreach($rows as $p)
        <tr>
          <td>{{ $p->id }}</td>

          {{-- Preview --}}
          <td>
  @php $src = $p->preview_src ?? null; @endphp
  @if($src)
    <img src="{{ $src }}" alt="preview"
         style="width:50px;height:50px;object-fit:contain"
         loading="lazy" referrerpolicy="no-referrer"
         onerror="this.onerror=null; this.src='{{ asset('images/placeholder.png') }}'">
  @else
    <img src="{{ asset('images/placeholder.png') }}" alt="preview"
         style="width:50px;height:50px;object-fit:contain">
  @endif
</td>


    <td>{{ $p->name }}</td>
    <td>₹{{ $p->min_price }}</td>
    <td>{{ $p->vendor }}</td>
    <td>{{ $p->status }}</td>
    <td>
    @if(!empty($p->methods))
      @foreach(explode(',', $p->methods) as $m)
        <span class="badge bg-secondary" style="margin-right:4px;">
          {{ trim($m) }}
        </span>
      @endforeach
    @else
      <span class="text-muted">—</span>
    @endif
  </td>


    <td class="text-nowrap">
      <a href="{{ route('admin.products.edit', $p->id) }}" class="btn btn-warning btn-sm">Edit</a>

      <form action="{{ route('admin.products.destroy', $p->id) }}"
            method="POST" class="d-inline"
            onsubmit="return confirm('Delete this product?');">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>

      <a href="{{ route('admin.products.decoration', $p->id) }}" class="btn btn-primary btn-sm">
        Decoration Area (Front)
      </a>
      <button type="button" class="btn btn-secondary btn-sm ms-1 btn-settings"
        data-product-id="{{ $p->id }}"
        data-product-preview="{{ $p->preview_src ?? '' }}">
        Settings
      </button>
    </td>
  </tr>
@endforeach
</tbody>

  </table>
  <div class="mt-3">
  {{ $rows->links() }}
</div>
</div>
<!-- Product Preview Modal (put near end of blade) -->
<div class="modal fade" id="productPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="product-preview-form">
        <div class="modal-header">
          <h5 class="modal-title">Product Preview Settings</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <input type="file" id="preview-file" name="preview_image" accept="image/*" class="form-control mb-3">
          <img id="preview-thumb" src="" style="max-width:120px; max-height:120px; object-fit:contain; display:none; margin:auto;">
          <div id="preview-alert" class="alert alert-danger mt-3" style="display:none;"></div>
        </div>
        <div class="modal-footer">
          <button type="button" id="delete-preview-btn" class="btn btn-danger">Delete Preview</button>
          <button type="submit" id="upload-preview-btn" class="btn btn-primary">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  const modalEl = document.getElementById('productPreviewModal');
  const bsModal = new bootstrap.Modal(modalEl);
  let currentProductId = null;

  // Open modal when 'Settings' clicked
  document.querySelectorAll('.btn-settings').forEach(btn=>{
    btn.addEventListener('click', function(){
      currentProductId = this.dataset.productId;
      const preview = this.dataset.productPreview || '';
      document.getElementById('preview-alert').style.display = 'none';
      const thumb = document.getElementById('preview-thumb');
      if(preview){
        thumb.src = preview;
        thumb.style.display = 'block';
      } else {
        thumb.style.display = 'none';
      }
      document.getElementById('preview-file').value = '';
      bsModal.show();
    });
  });

  // preview file read
  document.getElementById('preview-file').addEventListener('change', function(e){
    const f = e.target.files && e.target.files[0];
    if(!f) return;
    const reader = new FileReader();
    reader.onload = function(ev){
      const thumb = document.getElementById('preview-thumb');
      thumb.src = ev.target.result;
      thumb.style.display = 'block';
    };
    reader.readAsDataURL(f);
  });

  // Upload form submit
  document.getElementById('product-preview-form').addEventListener('submit', async function(e){
    e.preventDefault();
    if(!currentProductId) return;

    const fileInput = document.getElementById('preview-file');
    if(!fileInput.files || !fileInput.files[0]){
      showError('Please choose an image first.');
      return;
    }
    const fd = new FormData();
    fd.append('_token', '{{ csrf_token() }}');
    fd.append('preview_image', fileInput.files[0]);

    const url = '/admin/products/' + currentProductId + '/preview';
    try {
      const resp = await fetch(url, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
      });
      const json = await resp.json().catch(()=>({}));
      if(!resp.ok){
        const msg = (json && json.message) ? json.message : 'Upload failed';
        showError(msg);
        return;
      }
      // success: update thumbnail in row
      const newUrl = json.url || '';
      const settingBtn = document.querySelector('.btn-settings[data-product-id="'+currentProductId+'"]');
      if (settingBtn) {
        settingBtn.dataset.productPreview = newUrl;
        const tr = settingBtn.closest('tr');
        const rowImg = tr ? tr.querySelector('td:nth-child(2) img') : null;
        if (rowImg) rowImg.src = newUrl;
      }
      bsModal.hide();
    } catch (err) {
      showError('Upload failed (network)');
    }
  });

  // Delete preview
  document.getElementById('delete-preview-btn').addEventListener('click', async function(){
    if(!currentProductId) return;
    if(!confirm('Delete preview for this product?')) return;
    const url = '/admin/products/' + currentProductId + '/preview';
    try {
      const resp = await fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        credentials: 'same-origin'
      });
      if(!resp.ok) { showError('Delete failed'); return; }
      // clear local
      const settingBtn = document.querySelector('.btn-settings[data-product-id="'+currentProductId+'"]');
      if(settingBtn) settingBtn.dataset.productPreview = '';
      const rowImg = document.querySelector('button[data-product-id="'+currentProductId+'"]').closest('td').parentNode.querySelector('td img');
      if(rowImg) rowImg.src = '{{ asset("images/placeholder.png") }}';
      bsModal.hide();
    } catch(e){
      showError('Delete failed');
    }
  });

  function showError(msg){
    const a = document.getElementById('preview-alert');
    a.innerText = msg;
    a.style.display = 'block';
  }
});
</script>
@endpush

@endsection
