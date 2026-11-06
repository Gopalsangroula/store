@extends('super.layout', ['title' => $plan->exists ? $plan->name : 'New plan'])

@section('content')
<form method="POST" action="{{ $plan->exists ? route('super.plans.update', $plan) : route('super.plans.store') }}">
    @csrf @if ($plan->exists) @method('PUT') @endif
    <div class="page-head"><div><a href="{{ route('super.plans.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Plans</a><h1>{{ $plan->exists ? $plan->name : 'New plan' }}</h1></div><button class="btn btn-brand">Save plan</button></div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="card-a"><div class="body row g-3">
            <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $plan->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Slug</label><input name="slug" class="form-control" value="{{ old('slug', $plan->slug) }}" required></div>
            <div class="col-12"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ old('description', $plan->description) }}"></div>
            <div class="col-md-4"><label class="form-label">Monthly (Rs.)</label><input name="price_monthly" type="number" step="0.01" min="0" class="form-control" value="{{ old('price_monthly', $plan->price_monthly ?? 0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Yearly (Rs.)</label><input name="price_yearly" type="number" step="0.01" min="0" class="form-control" value="{{ old('price_yearly', $plan->price_yearly ?? 0) }}" required></div>
            <div class="col-md-4"><label class="form-label">Setup fee (Rs.)</label><input name="setup_fee" type="number" step="0.01" min="0" class="form-control" value="{{ old('setup_fee', $plan->setup_fee ?? 0) }}"></div>
            <div class="col-md-4"><label class="form-label">Max products</label><input name="max_products" type="number" min="1" class="form-control" value="{{ old('max_products', $plan->max_products) }}" placeholder="Unlimited"></div>
            <div class="col-md-4"><label class="form-label">Max staff</label><input name="max_staff" type="number" min="1" class="form-control" value="{{ old('max_staff', $plan->max_staff) }}" placeholder="Unlimited"></div>
            <div class="col-md-4"><label class="form-label">Orders / month</label><input name="max_orders_per_month" type="number" min="1" class="form-control" value="{{ old('max_orders_per_month', $plan->max_orders_per_month) }}" placeholder="Unlimited"></div>
            <div class="col-md-4"><label class="form-label">Sort</label><input name="sort" type="number" min="0" class="form-control" value="{{ old('sort', $plan->sort ?? 0) }}"></div>
            <div class="col-md-8 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $plan->is_active))><label class="form-check-label" for="act">On sale</label></div></div>
        </div></div></div>
        <div class="col-xl-5"><div class="card-a"><div class="head">Modules included</div><div class="body">
            @foreach (\App\Models\Plan::FEATURES as $key => $label)
                <div class="form-check"><input class="form-check-input" type="checkbox" name="features[]" value="{{ $key }}" id="f-{{ $key }}" @checked(in_array($key, old('features', $plan->features ?? [])))><label class="form-check-label" for="f-{{ $key }}">{{ $label }}</label></div>
            @endforeach
            <div class="form-text mt-2">Catalog, orders, cash on delivery and bank transfer are in every plan.</div>
        </div></div></div>
    </div>
</form>
@endsection
