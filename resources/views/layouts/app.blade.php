<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'NextPrint Admin')</title>

  <!-- Bootstrap (or your CSS) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  @stack('styles')
</head>
<body class="bg-light">

<nav class="navbar navbar-light bg-white border-bottom mb-4">
  <div class="container">
    <a class="navbar-brand" href="{{ route('admin.products') }}">NextPrint Admin</a>
    <div class="ms-auto">
      <a href="{{ route('admin.print-methods.index') }}" class="btn btn-sm btn-outline-primary">Print Methods</a>
      <a href="{{ route('admin.decoration.index') }}" class="btn btn-sm btn-outline-secondary">Decoration Areas</a>
    </div>
  </div>
</nav>

<main class="container mb-5">
  @yield('content')
</main>

<footer class="text-center text-muted py-3">
  © {{ date('Y') }} NextPrint
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
