@extends('admin.layout', ['title' => 'Coupons'])

@section('content')
<div class="page-head">
    <div><h1>Coupons</h1><div class="sub">Discount codes shoppers enter in the cart or at checkout.</div></div>
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New coupon</a>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Code</th><th>Offer</th><th>Rules</th><th class="text-end">Used</th><th class="text-end">Discount given</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse ($coupons as $coupon)
        @php $expired = $coupon->ends_at && $coupon->ends_at->isPast(); @endphp
        <tr>
            <td><span class="font-monospace fw-bold">{{ $coupon->code }}</span><div class="small text-muted">{{ $coupon->name }}</div></td>
            <td>{{ $coupon->describe() }}</td>
            <td class="small text-muted">
                @if ($coupon->min_order_amount) Min {{ \App\Support\Money::format($coupon->min_order_amount) }} · @endif
                @if ($coupon->first_order_only) First order · @endif
                @if ($coupon->applies_to !== 'all') Selected {{ $coupon->applies_to }} · @endif
                @if ($coupon->ends_at) Until {{ $coupon->ends_at->format('j M Y') }} @endif
            </td>
            <td class="text-end">{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
            <td class="text-end">{{ \App\Support\Money::format($coupon->usages_sum_discount_amount ?? 0) }}</td>
            <td><span class="badge {{ ! $coupon->is_active || $expired ? 'text-bg-secondary' : 'text-bg-success' }}">{{ ! $coupon->is_active ? 'Off' : ($expired ? 'Expired' : 'Active') }}</span></td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="d-inline" onsubmit="return confirm('Delete this coupon?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">No coupons yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
@endsection
