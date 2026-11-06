@extends('super.layout', ['title' => 'Plans'])

@section('content')
<div class="page-head">
    <div><h1>Plans</h1><div class="sub">What clients pay and which modules they get.</div></div>
    <a href="{{ route('super.plans.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New plan</a>
</div>
<div class="row g-3">
    @foreach ($plans as $plan)
        <div class="col-md-6 col-xl-4"><div class="card-a h-100"><div class="body">
            <div class="d-flex justify-content-between"><h2 class="h5 fw-bold">{{ $plan->name }}</h2><span class="badge {{ $plan->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $plan->is_active ? 'On sale' : 'Hidden' }}</span></div>
            <div class="fs-4 fw-bold">Rs. {{ number_format($plan->price_monthly) }}<span class="fs-6 text-muted fw-normal">/month</span></div>
            <div class="small text-muted mb-2">Rs. {{ number_format($plan->price_yearly) }}/year · setup Rs. {{ number_format($plan->setup_fee) }}</div>
            <div class="small mb-2">{{ $plan->max_products ?? '∞' }} products · {{ $plan->max_staff ?? '∞' }} staff · {{ $plan->max_orders_per_month ?? '∞' }} orders/month</div>
            <ul class="small ps-3">@foreach ($plan->features ?? [] as $f)<li>{{ \App\Models\Plan::FEATURES[$f] ?? $f }}</li>@endforeach</ul>
            <div class="d-flex justify-content-between align-items-center"><span class="small text-muted">{{ $plan->stores_count }} stores</span><a href="{{ route('super.plans.edit', $plan) }}" class="btn btn-sm btn-light">Edit</a></div>
        </div></div></div>
    @endforeach
</div>
@endsection
