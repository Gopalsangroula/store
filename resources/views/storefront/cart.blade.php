@extends('storefront.layout', ['title' => 'Your cart'])

@section('content')
<div class="container py-4 py-lg-5">
    <h1 class="section-title h2 mb-4">Your cart</h1>

    @if (! $quote || $quote->isEmpty())
        <div class="panel text-center py-5">
            <i class="bi bi-cart3 fs-1 text-muted"></i>
            <p class="mt-2">Your cart is empty.</p>
            <a href="{{ route('shop.shop') }}" class="btn btn-accent">Start shopping</a>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="panel p-0">
                    @foreach ($quote->lines as $line)
                        @php $variant = $line['variant']; @endphp
                        <div class="d-flex gap-3 align-items-center p-3 @if(! $loop->last) border-bottom @endif">
                            <img class="line-thumb" src="{{ $variant->imageUrl() }}" alt="">
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('shop.product', $variant->product->slug) }}" class="fw-semibold text-decoration-none">{{ $variant->product->name }}</a>
                                @if ($variant->name)<div class="small text-muted">{{ $variant->name }}</div>@endif
                                <div class="small fw-semibold mt-1">{{ \App\Support\Money::format($variant->price) }}</div>
                                @if ($variant->stockState() === 'preorder')<span class="stock-note preorder">Pre-order</span>@endif
                            </div>
                            <form method="POST" action="{{ route('shop.cart.update', $line['item_id']) }}" class="d-flex align-items-center">
                                @csrf @method('PATCH')
                                <select name="quantity" class="form-select form-select-sm" style="width:80px" onchange="this.form.submit()" aria-label="Quantity">
                                    @for ($q = 1; $q <= max(10, $line['quantity']); $q++)
                                        <option value="{{ $q }}" @selected($q === $line['quantity'])>{{ $q }}</option>
                                    @endfor
                                </select>
                            </form>
                            <div class="fw-bold text-end" style="min-width:90px">{{ \App\Support\Money::format(\App\Support\Money::fromMinor($line['subtotal'])) }}</div>
                            <form method="POST" action="{{ route('shop.cart.remove', $line['item_id']) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-muted" aria-label="Remove"><i class="bi bi-trash3"></i></button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('shop.shop') }}" class="btn btn-link text-decoration-none px-0 mt-2"><i class="bi bi-arrow-left"></i> Continue shopping</a>
            </div>
            <div class="col-lg-4">
                <div class="panel">
                    <h2 class="h6 fw-bold mb-3">Order summary</h2>
                    @if ($quote->coupon)
                        <div class="d-flex justify-content-between align-items-center bg-light rounded-3 p-2 mb-3 small">
                            <span><i class="bi bi-ticket-perforated"></i> <strong>{{ $quote->coupon->code }}</strong> · {{ $quote->coupon->describe() }}</span>
                            <form method="POST" action="{{ route('shop.cart.coupon.remove') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Remove</button></form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('shop.cart.coupon') }}" class="d-flex gap-2 mb-3">
                            @csrf
                            <input name="code" class="form-control form-control-sm text-uppercase" placeholder="Coupon code" value="{{ old('code') }}" aria-label="Coupon code">
                            <button class="btn btn-sm btn-ink px-3">Apply</button>
                        </form>
                        @if ($quote->couponError && $cart->coupon_code)<div class="small text-danger mb-2">{{ $quote->couponError }}</div>@endif
                    @endif
                    <div class="summary-row"><span>Subtotal ({{ $quote->itemCount() }} items)</span><span>{{ \App\Support\Money::format($quote->money('subtotal')) }}</span></div>
                    @if ($quote->discount > 0)<div class="summary-row text-success"><span>Discount</span><span>- {{ \App\Support\Money::format($quote->money('discount')) }}</span></div>@endif
                    <div class="summary-row text-muted"><span>Delivery</span><span>{{ $quote->freeShipping ? 'Free' : 'At checkout' }}</span></div>
                    <div class="summary-row summary-total"><span>Total</span><span>{{ \App\Support\Money::format(\App\Support\Money::fromMinor($quote->subtotal - $quote->discount)) }}</span></div>
                    @if ($quote->taxRate && $quote->taxInclusive)<div class="small text-muted">Prices include {{ $quote->taxRate->name }}.</div>@endif
                    <a href="{{ route('shop.checkout') }}" class="btn btn-accent w-100 mt-3 py-2">Checkout <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
