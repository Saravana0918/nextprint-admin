


<?php $__env->startSection('content'); ?>
<h3>Manage decoration areas</h3>

<?php if(session('ok')): ?> <div class="alert alert-success"><?php echo e(session('ok')); ?></div> <?php endif; ?>
<?php if($errors->any()): ?>
  <div class="alert alert-danger mb-3">
    <ul class="mb-0">
      <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?php echo e(route('admin.decoration.store')); ?>" enctype="multipart/form-data"
      class="card p-3 mb-3" style="max-width:900px">
  <?php echo csrf_field(); ?>
  <div class="row g-2 align-items-end">

    <div class="col-3">
      <label class="form-label">Category</label>
      <select name="category" class="form-control">
        <option value="without_bleed" <?php if(old('category')==='without_bleed'): echo 'selected'; endif; ?>>Without bleed Mark</option>
        <option value="custom"        <?php if(old('category')==='custom'): echo 'selected'; endif; ?>>Custom</option>
        <option value="regular"       <?php if(old('category')==='regular'): echo 'selected'; endif; ?>>Regular</option>
      </select>
    </div>

    <div class="col-3">
      <label class="form-label">Name</label>
      <input name="name" class="form-control" placeholder="Front / Heart / A4" value="<?php echo e(old('name')); ?>" required>
    </div>

    <div class="col-2">
      <label class="form-label">Width (mm)</label>
      <input name="width_mm" type="number" class="form-control" value="<?php echo e(old('width_mm')); ?>" required>
    </div>

    <div class="col-2">
      <label class="form-label">Height (mm)</label>
      <input name="height_mm" type="number" class="form-control" value="<?php echo e(old('height_mm')); ?>" required>
    </div>

    <div class="col-2">
      <label class="form-label">Preview (SVG)</label>
      <input type="file" name="svg" accept="image/svg+xml" class="form-control">
    </div>

    
    <div class="col-3">
      <label class="form-label">Type (Usage)</label>
      <select name="slot_key" id="slot_key" class="form-control">
        <option value=""        <?php if(old('slot_key')===''): echo 'selected'; endif; ?>>— Generic —</option>
        <option value="name"    <?php if(old('slot_key')==='name'): echo 'selected'; endif; ?>>Name</option>
        <option value="number"  <?php if(old('slot_key')==='number'): echo 'selected'; endif; ?>>Number</option>
      </select>
      <div class="form-text">Choose “Name” or “Number” if this template is for text placement.</div>
    </div>

    <div class="col-2">
      <label class="form-label">Max chars (optional)</label>
      <input name="max_chars" id="max_chars" type="number" min="1" step="1"
             class="form-control" value="<?php echo e(old('max_chars')); ?>" placeholder="e.g. 12">
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
  <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <tr>
      <td><?php echo e(ucfirst(str_replace('_',' ', $it->category))); ?></td>
      <td><?php echo e($it->name); ?></td>
      <td><?php echo e($it->width_mm); ?> × <?php echo e($it->height_mm); ?></td>
      <td>
        <?php if($it->slot_key === 'name'): ?>  <span class="badge bg-secondary">Name</span>
        <?php elseif($it->slot_key === 'number'): ?> <span class="badge bg-secondary">Number</span>
        <?php else: ?> — <?php endif; ?>
      </td>
      <td>
        <?php if($it->svg_path): ?>
          <a target="_blank" href="<?php echo e(url('files/'.$it->svg_path)); ?>">view</a>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" action="<?php echo e(route('admin.decoration.destroy', $it)); ?>"
              onsubmit="return confirm('Delete this template?');">
          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
          <button class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </tbody>
</table>


<?php echo e($items->links()); ?>



<script>
  document.getElementById('slot_key')?.addEventListener('change', function(){
    const f = document.getElementById('max_chars');
    if (this.value === 'name'   && !f.value) f.value = 12;
    if (this.value === 'number' && !f.value) f.value = 3;
  });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH E:\Nextprint-designing-tool\backend-app\resources\views/admin/decoration/index.blade.php ENDPATH**/ ?>