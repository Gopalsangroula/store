@extends('storefront.layout', ['title' => $product->seo_title ?: $product->name, 'description' => $product->seo_description ?: $product->short_description, 'ogImage' => $product->imageUrl()])

@php
    $variantData = $product->activeVariants->map(fn ($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'price' => app(\App\Services\CurrencyService::class)->format($v->price, request()),
        'was' => $v->compare_at_price && $v->compare_at_price > $v->price ? app(\App\Services\CurrencyService::class)->format($v->compare_at_price, request()) : null,
        'state' => $v->stockState(),
        'available' => $v->available(),
        'image' => $v->image_path ? $v->imageUrl() : null,
        'swatch' => $v->swatch,
        'sku' => $v->sku,
    ])->values();
    $images = $product->images->map(fn ($i) => $i->url())->values();
@endphp

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name,
    'image' => $images->map(fn ($i) => url($i))->all(), 'description' => strip_tags((string) $product->short_description),
    'sku' => $selected?->sku, 'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
    'offers' => $product->activeVariants->map(fn ($v) => ['@type' => 'Offer', 'price' => (float) $v->price, 'priceCurrency' => $store->base_currency,
        'availability' => $v->stockState() === 'out' ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock', 'sku' => $v->sku])->all(),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="container py-4 py-lg-5" x-data="productPage(@js($variantData), @js($images), {{ $selected?->id ?? 'null' }})">
    <nav aria-label="breadcrumb" class="small mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('shop.home') }}" class="text-decoration-none">Home</a></li>
            @if ($product->category)<li class="breadcrumb-item"><a href="{{ route('shop.category', $product->category->slug) }}" class="text-decoration-none">{{ $product->category->name }}</a></li>@endif
            <li class="breadcrumb-item active">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            <div class="gallery-main mb-3">
                <template x-if="image"><img :src="image" alt="{{ $product->name }}"></template>
                <template x-if="!image"><i class="bi bi-box-seam text-muted" style="font-size:5rem"></i></template>
            </div>
            @if ($images->count() > 1)
                <div class="gallery-thumbs d-flex gap-2 flex-wrap">
                    <template x-for="(img, i) in images" :key="i">
                        <button type="button" :class="image === img && 'active'" @click="image = img"><img :src="img" alt=""></button>
                    </template>
                </div>
            @endif
        </div>

        <div class="col-lg-6">
            @if ($product->badge)<span class="badge rounded-pill text-bg-dark mb-2">{{ $product->badge }}</span>@endif
            <h1 class="fw-bold mb-2">{{ $product->name }}</h1>
            @if ($reviews->isNotEmpty())
                <a href="#reviews" class="small text-decoration-none text-muted"><span class="stars">{{ str_repeat('★', (int) round($rating)) }}{{ str_repeat('☆', 5 - (int) round($rating)) }}</span> {{ number_format($rating, 1) }} ({{ $reviews->count() }})</a>
            @endif

            <div class="d-flex align-items-baseline gap-2 my-3">
                <span class="price-xl" x-text="current.price"></span>
                <span class="text-muted text-decoration-line-through" x-show="current.was" x-text="current.was"></span>
            </div>
            @if ($product->short_description)<p class="text-muted">{{ $product->short_description }}</p>@endif

            @if ($product->activeVariants->count() > 1 || $product->activeVariants->first()?->name)
                <div class="mb-3">
                    <div class="form-label mb-2">Option: <span class="fw-normal" x-text="current.name"></span></div>
                    <div class="d-flex flex-wrap gap-2">
                        <template x-for="v in variants" :key="v.id">
                            <button type="button" class="option-btn" :class="v.id === current.id && 'active'" @click="choose(v)" :disabled="v.state === 'out'" :style="v.state === 'out' && 'opacity:.45'">
                                <span class="dot" x-show="v.swatch" :style="'background:' + v.swatch"></span>
                                <span x-text="v.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            @endif

            <div class="small mb-3">
                <span x-show="current.state === 'in_stock'" class="text-success fw-semibold"><i class="bi bi-check-circle"></i> In stock</span>
                <span x-show="current.state === 'low'" class="text-warning-emphasis fw-semibold"><i class="bi bi-exclamation-circle"></i> Only <span x-text="current.available"></span> left</span>
                <span x-show="current.state === 'preorder'" class="text-primary fw-semibold"><i class="bi bi-clock-history"></i> Available on pre-order</span>
                <span x-show="current.state === 'out'" class="text-danger fw-semibold"><i class="bi bi-x-circle"></i> Sold out</span>
                <span class="text-muted ms-2">SKU <span x-text="current.sku"></span></span>
            </div>

            <form method="POST" action="{{ route('shop.cart.buy') }}" class="d-flex flex-wrap gap-2 align-items-center mb-4">
                @csrf
                <input type="hidden" name="variant_id" :value="current.id">
                <div class="qty">
                    <button type="button" @click="qty = Math.max(1, qty - 1)" aria-label="Less">−</button>
                    <input name="quantity" type="number" min="1" max="20" x-model.number="qty" aria-label="Quantity">
                    <button type="button" @click="qty = Math.min(20, qty + 1)" aria-label="More">+</button>
                </div>
                <button type="button" class="btn btn-outline-dark fw-semibold px-4" style="border-radius:8px" :disabled="current.state === 'out'" @click="addToCart(current.id, qty)"><i class="bi bi-bag-plus me-1"></i> Add to cart</button>
                <button class="btn btn-accent px-4" :disabled="current.state === 'out'" x-text="current.state === 'preorder' ? 'Pre-order now' : 'Buy now'">Buy now</button>
            </form>

            <div class="row g-3 border-top pt-4">
                <div class="col-sm-6 perk"><i class="bi bi-truck"></i><div><strong>Delivery all over Nepal</strong><div class="text-muted small">{{ $store->setting('delivery_note', 'Delivered in 1 to 3 days') }}</div></div></div>
                <div class="col-sm-6 perk"><i class="bi bi-shield-check"></i><div><strong>Genuine product</strong><div class="text-muted small">{{ $store->setting('warranty_note', 'Easy returns') }}</div></div></div>
                <div class="col-sm-6 perk"><i class="bi bi-wallet2"></i><div><strong>Pay your way</strong><div class="text-muted small">Khalti, QR or cash on delivery</div></div></div>
                <div class="col-sm-6 perk"><i class="bi bi-headset"></i><div><strong>Real support</strong><div class="text-muted small">{{ $store->phone ?: 'Message us anytime' }}</div></div></div>
            </div>
        </div>
    </div>

    @if ($product->description)
        <div class="row mt-5"><div class="col-lg-8"><h2 class="h4 fw-bold mb-3">About this product</h2><div class="prose">{!! \App\Support\HtmlSanitizer::clean($product->description) !!}</div></div></div>
    @endif

    <div class="row mt-5" id="reviews">
        <div class="col-lg-7">
            <h2 class="h4 fw-bold mb-3">Reviews</h2>
            @forelse ($reviews as $review)
                <div class="border-bottom py-3">
                    <div class="d-flex justify-content-between"><strong>{{ $review->name }}</strong><span class="text-muted small">{{ $review->created_at->format('j M Y') }}</span></div>
                    <div class="stars small">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                    @if ($review->body)<p class="mb-0 mt-1">{{ $review->body }}</p>@endif
                </div>
            @empty
                <p class="text-muted">No reviews yet. Be the first to share what you think.</p>
            @endforelse
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('shop.product.review', $product->slug) }}" class="panel" x-data="{ rating: 5 }">
                @csrf
                <h3 class="h6 fw-bold">Write a review</h3>
                <div class="mb-2"><label class="form-label">Your name</label><input name="name" class="form-control" required maxlength="80" value="{{ auth('customer')->user()?->name }}"></div>
                <div class="mb-2">
                    <label class="form-label d-block">Rating</label>
                    <input type="hidden" name="rating" :value="rating">
                    <template x-for="n in 5"><button type="button" class="btn btn-link p-0 me-1 fs-4 text-decoration-none stars" @click="rating = n" x-text="n <= rating ? '★' : '☆'" :aria-label="n + ' stars'"></button></template>
                </div>
                <div class="mb-3"><label class="form-label">Review</label><textarea name="body" rows="3" class="form-control" maxlength="1500"></textarea></div>
                <button class="btn btn-ink w-100">Submit review</button>
            </form>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <h2 class="h4 fw-bold mt-5 mb-3">You may also like</h2>
        <div class="row g-4">
            @foreach ($related as $item)
                <div class="col-sm-6 col-lg-3">@include('storefront.partials.product-card', ['product' => $item])</div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function productPage(variants, images, selectedId) {
        const first = variants.find(v => v.id === selectedId) || variants[0] || {};
        return {
            variants, images, qty: 1,
            current: first,
            image: first.image || images[0] || null,
            choose(v) { this.current = v; if (v.image) this.image = v.image; }
        };
    }
</script>
@endpush
