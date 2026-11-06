@extends('admin.layout', ['title' => $order->number])

@php
    $m = fn ($v) => $order->money($v);
    $refundable = max(0, (float) $order->paid_total - (float) $order->refunded_total);
@endphp

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('admin.orders.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Orders</a>
        <h1>{{ $order->number }}
            <span class="badge text-bg-{{ $order->statusColor() }} fs-6 align-middle">{{ $order->statusLabel() }}</span>
            <span class="badge text-bg-{{ $order->paymentStatusColor() }} fs-6 align-middle">{{ $order->paymentStatusLabel() }}</span>
        </h1>
        <div class="sub">Placed {{ $order->placed_at->format('l, j M Y \a\t g:i A') }} · {{ \App\Models\PaymentMethod::CODES[$order->payment_method] ?? $order->payment_method }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.orders.slip', $order) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer"></i> Packing slip</a>
        @if ($order->invoice)<a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="btn btn-light"><i class="bi bi-receipt"></i> Invoice {{ $order->invoice->number }}</a>@endif
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        {{-- Payment needing a decision comes first. --}}
        @foreach ($order->payments->where('status', 'pending_verification') as $payment)
            <div class="card-a mb-3 border-warning" style="border-width:2px">
                <div class="head bg-warning-subtle"><span><i class="bi bi-patch-question"></i> Check this {{ $payment->methodLabel() }} payment</span><span>{{ $m($payment->amount) }}</span></div>
                <div class="body row g-3">
                    <div class="col-md-5">
                        @if ($payment->proof_path)
                            <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank"><img src="{{ route('admin.payments.proof', $payment) }}" class="proof-img" alt="Payment screenshot"></a>
                        @else
                            <div class="empty border rounded">No screenshot uploaded</div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <dl class="kv">
                            <dt>Customer's transaction ID</dt><dd class="fw-bold">{{ $payment->payer_reference ?: '—' }}</dd>
                            <dt>Submitted</dt><dd>{{ $payment->updated_at->format('j M Y, g:i A') }}</dd>
                        </dl>
                        <p class="small text-muted">Open your bank or wallet statement and confirm {{ $m($payment->amount) }} arrived with remarks {{ $order->number }}.</p>
                        @staffcan('payments.verify')
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="d-flex gap-2 mb-2">
                            @csrf
                            <input name="transaction_id" class="form-control" placeholder="Transaction ID from your statement" value="{{ $payment->payer_reference }}">
                            <button class="btn btn-success text-nowrap" onclick="return confirm('Confirm that {{ $m($payment->amount) }} was received?')"><i class="bi bi-check-lg"></i> Received</button>
                        </form>
                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="d-flex gap-2">
                            @csrf
                            <input name="reason" class="form-control form-control-sm" placeholder="Why it was not accepted" required>
                            <button class="btn btn-sm btn-outline-danger text-nowrap">Not received</button>
                        </form>
                        @endstaffcan
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card-a mb-3">
            <div class="head">Items</div>
            <div class="table-responsive"><table class="table table-a">
                <thead><tr><th></th><th>Item</th><th class="text-end">Price</th><th class="text-end">Qty</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td style="width:56px"><img src="{{ $item->imageUrl() }}" class="thumb" alt=""></td>
                        <td>{{ $item->product_name }} <span class="text-muted">{{ $item->variant_name }}</span><div class="small text-muted">{{ $item->sku }} @if($item->is_preorder)<span class="badge text-bg-info">Pre-order</span>@endif</div></td>
                        <td class="text-end">{{ $m($item->unit_price) }}</td>
                        <td class="text-end">{{ $item->quantity }}</td>
                        <td class="text-end fw-semibold">{{ $m($item->unit_price * $item->quantity) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="body border-top">
                <div class="row justify-content-end"><div class="col-md-6 small">
                    <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ $m($order->subtotal) }}</span></div>
                    @if ($order->discount_total > 0)<div class="d-flex justify-content-between text-success"><span>Discount {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</span><span>- {{ $m($order->discount_total) }}</span></div>@endif
                    <div class="d-flex justify-content-between"><span>Delivery ({{ $order->shipping_zone_name }})</span><span>{{ $m($order->shipping_total) }}</span></div>
                    <div class="d-flex justify-content-between text-muted"><span>VAT {{ $order->tax_inclusive ? 'included' : '' }}</span><span>{{ $m($order->tax_total) }}</span></div>
                    <div class="d-flex justify-content-between fw-bold fs-6 border-top pt-2 mt-1"><span>Total</span><span>{{ $m($order->grand_total) }}</span></div>
                    <div class="d-flex justify-content-between"><span>Paid</span><span>{{ $m($order->paid_total) }}</span></div>
                    @if ($order->refunded_total > 0)<div class="d-flex justify-content-between text-danger"><span>Refunded</span><span>- {{ $m($order->refunded_total) }}</span></div>@endif
                    @if ($order->balanceDue() > 0 && $order->status !== 'cancelled')<div class="d-flex justify-content-between fw-bold text-danger"><span>Balance due</span><span>{{ $m($order->balanceDue()) }}</span></div>@endif
                </div></div>
            </div>
        </div>

        <div class="card-a mb-3">
            <div class="head">Payments</div>
            <div class="table-responsive"><table class="table table-a">
                <thead><tr><th>Method</th><th>Amount</th><th>Status</th><th>Reference</th><th>When</th><th></th></tr></thead>
                <tbody>
                @forelse ($order->payments as $payment)
                    <tr>
                        <td>{{ $payment->methodLabel() }}</td>
                        <td>{{ $m($payment->amount) }}</td>
                        <td><span class="badge text-bg-{{ $payment->statusColor() }}">{{ $payment->statusLabel() }}</span></td>
                        <td class="small">{{ $payment->transaction_id ?: $payment->payer_reference ?: $payment->gateway_reference ?: '—' }}@if($payment->verifier)<div class="text-muted">verified by {{ $payment->verifier->name }}</div>@endif</td>
                        <td class="small">{{ ($payment->completed_at ?? $payment->updated_at)->format('j M, g:i A') }}</td>
                        <td class="text-end">
                            @if ($payment->method === 'khalti' && $payment->gateway_reference && $payment->status !== 'completed')
                                <form method="POST" action="{{ route('admin.payments.recheck', $payment) }}">@csrf<button class="btn btn-sm btn-light">Check with Khalti</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No payments.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        @if ($order->isPaid() && $refundable > 0)
            @staffcan('refunds.manage')
            <div class="card-a mb-3" x-data="{ open: false }">
                <div class="head">Refund <button class="btn btn-sm btn-light" @click="open = !open" x-text="open ? 'Close' : 'Make a refund'"></button></div>
                <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="body" x-show="open" x-cloak>
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Amount (max {{ $m($refundable) }})</label><input name="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" value="{{ $refundable }}" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Paid back by</label><select name="method" class="form-select"><option value="{{ $order->payment_method === 'cod' ? 'cash' : $order->payment_method }}">{{ $order->payment_method === 'cod' ? 'Cash' : (\App\Models\PaymentMethod::CODES[$order->payment_method] ?? $order->payment_method) }}</option><option value="bank">Bank transfer</option><option value="cash">Cash</option></select></div>
                        <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Refund transaction ID"></div>
                        <div class="col-12"><label class="form-label">Reason</label><input name="reason" class="form-control" required maxlength="300" placeholder="Returned unused, damaged in delivery..."></div>
                        <div class="col-12"><label class="form-label">Put inspected items back into stock</label>
                            @foreach ($order->items as $item)
                                <div class="d-flex align-items-center gap-2 mb-1 small"><input type="number" name="restock[{{ $item->id }}]" min="0" max="{{ $item->quantity }}" value="0" class="form-control form-control-sm" style="width:80px"><span>of {{ $item->quantity }} × {{ $item->product_name }} {{ $item->variant_name }}</span></div>
                            @endforeach
                            <div class="form-text">Leave 0 for items that are not resellable.</div>
                        </div>
                    </div>
                    <button class="btn btn-danger mt-3" onclick="return confirm('Record this refund and issue a credit note?')">Record refund</button>
                    <div class="form-text">This records the refund and credit note. Send the money itself from your Khalti merchant panel or bank.</div>
                </form>
            </div>
            @endstaffcan
        @endif
    </div>

    <div class="col-xl-4">
        @staffcan('orders.manage')
        @if ($next)
            <div class="card-a mb-3">
                <div class="head">Update order</div>
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="body" x-data="{ status: '{{ $next[0] }}' }">
                    @csrf
                    <div class="mb-2"><select name="status" class="form-select" x-model="status">@foreach ($next as $s)<option value="{{ $s }}">{{ \App\Models\Order::STATUSES[$s][0] }}</option>@endforeach</select></div>
                    <div class="row g-2 mb-2" x-show="status === 'shipped'" x-cloak>
                        <div class="col-6"><input name="courier" class="form-control form-control-sm" placeholder="Courier" value="{{ $order->courier }}"></div>
                        <div class="col-6"><input name="tracking_number" class="form-control form-control-sm" placeholder="Tracking no." value="{{ $order->tracking_number }}"></div>
                    </div>
                    <textarea name="note" rows="2" class="form-control form-control-sm mb-2" placeholder="Note for the customer (optional)"></textarea>
                    <button class="btn btn-brand w-100" :class="status === 'cancelled' && 'btn-danger'" @click="if (status === 'cancelled' && !confirm('Cancel this order and release its stock?')) $event.preventDefault()">Save</button>
                    @if ($order->payment_method === 'cod')<div class="form-text">Marking a cash order delivered records the cash as collected.</div>@endif
                </form>
            </div>
        @endif
        @endstaffcan

        <div class="card-a mb-3">
            <div class="head">Customer @if($order->customer_id)<a href="{{ route('admin.customers.show', $order->customer_id) }}" class="small fw-normal">Profile</a>@endif</div>
            <div class="body small">
                <div class="fw-semibold fs-6">{{ $order->customer_name }}</div>
                <div><a href="tel:{{ $order->customer_phone }}">{{ $order->customer_phone }}</a> · <a href="https://wa.me/977{{ $order->customer_phone }}" target="_blank">WhatsApp</a></div>
                @if ($order->customer_email)<div>{{ $order->customer_email }}</div>@endif
                <div class="text-muted mt-1">{{ $previousOrders }} other {{ \Illuminate\Support\Str::plural('order', $previousOrders) }}</div>
                <hr>
                <div class="fw-semibold">Delivery address</div>
                <div>{{ $order->fullAddress() }}</div>
                @if ($order->shipping_landmark)<div class="text-muted">Near {{ $order->shipping_landmark }}</div>@endif
                @if ($order->tracking_number)<div class="mt-2"><i class="bi bi-truck"></i> {{ $order->courier }} {{ $order->tracking_number }}</div>@endif
                @if ($order->customer_note)<div class="alert alert-light border mt-2 mb-0 py-2"><i class="bi bi-chat-left-text"></i> {{ $order->customer_note }}</div>@endif
            </div>
        </div>

        <div class="card-a">
            <div class="head">History</div>
            <div class="body">
                <ul class="timeline-a small">
                    @foreach ($order->histories as $h)
                        <li><strong>{{ $h->label() }}</strong> <span class="text-muted">{{ $h->created_at->format('j M, g:i A') }}{{ $h->user ? ' · '.$h->user->name : '' }}</span> @unless($h->is_customer_visible)<span class="badge badge-soft">internal</span>@endunless @if($h->note)<div>{{ $h->note }}</div>@endif</li>
                    @endforeach
                </ul>
                @staffcan('orders.manage')
                <form method="POST" action="{{ route('admin.orders.note', $order) }}">
                    @csrf
                    <textarea name="note" rows="2" class="form-control form-control-sm mb-2" placeholder="Add a note" required></textarea>
                    <div class="d-flex justify-content-between align-items-center"><label class="small"><input type="checkbox" name="visible" value="1"> Customer can see it</label><button class="btn btn-sm btn-light">Add note</button></div>
                </form>
                @endstaffcan
            </div>
        </div>
    </div>
</div>
@endsection
