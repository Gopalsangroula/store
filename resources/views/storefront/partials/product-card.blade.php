{{-- One product with a row per variant, each with its own Buy button. --}}
@php
    $variants = $product->activeVariants;
    $image = $product->imageUrl();
@endphp
<div class="product-card">
    <a href="{{ route('shop.product', $product->slug) }}" class="product-media" aria-label="{{ $product->name }}">
        @if ($product->badge)
            <span class="product-badge @if(str_contains(strtolower($product->badge), 'limited')) accent @endif">{{ $product->badge }}</span>
        @endif
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span class="d-flex h-100 align-items-center justify-content-center text-muted"><i class="bi bi-box-seam fs-1"></i></span>
        @endif
    </a>
    <div class="product-body">
        <a href="{{ route('shop.product', $product->slug) }}" class="product-title">{{ $product->name }}</a>
        <div class="variant-list">
            @foreach ($variants as $variant)
                @php $state = $variant->stockState(); @endphp
                <div class="variant-row">
                    @if ($variant->image_path)
                        <img class="variant-thumb" src="{{ $variant->imageUrl() }}" alt="" loading="lazy">
                    @elseif ($variant->swatch)
                        <span class="variant-swatch" style="background: {{ $variant->swatch }}"></span>
                    @else
                        <img class="variant-thumb" src="{{ $image }}" alt="" loading="lazy">
                    @endif
                    <div class="variant-meta">
                        <div class="name">{{ $variant->name ?: $product->name }}</div>
                        <div class="price">@price($variant->price)@if ($variant->compare_at_price && $variant->compare_at_price > $variant->price)<span class="was">@price($variant->compare_at_price)</span>@endif</div>
                        @if ($state === 'low')<div class="stock-note low">Only {{ $variant->available() }} left</div>@endif
                    </div>
                    @if ($state === 'out')
                        <button class="btn-pill" disabled>Sold out</button>
                    @else
                        <button type="button" class="add-icon" title="Add to cart" @click="addToCart({{ $variant->id }})" aria-label="Add {{ $variant->name }} to cart"><i class="bi bi-bag-plus"></i></button>
                        <form method="POST" action="{{ route('shop.cart.buy') }}" class="m-0">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                            <button class="btn-pill">{{ $state === 'preorder' ? 'Pre-Order' : 'Buy' }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
