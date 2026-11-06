<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store->name }} is closed for now</title>
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
<div class="container text-center" style="max-width:520px">
    <i class="bi bi-shop-window display-3 text-secondary"></i>
    <h1 class="h3 fw-bold mt-3">{{ $store->name }} is closed for now</h1>
    <p class="text-muted">The shop is temporarily unavailable. Please check back soon.</p>
    @if ($store->phone)<p class="mb-0">Questions? Call <a href="tel:{{ $store->phone }}">{{ $store->phone }}</a></p>@endif
</div>
</body>
</html>
