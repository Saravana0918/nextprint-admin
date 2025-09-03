<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $__env->yieldContent('title', 'Admin'); ?></title>

  <link rel="stylesheet" href="<?php echo e(asset('syndron/assets/css/bootstrap.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('syndron/assets/css/icons.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('syndron/assets/css/style.css')); ?>">

  
  <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#">NextPrint Admin</a>
    </div>
  </nav>

  <main class="container-fluid py-4">
    <?php echo $__env->yieldContent('content'); ?>
  </main>

  <footer class="text-center text-muted py-3 small">
    © <?php echo e(date('Y')); ?> NextPrint
  </footer>

  
  <script src="<?php echo e(asset('syndron/assets/js/bootstrap.bundle.min.js')); ?>"></script>

  
  
  

  
  <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH E:\Nextprint-designing-tool\backend-app\resources\views/layouts/admin.blade.php ENDPATH**/ ?>