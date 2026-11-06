@php
    $user = auth('web')->user();
    $toVerify = \App\Models\Order::where('payment_status', 'pending_verification')->whereNot('status', 'cancelled')->count();
    $pending = \App\Models\Order::where('status', 'pending')->count();
    $sub = $store->currentSubscription;
    $nav = [
        ['Overview', null, [
            ['Dashboard', 'admin.dashboard', 'speedometer2', null, null],
        ]],
        ['Catalog', null, [
            ['Products', 'admin.products.index', 'box-seam', 'products.view', null],
            ['Categories', 'admin.categories.index', 'diagram-3', 'products.manage', null],
            ['Brands', 'admin.brands.index', 'award', 'products.manage', null],
            ['Reviews', 'admin.reviews.index', 'star', 'reviews.manage', null],
        ]],
        ['Inventory', 'inventory', [
            ['Stock overview', 'admin.inventory.index', 'boxes', 'inventory.view', null],
            ['Stock movements', 'admin.inventory.movements', 'arrow-left-right', 'inventory.view', null],
            ['Warehouses', 'admin.warehouses.index', 'buildings', 'inventory.manage', null],
            ['Purchase orders', 'admin.purchase-orders.index', 'truck', 'purchases.manage', null],
            ['Suppliers', 'admin.suppliers.index', 'person-badge', 'purchases.manage', null],
        ]],
        ['Sales', null, [
            ['Orders', 'admin.orders.index', 'receipt', 'orders.view', $pending ?: null],
            ['Verify payments', 'admin.orders.index', 'patch-check', 'payments.verify', $toVerify ?: null, ['payment' => 'pending_verification']],
            ['Customers', 'admin.customers.index', 'people', 'customers.view', null],
            ['Abandoned carts', 'admin.carts.index', 'cart-x', 'orders.view', null],
        ]],
        ['Marketing', null, [
            ['Coupons', 'admin.coupons.index', 'ticket-perforated', 'coupons.manage', null],
            ['Banners', 'admin.banners.index', 'images', 'content.manage', null],
            ['Pages', 'admin.pages.index', 'file-earmark-text', 'content.manage', null],
        ]],
        ['Finance', 'accounting', [
            ['Invoices', 'admin.finance.invoices', 'file-earmark-ruled', 'finance.view', null],
            ['Payments', 'admin.finance.payments', 'credit-card', 'finance.view', null],
            ['Credit notes', 'admin.finance.credit-notes', 'arrow-counterclockwise', 'finance.view', null],
            ['Expenses', 'admin.finance.expenses', 'wallet2', 'expenses.manage', null],
            ['Accounts & journal', 'admin.finance.accounts', 'journal-text', 'finance.view', null],
        ]],
        ['Reports', null, [
            ['Reports', 'admin.reports', 'bar-chart-line', 'finance.view', null],
        ]],
        ['Settings', null, [
            ['Store settings', 'admin.settings.show', 'gear', 'settings.manage', null],
            ['Staff & roles', 'admin.staff.index', 'person-gear', 'staff.manage', null],
            ['Plan & billing', 'admin.billing', 'credit-card-2-front', null, null],
        ]],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ ($title ?? 'Dashboard').' · '.$store->name }}</title>
    <link rel="icon" href="/favicon.svg">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/admin.css?v=1">
    @stack('head')
    <script defer src="/vendor/alpine/alpine.min.js"></script>
</head>
<body x-data="{ side: false }">
<div class="admin-shell">
    <aside class="admin-side" :class="side && 'open'">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <span class="logo"><i class="bi bi-bag-fill"></i></span>
            <span>{{ \Illuminate\Support\Str::limit($store->name, 22) }}<small>Store admin</small></span>
        </a>
        @foreach ($nav as [$group, $feature, $links])
            @continue($feature && ! $store->hasFeature($feature))
            @php
                $visible = collect($links)->filter(fn ($l) => ! $l[3] || $user->is_super_admin || $user->canInStore($store, $l[3]));
            @endphp
            @continue($visible->isEmpty())
            <div class="side-group">{{ $group }}</div>
            @foreach ($visible as $link)
                @php
                    $params = $link[5] ?? [];
                    $active = request()->routeIs($link[1].'*') && ($params === [] ? ! request('payment') : request('payment') === ($params['payment'] ?? null));
                    $active = $active || (str_ends_with($link[1], '.index') && request()->routeIs(substr($link[1], 0, -6).'.*') && $params === [] && ! request('payment'));
                @endphp
                <a href="{{ route($link[1], $params) }}" @class(['side-link', 'active' => $active])>
                    <i class="bi bi-{{ $link[2] }}"></i>{{ $link[0] }}
                    @if ($link[4])<span class="count">{{ $link[4] }}</span>@endif
                </a>
            @endforeach
        @endforeach
    </aside>

    <div class="admin-main">
        <header class="admin-top">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light d-lg-none" @click="side = !side" aria-label="Menu"><i class="bi bi-list"></i></button>
                <a href="{{ $store->url('/') }}" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-up-right"></i> View store</a>
                @if ($user->is_super_admin)
                    <a href="{{ route('super.dashboard') }}" class="btn btn-sm btn-outline-dark"><i class="bi bi-shield-lock"></i> Platform</a>
                @endif
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <span class="rounded-circle bg-dark text-white d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;font-size:.75rem">{{ $user->initials() }}</span>
                    <span class="d-none d-sm-inline">{{ $user->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-item-text small text-muted">{{ $user->email }}</li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Sign out</button></form></li>
                </ul>
            </div>
        </header>

        <div class="admin-content">
            @if (! $store->isActive())
                <div class="alert alert-danger"><i class="bi bi-exclamation-octagon"></i> Your store is <strong>suspended</strong>{{ $store->suspended_reason ? ': '.$store->suspended_reason : '' }}. Shoppers cannot see it. <a href="{{ route('admin.billing') }}" class="alert-link">See billing</a></div>
            @elseif ($sub && $sub->ends_at->isPast())
                <div class="alert alert-warning"><i class="bi bi-clock-history"></i> Your plan ended on {{ $sub->ends_at->format('j M Y') }}. Please renew to keep your store online. <a href="{{ route('admin.billing') }}" class="alert-link">See billing</a></div>
            @endif
            @if (session('status'))<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
            @if (session('error'))<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
            @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </div>
</div>
<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
