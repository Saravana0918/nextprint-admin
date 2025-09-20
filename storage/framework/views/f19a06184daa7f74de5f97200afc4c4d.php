
<?php $__env->startSection('title','Products'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  .preview-img{
    width:50px;height:50px;object-fit:cover;border-radius:6px;background:#f3f4f6
  }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">Products</h3>
  <div class="d-flex gap-2">
    <a href="<?php echo e(route('admin.decoration.index')); ?>" class="btn btn-outline-primary">Manage Decoration Areas</a>
    <a href="<?php echo e(route('admin.print-methods.index')); ?>" class="btn btn-outline-secondary">Print Methods</a>
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
      <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td><?php echo e($p->id); ?></td>

          
          <td>
            
      <?php $src = $p->preview_src ?? null; ?>
      <?php if($src): ?>
        <img src="<?php echo e($src); ?>" alt="preview"
             style="width:50px;height:50px;object-fit:contain"
             loading="lazy" referrerpolicy="no-referrer"
             onerror="this.onerror=null; this.src='<?php echo e(asset('images/placeholder.png')); ?>'"
      <?php else: ?>
        <img src="<?php echo e(asset('images/placeholder.png')); ?>" alt="preview"
             style="width:50px;height:50px;object-fit:contain">
      <?php endif; ?>
    </td>

    <td><?php echo e($p->name); ?></td>
    <td>₹<?php echo e($p->min_price); ?></td>
    <td><?php echo e($p->vendor); ?></td>
    <td><?php echo e($p->status); ?></td>
    <td>
    <?php if(!empty($p->methods)): ?>
      <?php $__currentLoopData = explode(',', $p->methods); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <span class="badge bg-secondary" style="margin-right:4px;">
          <?php echo e(trim($m)); ?>

        </span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php else: ?>
      <span class="text-muted">—</span>
    <?php endif; ?>
  </td>


    <td class="text-nowrap">
      <a href="<?php echo e(route('admin.products.edit', $p->id)); ?>" class="btn btn-warning btn-sm">Edit</a>

      <form action="<?php echo e(route('admin.products.destroy', $p->id)); ?>"
            method="POST" class="d-inline"
            onsubmit="return confirm('Delete this product?');">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button class="btn btn-danger btn-sm">Delete</button>
      </form>

      <a href="<?php echo e(route('admin.products.decoration', $p->id)); ?>" class="btn btn-primary btn-sm">
        Decoration Area (Front)
      </a>
    </td>
  </tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody>

  </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH E:\Nextprint-designing-tool\backend-app\resources\views/admin/products/index.blade.php ENDPATH**/ ?>