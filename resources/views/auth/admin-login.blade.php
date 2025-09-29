{{-- resources/views/auth/admin-login.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; background:#f7fafc; padding:40px;}
    .box{max-width:520px;margin:40px auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,0.06);}
    .muted{color:#666;font-size:14px}
    .btn{display:inline-block;padding:8px 12px;border-radius:6px;background:#1d72b8;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <div class="box">
    <h2>Admin Login (temporary)</h2>
    <p class="muted">This is a temporary login placeholder so unauthenticated redirects don't 404. Replace with your real admin login page or auth controller.</p>

    {{-- optionally show link to real login if you have one --}}
    <p>
      @if (Route::has('admin.login'))
        <!-- already on admin.login -->
      @endif
    </p>

    <p style="margin-top:18px;">
      <a class="btn" href="{{ url('/') }}">Go to Home</a>
      &nbsp;
      <a class="btn" href="{{ url()->previous() }}">Back</a>
    </p>

    <hr>
    <div class="muted">Developer note: implement real admin auth or change Authenticate::redirectTo()</div>
  </div>
</body>
</html>
