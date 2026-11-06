@extends('storefront.layout', ['title' => 'Checkout', 'inlineErrors' => true])

@php
    $money = fn ($minor) => \App\Support\Money::format(\App\Support\Money::fromMinor($minor));
    $icons = ['khalti' => 'K', 'qr' => '<i class="bi bi-qr-code-scan"></i>', 'cod' => '<i class="bi bi-cash-coin"></i>', 'bank' => '<i class="bi bi-bank"></i>'];
    $defaultMethod = old('payment_method', $methods->first()?->code);
@endphp

@section('content')
<div class="container py-4 py-lg-5" x-data="checkout()">
    <h1 class="section-title h2 mb-1">Checkout</h1>
    <p class="text-muted mb-4">@guest('customer') Have an account? <a href="{{ route('shop.account.login') }}">Sign in</a> for faster checkout. @endguest Guest checkout is fine too.</p>

    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('shop.checkout.place') }}" @submit="submitting = true">
        @csrf
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="panel mb-4">
                    <h2 class="h6 fw-bold mb-3"><span class="badge text-bg-dark rounded-pill me-2">1</span>Contact</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Full name</label>
                            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $customer?->name) }}" required maxlength="120" autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Mobile number</label>
                            <input id="phone" name="phone" x-model="phone" @change="refresh()" class="form-control @error('phone') is-invalid @enderror" placeholder="98XXXXXXXX" inputmode="tel" required autocomplete="tel">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="email">Email <span class="text-muted fw-normal">(for order updates, optional)</span></label>
                            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $customer?->email) }}" autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="panel mb-4">
                    <h2 class="h6 fw-bold mb-3"><span class="badge text-bg-dark rounded-pill me-2">2</span>Delivery address</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="city">City / District</label>
                            <input id="city" name="city" list="city-list" x-model="city" @change="refresh()" @blur="refresh()" class="form-control @error('city') is-invalid @enderror" required maxlength="100" autocomplete="address-level2">
                            <datalist id="city-list">@foreach ($cities as $c)<option value="{{ $c }}">@endforeach</datalist>
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="province">Province</label>
                            <select id="province" name="province" class="form-select">
                                <option value="">Choose...</option>
                                @foreach ($provinces as $p)<option @selected(old('province', 'Bagmati') === $p)>{{ $p }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="area">Area / Tole</label>
                            <input id="area" name="area" class="form-control" value="{{ old('area', $address?->area) }}" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="landmark">Landmark <span class="text-muted fw-normal">(optional)</span></label>
                            <input id="landmark" name="landmark" class="form-control" value="{{ old('landmark', $address?->landmark) }}" maxlength="250">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Street address</label>
                            <input id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $address?->address_line) }}" required maxlength="250" autocomplete="street-address">
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="note">Note for the shop <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea id="note" name="note" rows="2" class="form-control" maxlength="1000">{{ old('note') }}</textarea>
                        </div>
                    </div>
                    <div class="small text-muted mt-3" x-show="zone" x-cloak><i class="bi bi-truck"></i> <span x-text="zone"></span></div>
                </div>

                <div class="panel">
                    <h2 class="h6 fw-bold mb-3"><span class="badge text-bg-dark rounded-pill me-2">3</span>Payment</h2>
                    @if ($methods->isEmpty())
                        <div class="alert alert-warning mb-0">This shop has not set up any payment method yet. Please contact the shop.</div>
                    @endif
                    <div class="d-grid gap-2">
                        @foreach ($methods as $method)
                            <label class="pay-option" @if($method->code === 'cod') :style="!cod && 'opacity:.5'" @endif>
                                <input type="radio" name="payment_method" value="{{ $method->code }}" x-model="method" @checked($defaultMethod === $method->code) @if($method->code === 'cod') :disabled="!cod" @endif required>
                                <span class="pay-logo {{ $method->code }}">{!! $icons[$method->code] ?? '' !!}</span>
                                <span class="flex-grow-1">
                                    <strong class="d-block">{{ $method->name }}</strong>
                                    <span class="small text-muted">{{ $method->instructions }}</span>
                                    @if ($method->code === 'cod')<div class="small text-danger" x-show="!cod" x-cloak>Not available for this city.</div>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-lg-5">
                <div class="panel position-sticky" style="top:90px">
                    <h2 class="h6 fw-bold mb-3">Your order</h2>
                    @foreach ($quote->lines as $line)
                        <div class="d-flex gap-3 align-items-center mb-3">
                            <div class="position-relative">
                                <img class="line-thumb" src="{{ $line['variant']->imageUrl() }}" alt="">
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-dark">{{ $line['quantity'] }}</span>
                            </div>
                            <div class="flex-grow-1 small"><div class="fw-semibold">{{ $line['variant']->product->name }}</div><div class="text-muted">{{ $line['variant']->name }}</div></div>
                            <div class="small fw-semibold">{{ $money($line['subtotal']) }}</div>
                        </div>
                    @endforeach

                    <div class="d-flex gap-2 my-3">
                        <input name="coupon_code" x-model="coupon" class="form-control form-control-sm text-uppercase" placeholder="Coupon code" aria-label="Coupon code">
                        <button type="button" class="btn btn-sm btn-ink px-3" @click="refresh()">Apply</button>
                    </div>
                    <div class="small text-danger mb-2" x-show="couponError" x-text="couponError" x-cloak></div>
                    <div class="small text-success mb-2" x-show="appliedCoupon" x-cloak><i class="bi bi-check-circle"></i> <span x-text="appliedCoupon"></span> applied</div>

                    <div class="summary-row"><span>Subtotal</span><span x-text="totals.subtotal">{{ $money($quote->subtotal) }}</span></div>
                    <div class="summary-row text-success" x-show="totals.discount" x-cloak><span>Discount</span><span x-text="totals.discount"></span></div>
                    <div class="summary-row"><span>Delivery</span><span x-text="totals.shipping">{{ $quote->zone ? ($quote->shipping ? $money($quote->shipping) : 'Free') : 'Enter your city' }}</span></div>
                    <div class="summary-row text-muted small" x-show="totals.tax" x-cloak><span x-text="totals.tax_label"></span><span x-text="totals.tax"></span></div>
                    <div class="summary-row summary-total"><span>Total</span><span x-text="totals.total">{{ $money($quote->total) }}</span></div>
                    @if (app(\App\Services\CurrencyService::class)->selected(request()) !== $store->base_currency)
                        <div class="small text-muted">You will be charged in {{ $store->base_currency }}.</div>
                    @endif

                    <button class="btn btn-accent w-100 mt-3 py-2" :disabled="submitting || {{ $methods->isEmpty() ? 'true' : 'false' }}">
                        <span x-show="!submitting">Place order</span><span x-show="submitting" x-cloak><span class="spinner-border spinner-border-sm"></span> Placing order...</span>
                    </button>
                    <p class="small text-muted text-center mt-2 mb-0"><i class="bi bi-lock"></i> Your details are only used to deliver this order.</p>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function checkout() {
        return {
            city: @json(old('city', $address?->city ?? '')),
            phone: @json(old('phone', $customer?->phone ?? '')),
            coupon: @json(old('coupon_code', $cart->coupon_code ?? '')),
            method: @json($defaultMethod),
            totals: {}, zone: '', cod: true, couponError: '', appliedCoupon: '', submitting: false,
            init() { this.refresh(); },
            async refresh() {
                const res = await fetch(@json(route('shop.checkout.quote')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ city: this.city, phone: this.phone, coupon_code: this.coupon })
                });
                if (!res.ok) return;
                const d = await res.json();
                this.totals = d; this.zone = d.zone || ''; this.cod = d.cod;
                this.couponError = this.coupon ? (d.coupon_error || '') : '';
                this.appliedCoupon = d.coupon || '';
                if (!this.cod && this.method === 'cod') this.method = null;
            }
        };
    }
</script>
@endpush
