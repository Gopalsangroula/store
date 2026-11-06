@extends('admin.layout', ['title' => 'Payments'])

@section('content')
<div class="page-head"><div><h1>Payments</h1><div class="sub">Every payment attempt, from every method.</div></div></div>
<div class="row g-3 mb-3">
    @foreach ($totals as $row)
        <div class="col-6 col-lg-3"><div class="stat"><div class="label">{{ \App\Models\PaymentMethod::CODES[$row->method] ?? $row->method }}</div><div class="value fs-4">{{ \App\Support\Money::format($row->total) }}</div><div class="hint">{{ $row->c }} completed</div></div></div>
    @endforeach
</div>
<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-5"><select name="method" class="form-select"><option value="">All methods</option>@foreach (\App\Models\PaymentMethod::CODES as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-5"><select name="status" class="form-select"><option value="">Any status</option>@foreach (\App\Models\Payment::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Filter</button></div>
</div></form>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>When</th><th>Order</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($payments as $p)
        <tr>
            <td class="small">{{ ($p->completed_at ?? $p->created_at)->format('j M Y, g:i A') }}</td>
            <td><a href="{{ route('admin.orders.show', $p->order_id) }}">{{ $p->order?->number }}</a></td>
            <td>{{ $p->methodLabel() }}</td>
            <td class="small text-muted">{{ $p->transaction_id ?: $p->payer_reference ?: $p->gateway_reference ?: '—' }}</td>
            <td class="text-end fw-semibold">{{ \App\Support\Money::format($p->amount, $p->currency) }}</td>
            <td><span class="badge text-bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No payments.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
