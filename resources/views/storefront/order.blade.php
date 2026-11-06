@extends('storefront.layout', ['title' => 'Order '.$order->number])

@php
    $steps = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $rank = ['pending' => 0, 'confirmed' => 1, 'processing' => 1, 'shipped' => 2, 'delivered' => 3, 'returned' => 3];
    $current = $rank[$order->status] ?? 0;
@endphp

@section('content')
<div class="container py-4 py-lg-5" style="max-width: 1040px">
    @if (session('placed'))
        <div class="panel text-center mb-4" style="border-color: var(--accent)">
            <i class="bi bi-bag-check-fill fs-1" style="color: var(--accent); filter: brightness(.85)"></i>
            <h1 class="h3 fw-bold mt-2">Thank you, {{ $order->customer_name }}!</h1>
            <p class="text-muted mb-0">Your order <strong>{{ $order->number }}</strong> has been placed. Save this page to check its progress.</p>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="section-kicker">Order</div>
            <h2 class="h3 fw-bold mb-0">{{ $order->number }}</h2>
            <div class="small text-muted">Placed {{ $order->placed_at->format('j M Y, g:i A') }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge rounded-pill text-bg-{{ $order->statusColor() }} fs-6">{{ $order->statusLabel() }}</span>
            <span class="badge rounded-pill text-bg-{{ $order->paymentStatusColor() }} fs-6">{{ $order->paymentStatusLabel() }}</span>
        </div>
    </div>

    @if (! $order->isPaid() && $order->status !== 'cancelled' && $order->payment_method !== 'cod' && $order->payment_status !== 'pending_verification')
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-exclamation-triangle"></i> This order is waiting for payment of <strong>{{ $order->money($order->balanceDue()) }}</strong>.</span>
            <a href="{{ route('shop.order.pay', ['number' => $order->number, 't' => $token]) }}" class="btn btn-sm btn-ink">Pay now</a>
        </div>
    @endif

    @if ($order->status === 'cancelled')
        <div class="alert alert-secondary">This order was cancelled.</div>
    @else
        <div class="panel mb-4">
            <div class="progress-steps">
                @foreach ($steps as $key => $label)
                    <div class="step @if($loop->index <= $current) done @endif">
                        <div class="dot">@if($loop->index <= $current)<i class="bi bi-check-lg"></i>@endif</div>{{ $label }}
                    </div>
                @endforeach
            </div>
            @if ($order->tracking_number)
                <p class="small text-center mt-3 mb-0"><i class="bi bi-truck"></i> {{ $order->courier }} tracking number: <strong>{{ $order->tracking_number }}</strong></p>
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel mb-4">
                <h3 class="h6 fw-bold mb-3">Items</h3>
                @foreach ($order->items as $item)
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <img class="line-thumb" src="{{ $item->imageUrl() }}" alt="">
                        <div class="flex-grow-1 small">
                            <div class="fw-semibold">{{ $item->product_name }}</div>
                            <div class="text-muted">{{ $item->variant_name }} · {{ $item->quantity }} × {{ $order->money($item->unit_price) }}</div>
                            @if ($item->is_preorder)<span class="stock-note preorder">Pre-order</span>@endif
                        </div>
                        <div class="fw-semibold small">{{ $order->money(\App\Support\Money::fromMinor(\App\Support\Money::toMinor($item->unit_price) * $item->quantity)) }}</div>
                    </div>
                @endforeach
                <hr>
                <div class="summary-row"><span>Subtotal</span><span>{{ $order->money($order->subtotal) }}</span></div>
                @if ($order->discount_total > 0)<div class="summary-row text-success"><span>Discount @if($order->coupon_code)({{ $order->coupon_code }})@endif</span><span>- {{ $order->money($order->discount_total) }}</span></div>@endif
                <div class="summary-row"><span>Delivery</span><span>{{ $order->shipping_total > 0 ? $order->money($order->shipping_total) : 'Free' }}</span></div>
                @if ($order->tax_total > 0)<div class="summary-row small text-muted"><span>VAT {{ $order->tax_inclusive ? '(included)' : '' }}</span><span>{{ $order->money($order->tax_total) }}</span></div>@endif
                <div class="summary-row summary-total"><span>Total</span><span>{{ $order->money($order->grand_total) }}</span></div>
                @if ($order->invoice)
                    <a href="{{ route('shop.order.invoice', ['number' => $order->number, 't' => $token]) }}" target="_blank" class="btn btn-sm btn-outline-dark mt-3"><i class="bi bi-receipt"></i> Invoice {{ $order->invoice->number }}</a>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel mb-4">
                <h3 class="h6 fw-bold mb-2">Delivery to</h3>
                <div class="small">{{ $order->customer_name }} · {{ $order->customer_phone }}<br>{{ $order->fullAddress() }}@if($order->shipping_landmark)<br><span class="text-muted">Near {{ $order->shipping_landmark }}</span>@endif</div>
                <h3 class="h6 fw-bold mt-3 mb-2">Payment</h3>
                <div class="small">{{ $method?->name ?? $order->payment_method }} · {{ $order->paymentStatusLabel() }}</div>
            </div>
            <div class="panel">
                <h3 class="h6 fw-bold mb-3">Updates</h3>
                <ul class="timeline small">
                    @foreach ($order->histories as $history)
                        <li><strong>{{ $history->label() }}</strong> <span class="text-muted">· {{ $history->created_at->format('j M, g:i A') }}</span>@if ($history->note)<div class="text-muted">{{ $history->note }}</div>@endif</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
