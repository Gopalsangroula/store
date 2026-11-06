@php
    $nav = [
        ['Dashboard', 'super.dashboard', 'speedometer2'],
        ['Stores', 'super.stores.index', 'shop'],
        ['Plans', 'super.plans.index', 'layers'],
        ['Billing', 'super.billing.index', 'receipt'],
        ['Activity', 'super.activity', 'clock-history'],
        ['Settings', 'super.settings', 'gear'],
    ];
    $unpaid = \App\Models\SubscriptionInvoice::where('status', 'unpaid')->where('due_on', '<=', now())->count();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ ($title ?? 'Platform').' · '.config('platform.name') }}</title>
    <link rel="icon" href="/favicon.svg">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/admin.css?v=1">
    <style>:root { --side: #0e1630; --side-active: #7cc4ff; } .admin-side .brand .logo { background: #7cc4ff; }</style>
    <script defer src="/vendor/alpine/alpine.min.js"></script>
</head>
<body x-data="{ side: false }">
<div class="admin-shell">
    <aside class="admin-side" :class="side && 'open'">
        <a href="{{ route('super.dashboard') }}" class="brand"><span class="logo"><i class="bi bi-shield-lock-fill"></i></span><span>{{ config('platform.name') }}<small>Super admin</small></span></a>
        <div class="side-group">Platform</div>
        @foreach ($nav as [$label, $route, $icon])
            <a href="{{ route($route) }}" @class(['side-link', 'active' => request()->routeIs(str_replace('.index', '', $route).'*')])><i class="bi bi-{{ $icon }}"></i>{{ $label }}@if($route === 'super.billing.index' && $unpaid)<span class="count">{{ $unpaid }}</span>@endif</a>
        @endforeach
    </aside>
    <div class="admin-main">
        <header class="admin-top">
            <button class="btn btn-light d-lg-none" @click="side = !side"><i class="bi bi-list"></i></button>
            <span class="small text-muted d-none d-lg-inline">Platform billing is kept separate from every store's own sales and books.</span>
            <div class="dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">{{ auth()->user()->name }}</button>
                <ul class="dropdown-menu dropdown-menu-end"><li><form method="POST" action="{{ route('super.logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Sign out</button></form></li></ul>
            </div>
        </header>
        <div class="admin-content">
            @if (session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
            @if (session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </div>
</div>
<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
