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
<!-- Preview Upload Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="previewUploadForm" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Product Preview Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="file" id="preview_image" name="preview_image" accept="image/*" class="form-control mb-2" />
        <div class="text-center">
          <img id="currentPreview" src="" alt="current preview" class="preview-img" style="display:none; max-width:100%; margin-top:10px;" />
        </div>
        <div id="previewAlert" class="alert d-none mt-2" role="alert"></div>
      </div>

      <div class="modal-footer">
        <button type="button" id="deletePreviewBtn" class="btn btn-danger" style="display:none;">Delete Preview</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

  let activeProductId = null;
  const previewModalEl = document.getElementById('previewModal');
  const currentPreview = document.getElementById('currentPreview');
  const previewForm = document.getElementById('previewUploadForm');
  const previewFileInput = document.getElementById('preview_image');
  const deleteBtn = document.getElementById('deletePreviewBtn');
  const previewAlert = document.getElementById('previewAlert');

  // bootstrap modal instance
  const bsModal = new bootstrap.Modal(previewModalEl);

  document.querySelectorAll('.btn-settings').forEach(btn => {
    btn.addEventListener('click', function () {
      activeProductId = this.dataset.productId;
      const url = this.dataset.productPreview || '';
      previewFileInput.value = '';
      previewAlert.classList.add('d-none');
      if (url) {
        currentPreview.src = url;
        currentPreview.style.display = 'inline-block';
        deleteBtn.style.display = 'inline-block';
      } else {
        currentPreview.style.display = 'none';
        deleteBtn.style.display = 'none';
      }
      bsModal.show();
    });
  });

  previewForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!activeProductId) return alert('No product selected');

    const file = previewFileInput.files && previewFileInput.files[0];
    if (!file) {
      showAlert('Please choose an image file.', 'danger');
      return;
    }

    const fd = new FormData();
    fd.append('preview_image', file);

    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    try {
      const resp = await fetch(`/admin/products/${activeProductId}/preview`, {
        method: 'POST',
        body: fd,
        headers: { 'X-CSRF-TOKEN': token },
        credentials: 'same-origin'
      });
      const json = await resp.json();
      if (resp.ok && json.success) {
        showAlert('Uploaded successfully', 'success');
        // update preview image in table row (if present)
        const btn = document.querySelector('.btn-settings[data-product-id="'+activeProductId+'"]');
        if (btn) btn.dataset.productPreview = json.url || '';
        // update preview thumbnail in table if exists
        const imgCell = document.querySelector('button.btn-settings[data-product-id="'+activeProductId+'"]').closest('td').parentNode.querySelector('td img');
        if (imgCell && json.url) {
          imgCell.src = json.url;
        }
        // keep the modal open briefly then reload to be safe (or just update DOM)
        setTimeout(()=> location.reload(), 700);
      } else {
        let msg = 'Upload failed';
        if (json && json.errors) msg = json.errors.join('; ');
        if (json && json.message) msg = json.message;
        showAlert(msg, 'danger');
      }
    } catch (err) {
      console.error(err);
      showAlert('Upload error', 'danger');
    }
  });

  deleteBtn.addEventListener('click', async function () {
    if (!activeProductId) return;
    if (!confirm('Delete preview for this product?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    try {
      const resp = await fetch(`/admin/products/${activeProductId}/preview`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' },
        credentials: 'same-origin'
      });
      const json = await resp.json();
      if (resp.ok && json.success) {
        showAlert('Removed', 'success');
        // update DOM
        const btn = document.querySelector('.btn-settings[data-product-id="'+activeProductId+'"]');
        if (btn) btn.dataset.productPreview = '';
        // refresh page to reflect changes
        setTimeout(()=> location.reload(), 600);
      } else {
        showAlert('Delete failed', 'danger');
      }
    } catch (err) {
      console.error(err);
      showAlert('Delete error', 'danger');
    }
  });

  function showAlert(message, type='info'){
    previewAlert.classList.remove('d-none','alert-success','alert-danger','alert-info');
    previewAlert.classList.add('alert-' + (type === 'danger' ? 'danger' : (type === 'success' ? 'success' : 'info')));
    previewAlert.textContent = message;
  }

});
</script>
@endpush

@endsection
