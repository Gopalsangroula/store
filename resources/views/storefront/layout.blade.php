@php
    $theme = $store->setting('theme', 'midnight');
    $accent = $store->setting('accent_color', '#f5c542');
    $currencies = app(\App\Services\CurrencyService::class)->enabled();
    $currentCurrency = app(\App\Services\CurrencyService::class)->selected(request());
    $footerPages = \App\Models\Page::where('is_published', true)->where('in_footer', true)->orderBy('id')->get(['title', 'slug']);
    $flags = ['NPR' => '🇳🇵', 'INR' => '🇮🇳', 'USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'AUD' => '🇦🇺'];
@endphp
<!doctype html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim(($title ?? '') ? $title.' - '.$store->name : $store->name.' - '.$store->setting('tagline', 'Online store')) }}</title>
    <meta name="description" content="{{ $description ?? $store->setting('tagline') }}">
    <meta property="og:site_name" content="{{ $store->name }}">
    <meta property="og:title" content="{{ $title ?? $store->name }}">
    @isset($ogImage)<meta property="og:image" content="{{ url($ogImage) }}">@endisset
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ $store->logoUrl() ?? '/favicon.svg' }}">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/css/storefront.css?v=2">
    <style>:root { --accent: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#f5c542' }}; }</style>
    @stack('head')
    <script defer src="/vendor/alpine/alpine.min.js"></script>
</head>
<body x-data="shop()" @toast.window="push($event.detail)">

<header class="site-header">
    <div class="container inner d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-2">
            <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Menu"><i class="bi bi-list"></i></button>
            <a href="{{ route('shop.home') }}" class="brand" aria-label="{{ $store->name }} home">
                @if ($store->logoUrl())
                    <img src="{{ $store->logoUrl() }}" alt="{{ $store->name }}">
                @else
                    <span class="brand-name">{{ $store->name }}</span>
                    <span class="brand-sub">{{ $store->primaryDomain() }}</span>
                @endif
            </a>
        </div>

        <nav class="main-nav d-none d-lg-flex">
            <a href="{{ route('shop.shop') }}" @class(['active' => request()->routeIs('shop.shop', 'shop.category', 'shop.product')])>Shop</a>
            <a href="{{ route('shop.track') }}" @class(['active' => request()->routeIs('shop.track')])>Track</a>
            <a href="{{ route('shop.page', 'about-us') }}" @class(['active' => request()->is('about-us')])>About</a>
        </nav>

        <div class="d-flex align-items-center gap-1 gap-md-2">
            <form action="{{ route('shop.shop') }}" class="search d-none d-md-flex" role="search">
                <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search products..." aria-label="Search products">
                <button type="submit" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>
            <a href="{{ route('shop.cart') }}" class="icon-btn" aria-label="Cart">
                <i class="bi bi-cart3"></i>
                <span class="cart-badge" x-show="count > 0" x-text="count" x-cloak>{{ $cartCount ?? 0 }}</span>
            </a>
            @if ($currencies->count() > 1)
                <div class="dropdown">
                    <button class="currency-btn dropdown-toggle" data-bs-toggle="dropdown" aria-label="Currency">{{ substr($currentCurrency, 0, 2) === 'NP' ? 'NP' : $currentCurrency }}</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach ($currencies as $currency)
                            <li><a class="dropdown-item @if($currency->code === $currentCurrency) active @endif" href="{{ route('shop.currency', $currency->code) }}">{{ $flags[$currency->code] ?? '' }} {{ $currency->code }} · {{ $currency->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <a href="{{ auth('customer')->check() ? route('shop.account') : route('shop.account.login') }}" class="avatar-btn" aria-label="Account"><i class="bi bi-person-fill"></i></a>
        </div>
    </div>
</header>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav">
    <div class="offcanvas-header"><strong>{{ $store->name }}</strong><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body">
        <form action="{{ route('shop.shop') }}" class="d-flex mb-3"><input name="q" class="form-control me-2" placeholder="Search products..."><button class="btn btn-ink"><i class="bi bi-search"></i></button></form>
        <div class="list-group list-group-flush">
            <a class="list-group-item" href="{{ route('shop.home') }}">Home</a>
            <a class="list-group-item" href="{{ route('shop.shop') }}">Shop</a>
            <a class="list-group-item" href="{{ route('shop.track') }}">Track order</a>
            @foreach ($footerPages as $page)
                <a class="list-group-item" href="{{ route('shop.page', $page->slug) }}">{{ $page->title }}</a>
            @endforeach
        </div>
    </div>
</div>

<div class="toast-stack" aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div class="toast-msg" :class="t.error && 'error'"><i class="bi" :class="t.error ? 'bi-exclamation-circle' : 'bi-check-circle'"></i><span x-text="t.message"></span></div>
    </template>
</div>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a href="{{ route('shop.home') }}" class="brand mb-3">
                    @if ($store->logoUrl())<img src="{{ $store->logoUrl() }}" alt="{{ $store->name }}" style="max-height:64px">@else<span class="brand-name fs-2">{{ $store->name }}</span><span class="brand-sub">{{ $store->primaryDomain() }}</span>@endif
                </a>
                <p class="text-muted mt-3 mb-3" style="max-width:26rem">{{ $store->setting('tagline') }}</p>
                <div class="social">
                    @foreach (['facebook' => 'facebook', 'tiktok' => 'tiktok', 'instagram' => 'instagram', 'youtube' => 'youtube'] as $key => $icon)
                        @if ($link = $store->setting('social_'.$key))
                            <a href="{{ $link }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($key) }}"><i class="bi bi-{{ $icon }}"></i></a>
                        @endif
                    @endforeach
                    @if ($store->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $store->whatsapp) }}" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <h6>Quick Links</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('shop.shop') }}">Shop all</a></li>
                    <li><a href="{{ route('shop.track') }}">Track Your Order</a></li>
                    @foreach ($footerPages as $page)
                        <li><a href="{{ route('shop.page', $page->slug) }}">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h6>Contact Us</h6>
                <ul class="list-unstyled small">
                    @if ($store->address)<li class="mb-2 d-flex gap-2"><i class="bi bi-geo-alt"></i>{{ $store->address }}</li>@endif
                    @if ($store->email)<li class="mb-2 d-flex gap-2"><i class="bi bi-envelope"></i><a href="mailto:{{ $store->email }}">{{ $store->email }}</a></li>@endif
                    @if ($store->phone)<li class="mb-2 d-flex gap-2"><i class="bi bi-telephone"></i><a href="tel:{{ $store->phone }}">{{ $store->phone }}</a></li>@endif
                    @if ($hours = $store->setting('business_hours'))<li class="mb-2 text-muted ps-4">{{ $hours }}</li>@endif
                </ul>
                <div class="pay-strip mt-3">
                    <span><i class="bi bi-wallet2" style="color:#5c2d91"></i>Khalti</span>
                    <span><i class="bi bi-qr-code"></i>QR</span>
                    <span><i class="bi bi-cash-coin"></i>Cash on delivery</span>
                </div>
            </div>
        </div>
        <hr class="my-4">
        <div class="d-flex flex-wrap justify-content-between small text-muted gap-2">
            <span>&copy; {{ now()->year }} {{ $store->name }}. All rights reserved.</span>
            <span>Powered by <a href="https://appharu.com" target="_blank" rel="noopener">Appharu</a></span>
        </div>
    </div>
</footer>

@if ($store->whatsapp)
    <a class="whatsapp-float" href="https://wa.me/{{ preg_replace('/\D/', '', $store->whatsapp) }}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp"></i></a>
@endif

<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
    function shop() {
        return {
            count: {{ (int) ($cartCount ?? 0) }},
            toasts: [],
            init() {
                @if (session('status')) this.push({ message: @json(session('status')) }); @endif
                @if (session('error')) this.push({ message: @json(session('error')), error: true }); @endif
                @if ($errors->any() && ! isset($inlineErrors)) this.push({ message: @json($errors->first()), error: true }); @endif
            },
            push(t) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, ...t });
                setTimeout(() => this.toasts = this.toasts.filter(x => x.id !== id), t.error ? 6000 : 3500);
            },
            async addToCart(variantId, quantity = 1) {
                const res = await fetch(@json(route('shop.cart.add')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ variant_id: variantId, quantity })
                });
                const data = await res.json().catch(() => ({ ok: false, message: 'Something went wrong. Please try again.' }));
                if (data.count !== undefined) this.count = data.count;
                this.push({ message: data.message, error: !data.ok });
            }
        };
    }
</script>
@stack('scripts')
</body>
</html>
