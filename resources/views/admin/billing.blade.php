@extends('admin.layout', ['title' => 'Plan & billing'])

@php $m = fn ($v) => \App\Support\Money::format($v, 'NPR'); @endphp

@section('content')
<div class="page-head"><div><h1>Plan & billing</h1><div class="sub">Your subscription to {{ config('platform.name') }}. This is separate from your store's own sales.</div></div></div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card-a h-100">
            <div class="head">Current plan</div>
            <div class="body">
                <div class="fs-4 fw-bold">{{ $store->plan?->name ?? 'No plan' }}</div>
                @if ($subscription)
                    <div class="text-muted">{{ $m($subscription->price) }} / {{ $subscription->billing_cycle === 'yearly' ? 'year' : 'month' }}</div>
                    <div class="mt-2">Paid until <strong>{{ $subscription->ends_at->format('j M Y') }}</strong> @if($subscription->ends_at->isPast())<span class="badge text-bg-danger">Expired</span>@endif</div>
                @endif
                <hr>
                @php $plan = $store->plan; @endphp
                <div class="small">
                    <div class="d-flex justify-content-between"><span>Products</span><span>{{ $usage['products'] }} / {{ $plan?->max_products ?? '∞' }}</span></div>
                    <div class="d-flex justify-content-between"><span>Staff accounts</span><span>{{ $usage['staff'] }} / {{ $plan?->max_staff ?? '∞' }}</span></div>
                    <div class="d-flex justify-content-between"><span>Orders this month</span><span>{{ $usage['orders'] }} / {{ $plan?->max_orders_per_month ?? '∞' }}</span></div>
                </div>
                <p class="small text-muted mt-3 mb-0">To change plan or pay an invoice, contact Appharu. Payments are recorded by the platform team.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-a h-100">
            <div class="head">Invoices from {{ config('platform.name') }}</div>
            <div class="table-responsive">
                <table class="table table-a">
                    <thead><tr><th>Number</th><th>Period</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="fw-semibold">{{ $invoice->number }}<div class="small text-muted">Due {{ $invoice->due_on->format('j M Y') }}</div></td>
                            <td class="small">{{ $invoice->period_start?->format('j M Y') }} – {{ $invoice->period_end?->format('j M Y') }}</td>
                            <td>{{ $m($invoice->amount) }}</td>
                            <td><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'void' ? 'secondary' : 'warning') }}">{{ ucfirst($invoice->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">No invoices yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card-a">
    <div class="head">Plans</div>
    <div class="body">
        <div class="row g-3">
            @foreach ($plans as $p)
                <div class="col-md-4">
                    <div class="border rounded-4 p-3 h-100 @if($p->id === $store->plan_id) border-dark border-2 @endif">
                        <div class="fw-bold">{{ $p->name }} @if($p->id === $store->plan_id)<span class="badge text-bg-dark">Current</span>@endif</div>
                        <div class="fs-5 fw-bold">{{ $m($p->price_monthly) }}<span class="small text-muted fw-normal">/month</span></div>
                        <div class="small text-muted mb-2">{{ $p->description }}</div>
                        <ul class="small ps-3 mb-0">
                            @foreach ($p->features ?? array_keys(\App\Models\Plan::FEATURES) as $f)<li>{{ \App\Models\Plan::FEATURES[$f] ?? $f }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
