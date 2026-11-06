@extends('admin.layout', ['title' => 'Dashboard'])

@php
    $m = fn ($v) => \App\Support\Money::format(round((float) $v), $store->base_currency);
    $change = $stats['prev_month_sales'] > 0 ? (($stats['month_sales'] - $stats['prev_month_sales']) / $stats['prev_month_sales']) * 100 : null;
@endphp

@section('content')
<div class="page-head">
    <div><h1>Namaste, {{ explode(' ', auth()->user()->name)[0] }}</h1><div class="sub">Here is how {{ $store->name }} is doing.</div></div>
    @staffcan('products.manage')<a href="{{ route('admin.products.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add product</a>@endstaffcan
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Sales today</div><div class="value">{{ $m($stats['today_sales']) }}</div><div class="hint">{{ $stats['today_orders'] }} orders</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Last 30 days</div><div class="value">{{ $m($stats['month_sales']) }}</div><div class="hint">{{ $stats['month_orders'] }} orders @if($change !== null)· <span class="{{ $change >= 0 ? 'text-success' : 'text-danger' }}">{{ $change >= 0 ? '+' : '' }}{{ number_format($change, 0) }}%</span> vs previous 30 @endif</div></div></div>
    <div class="col-6 col-xl-3"><a href="{{ route('admin.orders.index', ['payment' => 'pending_verification']) }}" class="text-reset"><div @class(['stat', 'alert-stat' => $stats['to_verify'] > 0])><div class="label">Payments to verify</div><div class="value">{{ $stats['to_verify'] }}</div><div class="hint">QR and bank transfers</div></div></a></div>
    <div class="col-6 col-xl-3"><a href="{{ route('admin.orders.index', ['status' => 'confirmed']) }}" class="text-reset"><div class="stat"><div class="label">To pack & ship</div><div class="value">{{ $stats['to_ship'] }}</div><div class="hint">{{ $stats['pending'] }} new orders waiting</div></div></a></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card-a h-100">
            <div class="head">Net sales, last 30 days <span class="small text-muted fw-normal">excluding VAT, after refunds</span></div>
            <div class="body"><canvas id="salesChart" height="110" aria-label="Net sales per day"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card-a h-100">
            <div class="head">Best sellers (30 days)</div>
            <ul class="list-group list-group-flush">
                @forelse ($top as $row)
                    <li class="list-group-item d-flex justify-content-between"><span>{{ $row->product_name }} <span class="text-muted small">{{ $row->variant_name }}</span></span><strong>{{ $row->qty }}</strong></li>
                @empty
                    <li class="list-group-item empty">No sales yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card-a">
            <div class="head">Recent orders <a href="{{ route('admin.orders.index') }}" class="small fw-normal">All orders</a></div>
            <div class="table-responsive">
                <table class="table table-a">
                    <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($recent as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="fw-semibold">{{ $order->number }}</a><div class="small text-muted">{{ $order->placed_at->diffForHumans() }}</div></td>
                            <td>{{ $order->customer_name }}<div class="small text-muted">{{ $order->shipping_city }}</div></td>
                            <td class="fw-semibold">{{ $m($order->grand_total) }}</td>
                            <td><span class="badge text-bg-{{ $order->paymentStatusColor() }}">{{ $order->paymentStatusLabel() }}</span><div class="small text-muted">{{ \App\Models\PaymentMethod::CODES[$order->payment_method] ?? $order->payment_method }}</div></td>
                            <td><span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No orders yet. Share your store link to get the first one.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        @if ($toVerify->isNotEmpty())
            <div class="card-a mb-3">
                <div class="head">Waiting for payment check</div>
                <ul class="list-group list-group-flush">
                    @foreach ($toVerify as $order)
                        <a href="{{ route('admin.orders.show', $order) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $order->number }}<div class="small text-muted">{{ $order->customer_name }}</div></span><strong>{{ $m($order->grand_total) }}</strong></a>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($store->hasFeature('inventory'))
        <div class="card-a">
            <div class="head">Low stock <a href="{{ route('admin.inventory.index', ['filter' => 'low']) }}" class="small fw-normal">Stock overview</a></div>
            <ul class="list-group list-group-flush">
                @forelse ($lowStock as $row)
                    <li class="list-group-item d-flex justify-content-between small"><span>{{ $row->variant->product->name }} <span class="text-muted">{{ $row->variant->name }} · {{ $row->warehouse->code }}</span></span><span class="badge {{ $row->available() <= 0 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $row->available() }} left</span></li>
                @empty
                    <li class="list-group-item empty small">All stock levels are healthy.</li>
                @endforelse
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="/vendor/chartjs/chart.umd.js"></script>
<script>
    (() => {
        const data = @json($stats['chart']);
        new Chart(document.getElementById('salesChart'), {
            type: 'bar',
            data: {
                labels: data.map(d => d.date),
                datasets: [{ label: 'Net sales', data: data.map(d => d.sales), backgroundColor: '#f5c542', borderRadius: 4, maxBarThickness: 22 }]
            },
            options: {
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'Rs. ' + Math.round(c.parsed.y).toLocaleString('en-IN') } } },
                scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } }, y: { grid: { color: '#eef0f3' }, ticks: { callback: v => 'Rs. ' + (v >= 1000 ? (v / 1000) + 'k' : v) } } }
            }
        });
    })();
</script>
@endpush
