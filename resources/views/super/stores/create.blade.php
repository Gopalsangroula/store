@extends('super.layout', ['title' => 'New store'])

@section('content')
<form method="POST" action="{{ route('super.stores.store') }}" x-data="{ name: @js(old('name', '')), slug: @js(old('slug', '')), touched: false }">
    @csrf
    <div class="page-head">
        <div><a href="{{ route('super.stores.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Stores</a><h1>Create a client store</h1><div class="sub">The store is ready to sell as soon as it is created: settings, VAT, delivery zones, payment methods, pages and the owner login are set up.</div></div>
        <button class="btn btn-brand">Create store</button>
    </div>
    <div class="row g-3">
        <div class="col-xl-6"><div class="card-a"><div class="head">Store</div><div class="body row g-3">
            <div class="col-12"><label class="form-label">Store name</label><input name="name" x-model="name" @input="if (!touched) slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Address</label><div class="input-group"><input name="slug" x-model="slug" @input="touched = true" class="form-control" required><span class="input-group-text">.{{ config('platform.domain') }}</span></div></div>
            <div class="col-12"><label class="form-label">Custom domain <span class="text-muted fw-normal">(optional)</span></label><input name="domain" class="form-control" value="{{ old('domain') }}" placeholder="shop.clientdomain.com"></div>
            <div class="col-md-6"><label class="form-label">Plan</label><select name="plan_id" class="form-select">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected(old('plan_id') == $p->id)>{{ $p->name }} — Rs. {{ number_format($p->price_monthly) }}/mo</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Billing</label><select name="billing_cycle" class="form-select"><option value="monthly">Monthly</option><option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option></select></div>
            <div class="col-md-3"><label class="form-label">Free trial days</label><input name="trial_days" type="number" min="0" max="90" class="form-control" value="{{ old('trial_days', 14) }}"></div>
        </div></div></div>
        <div class="col-xl-6"><div class="card-a"><div class="head">Owner</div><div class="body row g-3">
            <div class="col-12"><label class="form-label">Owner name</label><input name="owner_name" class="form-control" value="{{ old('owner_name') }}" required></div>
            <div class="col-md-6"><label class="form-label">Owner email</label><input name="owner_email" type="email" class="form-control" value="{{ old('owner_email') }}" required></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone') }}"></div>
            <div class="col-12"><label class="form-label">Password for the owner</label><input name="owner_password" type="password" class="form-control" autocomplete="new-password"><div class="form-text">10+ characters. Not needed if this email already has an account (it keeps its own password).</div></div>
        </div></div></div>
    </div>
</form>
@endsection
