@extends('admin.layout', ['title' => 'Reports'])

@php $m = fn ($v) => \App\Support\Money::format($v, $store->base_currency); @endphp

@section('content')
<div class="page-head">
    <div><h1>Reports</h1><div class="sub">{{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}</div></div>
    @if ($report !== 'inventory')
        <form class="d-flex gap-2 align-items-center">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
            <span class="text-muted">to</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-brand">Show</button>
        </form>
    @endif
</div>

<ul class="nav nav-pills mb-3">
    @foreach (\App\Http\Controllers\StoreAdmin\ReportController::REPORTS as $key => $label)
        <li class="nav-item"><a class="nav-link @if($key === $report) active bg-dark @else text-dark @endif" href="{{ route('admin.reports', ['report' => $key, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $label }}</a></li>
    @endforeach
</ul>

@if ($report === 'sales')
    @php $days = $data['days']; @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="stat"><div class="label">Gross sales</div><div class="value fs-4">{{ $m($days->sum('gross_sales')) }}</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="label">Discounts</div><div class="value fs-4">{{ $m($days->sum('discounts')) }}</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="label">Net sales</div><div class="value fs-4">{{ $m($days->sum('net_sales')) }}</div><div class="hint">after discounts and refunds, before VAT</div></div></div>
        <div class="col-6 col-xl-3"><div class="stat"><div class="label">Orders / items</div><div class="value fs-4">{{ $days->sum('orders_count') }} / {{ $days->sum('items_sold') }}</div><div class="hint">avg {{ $m($days->sum('orders_count') ? ($days->sum('net_sales') + $days->sum('tax')) / $days->sum('orders_count') : 0) }} per order</div></div></div>
    </div>
    <div class="card-a mb-3"><div class="head">Net sales by day</div><div class="body"><canvas id="chart" height="90"></canvas></div></div>
    <div class="row g-3">
        <div class="col-lg-6"><div class="card-a"><div class="head">By payment method</div><table class="table table-a"><thead><tr><th>Method</th><th class="text-end">Orders</th><th class="text-end">Value</th><th class="text-end">Collected</th></tr></thead><tbody>
            @forelse ($data['methods'] as $row)<tr><td>{{ \App\Models\PaymentMethod::CODES[$row->payment_method] ?? $row->payment_method }}</td><td class="text-end">{{ $row->orders }}</td><td class="text-end">{{ $m($row->total) }}</td><td class="text-end">{{ $m($row->paid) }}</td></tr>@empty<tr><td colspan="4" class="empty">No orders.</td></tr>@endforelse
        </tbody></table></div></div>
        <div class="col-lg-6"><div class="card-a"><div class="head">Coupon performance</div><table class="table table-a"><thead><tr><th>Code</th><th class="text-end">Uses</th><th class="text-end">Discount</th><th class="text-end">Order value</th></tr></thead><tbody>
            @forelse ($data['coupons'] as $row)<tr><td class="font-monospace">{{ $row->code }}</td><td class="text-end">{{ $row->uses }}</td><td class="text-end">{{ $m($row->discount) }}</td><td class="text-end">{{ $m($row->revenue) }}</td></tr>@empty<tr><td colspan="4" class="empty">No coupons used.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
    @push('scripts')
    <script src="/vendor/chartjs/chart.umd.js"></script>
    <script>
        new Chart(document.getElementById('chart'), { type: 'line', data: { labels: @json($days->map(fn ($d) => $d->date->format('j M'))), datasets: [{ data: @json($days->map(fn ($d) => (float) $d->net_sales)), borderColor: '#16181d', backgroundColor: 'rgba(245,197,66,.25)', fill: true, tension: .3, pointRadius: 2 }] }, options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#eef0f3' } } } } });
    </script>
    @endpush
@elseif ($report === 'products')
    <div class="card-a"><div class="head">Best-selling items</div><div class="table-responsive"><table class="table table-a">
        <thead><tr><th>Item</th><th>SKU</th><th class="text-end">Sold</th><th class="text-end">Revenue</th><th class="text-end">Cost</th><th class="text-end">Margin</th></tr></thead>
        <tbody>
        @forelse ($data['top'] as $row)
            @php $margin = (float) $row->revenue > 0 ? ((float) $row->revenue - (float) $row->cost) / (float) $row->revenue * 100 : 0; @endphp
            <tr><td>{{ $row->product_name }} <span class="text-muted">{{ $row->variant_name }}</span></td><td class="small text-muted">{{ $row->sku }}</td><td class="text-end fw-semibold">{{ $row->qty }}</td><td class="text-end">{{ $m($row->revenue) }}</td><td class="text-end">{{ $m($row->cost) }}</td><td class="text-end">{{ number_format($margin, 0) }}%</td></tr>
        @empty
            <tr><td colspan="6" class="empty">No sales in this period.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@elseif ($report === 'customers')
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="stat"><div class="label">Customers who bought</div><div class="value">{{ $data['buyers'] }}</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="label">New</div><div class="value">{{ $data['new'] }}</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="label">Returning</div><div class="value">{{ $data['returning'] }}</div><div class="hint">{{ $data['buyers'] ? round($data['returning'] / $data['buyers'] * 100) : 0 }}% came back</div></div></div>
    </div>
    <div class="card-a"><div class="head">Top customers (all time)</div><table class="table table-a"><thead><tr><th>Name</th><th>Phone</th><th class="text-end">Orders</th><th class="text-end">Spent</th></tr></thead><tbody>
        @foreach ($data['top'] as $c)<tr><td><a href="{{ route('admin.customers.show', $c) }}">{{ $c->name }}</a></td><td>{{ $c->phone }}</td><td class="text-end">{{ $c->orders_count }}</td><td class="text-end fw-semibold">{{ $m($c->total_spent) }}</td></tr>@endforeach
    </tbody></table></div>
@elseif ($report === 'inventory')
    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="stat"><div class="label">Units on hand</div><div class="value">{{ number_format($data['value']['units']) }}</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="label">Stock value at cost</div><div class="value">{{ $m($data['value']['cost']) }}</div></div></div>
        <div class="col-md-4"><div class="stat"><div class="label">Stock value at price</div><div class="value">{{ $m($data['value']['retail']) }}</div></div></div>
    </div>
    <div class="card-a"><div class="head">Low and out of stock</div><table class="table table-a"><thead><tr><th>Item</th><th>Warehouse</th><th class="text-end">On hand</th><th class="text-end">Reserved</th><th class="text-end">Available</th></tr></thead><tbody>
        @forelse ($data['low'] as $row)<tr><td>{{ $row->variant->product->name }} <span class="text-muted">{{ $row->variant->name }}</span></td><td>{{ $row->warehouse->name }}</td><td class="text-end">{{ $row->on_hand }}</td><td class="text-end">{{ $row->reserved }}</td><td class="text-end"><span class="badge {{ $row->available() <= 0 ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $row->available() }}</span></td></tr>@empty<tr><td colspan="5" class="empty">All good.</td></tr>@endforelse
    </tbody></table></div>
@else
    <div class="row g-3">
        <div class="col-lg-6"><div class="card-a h-100"><div class="head">Profit and loss</div><table class="table table-a"><tbody>
            @foreach ($data['pl']['rows'] as $row)<tr><td>{{ $row->code }} {{ $row->name }}</td><td class="text-end {{ $row->type === 'expense' ? 'text-danger' : '' }}">{{ $row->type === 'expense' ? '-' : '' }}{{ number_format($row->amount, 2) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>Income</td><td class="text-end">{{ number_format($data['pl']['income'], 2) }}</td></tr>
            <tr class="fw-bold"><td>Expenses</td><td class="text-end">{{ number_format($data['pl']['expenses'], 2) }}</td></tr>
            <tr class="fw-bold fs-6 {{ $data['pl']['profit'] >= 0 ? 'text-success' : 'text-danger' }}"><td>Net profit</td><td class="text-end">{{ $m($data['pl']['profit']) }}</td></tr>
        </tbody></table></div></div>
        <div class="col-lg-6"><div class="card-a h-100"><div class="head">VAT summary by month</div><table class="table table-a"><thead><tr><th>Month</th><th class="text-end">Invoices</th><th class="text-end">Taxable</th><th class="text-end">VAT</th><th class="text-end">Credited</th><th class="text-end">Net VAT</th></tr></thead><tbody>
            @forelse ($data['tax'] as $row)<tr><td>{{ \Carbon\Carbon::parse($row->month.'-01')->format('M Y') }}</td><td class="text-end">{{ $row->invoices }}</td><td class="text-end">{{ number_format($row->taxable, 2) }}</td><td class="text-end">{{ number_format($row->tax, 2) }}</td><td class="text-end">{{ number_format($row->credited_tax, 2) }}</td><td class="text-end fw-semibold">{{ number_format($row->net_tax, 2) }}</td></tr>@empty<tr><td colspan="6" class="empty">No invoices.</td></tr>@endforelse
        </tbody></table></div></div>
    </div>
@endif
@endsection
