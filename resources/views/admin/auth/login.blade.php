<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>Sign in · {{ $heading }}</title>
    <link rel="icon" href="/favicon.svg">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/admin.css?v=1">
</head>
<body>
<div class="login-wrap">
    <form method="POST" action="{{ $action }}" class="login-card">
        @csrf
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-2" style="width:48px;height:48px;background:#f5c542"><i class="bi bi-{{ $icon }} fs-4"></i></div>
            <h1 class="h4 fw-bold mb-0">{{ $heading }}</h1>
            <div class="text-muted small">{{ $subheading }}</div>
        </div>
        @if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        <div class="mb-3"><label class="form-label" for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username"></div>
        <div class="mb-3"><label class="form-label" for="password">Password</label><input id="password" type="password" name="password" class="form-control" required autocomplete="current-password"></div>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label small" for="remember">Remember me</label></div>
        <button class="btn btn-brand w-100 py-2">Sign in</button>
    </form>
</div>
</body>
</html>
