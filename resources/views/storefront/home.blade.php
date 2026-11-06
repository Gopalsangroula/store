@extends('storefront.layout')

@php
    $words = array_values(array_filter(array_map('trim', explode(',', $store->setting('hero_highlight', 'today')))));
    $heroImage = $bestSeller?->imageUrl();
    $rating = \App\Models\ProductReview::where('is_approved', true)->avg('rating');
    $reviewCount = \App\Models\ProductReview::where('is_approved', true)->count();
@endphp

@section('content')
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                @if ($eyebrow = $store->setting('hero_eyebrow'))<span class="eyebrow">{{ $eyebrow }}</span>@endif
                <h1>
                    {{ $store->setting('hero_title', 'Shop the things you love') }}
                    <br><span class="highlight" x-data="{ words: @js($words ?: ['today']), i: 0 }" x-init="if (words.length > 1) setInterval(() => i = (i + 1) % words.length, 2200)" x-text="words[i]">{{ $words[0] ?? 'today' }}</span>
                </h1>
                <p class="lead">{{ $store->setting('hero_text') }}</p>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('shop.shop') }}" class="btn btn-accent">Shop <i class="bi bi-arrow-right ms-1"></i></a>
                    <a href="#featured" class="btn btn-ghost-light">View Featured</a>
                </div>
                <div class="trust-row">
                    @if ($note = $store->setting('free_delivery_note'))<span><i class="bi bi-truck"></i>{{ $note }}</span>@endif
                    @if ($note = $store->setting('delivery_note'))<span><i class="bi bi-clock"></i>{{ $note }}</span>@endif
                    @if ($note = $store->setting('warranty_note'))<span><i class="bi bi-shield-check"></i>{{ $note }}</span>@endif
                </div>
            </div>
            <div class="col-lg-5 offset-lg-1">
                <div class="hero-visual">
                    @if ($heroImage)
                        <img src="{{ $heroImage }}" alt="{{ $bestSeller->name }}">
                    @else
                        <i class="bi bi-bag-heart" style="font-size:7rem;color:#0006"></i>
                    @endif
                    @if ($bestSeller)
                        <a href="{{ route('shop.product', $bestSeller->slug) }}" class="float-card top text-decoration-none">
                            <span class="emoji"><i class="bi bi-trophy-fill"></i></span>
                            <span><strong>Our best seller</strong><small>{{ $bestSeller->name }}</small></span>
                        </a>
                    @endif
                    <div class="float-card bottom">
                        @if ($reviewCount > 0)
                            <span class="emoji"><i class="bi bi-star-fill"></i></span>
                            <span><strong>{{ number_format($rating, 1) }} from customers</strong><small>{{ $reviewCount }} {{ \Illuminate\Support\Str::plural('review', $reviewCount) }}</small></span>
                        @else
                            <span class="emoji"><i class="bi bi-shield-lock-fill"></i></span>
                            <span><strong>Secure checkout</strong><small>Khalti · QR · Cash on delivery</small></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if ($banners->isNotEmpty())
<section class="section pb-0" id="featured">
    <div class="container">
        <div class="section-kicker">Featured Products</div>
        <h2 class="section-title mb-1">{{ $store->setting('featured_title', 'Our essentials') }}</h2>
        <p class="text-muted mb-4">{{ $store->setting('featured_text', 'The pieces most customers start with') }}</p>
        <div class="row g-4">
            @foreach ($banners as $banner)
                <div class="col-lg-6">
                    <div class="promo-card {{ $loop->first ? 'dark' : '' }}">
                        @if ($banner->eyebrow)<div class="eyebrow">{{ $banner->eyebrow }}</div>@endif
                        <h3>{{ $banner->title }}</h3>
                        @if ($banner->subtitle)<p>{{ $banner->subtitle }}</p>@endif
                        @if ($banner->link)
                            <div><a href="{{ $banner->link }}" class="btn {{ $loop->first ? 'btn-accent' : 'btn-ink' }} btn-sm mt-2">{{ $banner->button_text ?: 'Shop Now' }}</a></div>
                        @endif
                        @if ($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" alt="" loading="lazy">@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="section" @if ($banners->isEmpty()) id="featured" @endif>
    <div class="container">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
            <div>
                <div class="section-kicker">Shop</div>
                <h2 class="section-title mb-0">All products</h2>
            </div>
            @if ($categories->count() > 1)
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($categories as $category)
                        <a href="{{ route('shop.category', $category->slug) }}" class="category-chip">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="row g-4">
            @forelse ($products as $product)
                <div class="col-sm-6 col-lg-4">@include('storefront.partials.product-card', ['product' => $product])</div>
            @empty
                <div class="col-12"><div class="panel text-center text-muted py-5">Products are coming soon.</div></div>
            @endforelse
        </div>
        @if ($products->count() >= 24)
            <div class="text-center mt-4"><a href="{{ route('shop.shop') }}" class="btn btn-ink">See all products</a></div>
        @endif
    </div>
</section>
@endsection
