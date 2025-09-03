<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>@yield('title', 'Admin')</title>

  <link rel="stylesheet" href="{{ asset('syndron/assets/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('syndron/assets/css/icons.css') }}">
  <link rel="stylesheet" href="{{ asset('syndron/assets/css/style.css') }}">

  {{-- page-level styles --}}
  @stack('styles')
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#">NextPrint Admin</a>
    </div>
  </nav>

  <main class="container-fluid py-4">
    @yield('content')
  </main>

  <footer class="text-center text-muted py-3 small">
    © {{ date('Y') }} NextPrint
  </footer>

  {{-- vendor scripts --}}
  <script src="{{ asset('syndron/assets/js/bootstrap.bundle.min.js') }}"></script>

  {{-- (optional) if app.js needs jQuery, load it first to avoid console noise --}}
  {{-- <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script> --}}
  {{-- <script src="{{ asset('syndron/assets/js/app.js') }}"></script> --}}

  {{-- page-level scripts (VERY IMPORTANT) --}}
  @stack('scripts')
</body>
</html>
