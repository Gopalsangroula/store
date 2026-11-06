@extends('super.layout', ['title' => 'Dashboard'])

@php $m = fn ($v) => \App\Support\Money::format($v, 'NPR'); @endphp

@section('content')
<div class="page-head">
    <div><h1>Platform overview</h1><div class="sub">{{ now()->format('l, j F Y') }}</div></div>
    <a href="{{ route('super.stores.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New store</a>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Stores</div><div class="value">{{ $stores }}</div><div class="hint">{{ $active }} active · {{ $suspended }} suspended · {{ $newThisMonth }} new this month</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Monthly recurring revenue</div><div class="value">{{ $m($mrr) }}</div><div class="hint">{{ $m($mrr * 12) }} a year</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Collected this month</div><div class="value">{{ $m($collected) }}</div></div></div>
    <div class="col-6 col-xl-3"><a href="{{ route('super.billing.index', ['status' => 'unpaid']) }}" class="text-reset"><div @class(['stat', 'alert-stat' => $outstanding > 0])><div class="label">Outstanding</div><div class="value">{{ $m($outstanding) }}</div><div class="hint">Unpaid subscription invoices</div></div></a></div>
</div>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card-a mb-3"><div class="head">Newest stores <a href="{{ route('super.stores.index') }}" class="small fw-normal">All stores</a></div><div class="table-responsive"><table class="table table-a">
            <thead><tr><th>Store</th><th>Owner</th><th>Plan</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            @foreach ($latest as $s)
                <tr><td><a href="{{ route('super.stores.show', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted">{{ $s->primaryDomain() }}</div></td><td class="small">{{ $s->owner?->name }}</td><td>{{ $s->plan?->name }}</td><td><span class="badge {{ $s->status === 'active' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($s->status) }}</span></td><td class="small">{{ $s->created_at->format('j M Y') }}</td></tr>
            @endforeach
            </tbody>
        </table></div></div>
        <div class="card-a"><div class="head">Renewals in the next 14 days</div><table class="table table-a"><tbody>
            @forelse ($renewals as $sub)
                <tr><td><a href="{{ route('super.stores.show', $sub->store_id) }}">{{ $sub->store?->name }}</a></td><td>{{ $sub->plan->name }} ({{ $sub->billing_cycle }})</td><td>{{ $m($sub->price) }}</td><td class="small">{{ $sub->ends_at->format('j M Y') }} ({{ $sub->ends_at->diffForHumans() }})</td></tr>
            @empty
                <tr><td class="empty">No renewals due.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
    <div class="col-xl-4">
        <div class="card-a mb-3"><div class="head">Active stores by plan</div><ul class="list-group list-group-flush">
            @foreach ($byPlan as $plan)<li class="list-group-item d-flex justify-content-between"><span>{{ $plan->name }} <span class="small text-muted">{{ $m($plan->price_monthly) }}/mo</span></span><strong>{{ $plan->stores_count }}</strong></li>@endforeach
        </ul></div>
        <div class="stat"><div class="label">Sales through all stores (30 days)</div><div class="value">{{ $m($gmv) }}</div><div class="hint">Total order value across clients. Each store's money goes to that store.</div></div>
    </div>
</div>
@endsection
