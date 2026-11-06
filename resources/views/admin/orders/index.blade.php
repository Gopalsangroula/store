@extends('admin.layout', ['title' => 'Orders'])

@section('content')
<div class="page-head">
    <div><h1>{{ request('payment') === 'pending_verification' ? 'Payments to verify' : 'Orders' }}</h1><div class="sub">{{ $orders->total() }} orders</div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('admin.orders.index') }}" @class(['btn btn-sm', 'btn-dark' => ! request('status') && ! request('payment'), 'btn-light' => request('status') || request('payment')])>All</a>
    @foreach (\App\Models\Order::STATUSES as $key => [$label])
        <a href="{{ route('admin.orders.index', ['status' => $key]) }}" @class(['btn btn-sm', 'btn-dark' => request('status') === $key, 'btn-light' => request('status') !== $key])>{{ $label }} <span class="opacity-75">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
</div>

<form class="card-a mb-3 filters"><div class="body row g-2">
    @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Order no., name or phone"></div>
    <div class="col-md-2"><select name="payment" class="form-select"><option value="">Any payment</option>@foreach (\App\Models\Order::PAYMENT_STATUSES as $key => [$label])<option value="{{ $key }}" @selected(request('payment') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="method" class="form-select"><option value="">Any method</option>@foreach (\App\Models\PaymentMethod::CODES as $key => $label)<option value="{{ $key }}" @selected(request('method') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" title="From"></div>
    <div class="col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" title="To"></div>
    <div class="col-md-1 d-grid"><button class="btn btn-outline-dark">Go</button></div>
</div></form>

<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($orders as $order)
        <tr>
            <td><a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold">{{ $order->number }}</a><div class="small text-muted">{{ $order->placed_at->format('j M Y, g:i A') }}</div></td>
            <td>{{ $order->customer_name }}<div class="small text-muted">{{ $order->customer_phone }} · {{ $order->shipping_city }}</div></td>
            <td>{{ $order->items_count }}</td>
            <td class="fw-semibold">{{ $order->money($order->grand_total) }}</td>
            <td><span class="badge text-bg-{{ $order->paymentStatusColor() }}">{{ $order->paymentStatusLabel() }}</span><div class="small text-muted">{{ \App\Models\PaymentMethod::CODES[$order->payment_method] ?? $order->payment_method }}</div></td>
            <td><span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No orders match.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
