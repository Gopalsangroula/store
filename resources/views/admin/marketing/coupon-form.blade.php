@extends('admin.layout', ['title' => $coupon->exists ? $coupon->code : 'New coupon'])

@section('content')
<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" x-data="{ type: '{{ old('type', $coupon->type) }}', applies: '{{ old('applies_to', $coupon->applies_to) }}' }">
    @csrf @if ($coupon->exists) @method('PUT') @endif
    <div class="page-head">
        <div><a href="{{ route('admin.coupons.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Coupons</a><h1>{{ $coupon->exists ? 'Edit '.$coupon->code : 'New coupon' }}</h1></div>
        <button class="btn btn-brand">Save coupon</button>
    </div>
    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card-a mb-3"><div class="body row g-3">
                <div class="col-md-5"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase font-monospace" value="{{ old('code', $coupon->code) }}" required maxlength="50" placeholder="DASHAIN10"></div>
                <div class="col-md-7"><label class="form-label">Name (for you)</label><input name="name" class="form-control" value="{{ old('name', $coupon->name) }}" required maxlength="150"></div>
                <div class="col-md-5"><label class="form-label">Type</label><select name="type" class="form-select" x-model="type">@foreach (\App\Models\Coupon::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-md-3" x-show="type !== 'free_shipping'"><label class="form-label" x-text="type === 'percent' ? 'Percent off' : 'Amount off (Rs.)'"></label><input name="value" type="number" step="0.01" min="0" class="form-control" value="{{ old('value', $coupon->value) }}"></div>
                <div class="col-md-4" x-show="type === 'percent'"><label class="form-label">Max discount (Rs.)</label><input name="max_discount_amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}"></div>
                <div class="col-md-6"><label class="form-label">Minimum order (Rs.)</label><input name="min_order_amount" type="number" step="0.01" min="0" class="form-control" value="{{ old('min_order_amount', $coupon->min_order_amount) }}"></div>
            </div></div>
            <div class="card-a"><div class="head">Applies to</div><div class="body">
                <div class="btn-group mb-3" role="group">
                    @foreach (['all' => 'Whole order', 'products' => 'Some products', 'categories' => 'Some categories'] as $k => $l)
                        <input type="radio" class="btn-check" name="applies_to" id="ap-{{ $k }}" value="{{ $k }}" x-model="applies"><label class="btn btn-outline-dark" for="ap-{{ $k }}">{{ $l }}</label>
                    @endforeach
                </div>
                <div x-show="applies === 'products'" x-cloak><select name="products[]" multiple size="8" class="form-select">@foreach ($products as $p)<option value="{{ $p->id }}" @selected(collect(old('products', $coupon->products?->pluck('id')))->contains($p->id))>{{ $p->name }}</option>@endforeach</select><div class="form-text">Hold Ctrl to choose several.</div></div>
                <div x-show="applies === 'categories'" x-cloak><select name="categories[]" multiple size="6" class="form-select">@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(collect(old('categories', $coupon->categories?->pluck('id')))->contains($c->id))>{{ $c->name }}</option>@endforeach</select></div>
            </div></div>
        </div>
        <div class="col-xl-5">
            <div class="card-a"><div class="head">Limits</div><div class="body row g-3">
                <div class="col-6"><label class="form-label">Starts</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}"></div>
                <div class="col-6"><label class="form-label">Ends</label><input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $coupon->ends_at?->format('Y-m-d\TH:i')) }}"></div>
                <div class="col-6"><label class="form-label">Total uses</label><input name="usage_limit" type="number" min="1" class="form-control" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="Unlimited"></div>
                <div class="col-6"><label class="form-label">Uses per customer</label><input name="usage_limit_per_customer" type="number" min="1" class="form-control" value="{{ old('usage_limit_per_customer', $coupon->usage_limit_per_customer) }}" placeholder="Unlimited"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="first_order_only" value="1" id="first" @checked(old('first_order_only', $coupon->first_order_only))><label class="form-check-label" for="first">First order only (by phone number)</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $coupon->is_active))><label class="form-check-label" for="active">Active</label></div></div>
            </div></div>
        </div>
    </div>
</form>
@endsection
