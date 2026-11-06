@extends('admin.layout', ['title' => $customer->name])

@section('content')
<div class="page-head"><div><a href="{{ route('admin.customers.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Customers</a><h1>{{ $customer->name }}</h1><div class="sub">Customer since {{ $customer->created_at->format('j M Y') }}</div></div></div>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="row g-3 mb-3">
            <div class="col-6"><div class="stat"><div class="label">Orders</div><div class="value">{{ $customer->orders_count }}</div></div></div>
            <div class="col-6"><div class="stat"><div class="label">Spent</div><div class="value fs-5">{{ \App\Support\Money::format($customer->total_spent) }}</div></div></div>
        </div>
        <div class="card-a"><div class="body"><dl class="kv mb-0">
            <dt>Phone</dt><dd><a href="tel:{{ $customer->phone }}">{{ $customer->phone }}</a> · <a href="https://wa.me/977{{ $customer->phone }}" target="_blank">WhatsApp</a></dd>
            <dt>Email</dt><dd>{{ $customer->email ?: '—' }}</dd>
            <dt>Account</dt><dd>{{ $customer->hasAccount() ? 'Registered' : 'Guest checkout only' }}</dd>
            @foreach ($customer->addresses as $address)<dt>Address</dt><dd>{{ $address->address_line }}, {{ $address->area }}, {{ $address->city }}</dd>@endforeach
        </dl></div></div>
    </div>
    <div class="col-xl-8">
        <div class="card-a"><div class="head">Orders</div><div class="table-responsive"><table class="table table-a">
            <thead><tr><th>Order</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold">{{ $order->number }}</a><div class="small text-muted">{{ $order->placed_at->format('j M Y') }}</div></td>
                    <td>{{ $order->items_count }}</td>
                    <td>{{ $order->money($order->grand_total) }}</td>
                    <td><span class="badge text-bg-{{ $order->paymentStatusColor() }}">{{ $order->paymentStatusLabel() }}</span></td>
                    <td><span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No orders.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
</div>
@endsection
