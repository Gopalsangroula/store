@extends('super.layout', ['title' => $store->name])

@php $m = fn ($v) => \App\Support\Money::format($v, 'NPR'); @endphp

@section('content')
<div class="page-head">
    <div><a href="{{ route('super.stores.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Stores</a>
        <h1>{{ $store->name }} <span class="badge {{ $store->status === 'active' ? 'text-bg-success' : 'text-bg-danger' }} fs-6 align-middle">{{ ucfirst($store->status) }}</span></h1>
        <div class="sub">{{ $store->primaryDomain() }} · created {{ $store->created_at->format('j M Y') }} @if($store->suspended_reason)· {{ $store->suspended_reason }}@endif</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ $store->url('/') }}" target="_blank" class="btn btn-light"><i class="bi bi-box-arrow-up-right"></i> Store</a>
        <a href="{{ $store->url('/admin') }}" target="_blank" class="btn btn-light"><i class="bi bi-speedometer2"></i> Store admin</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Products</div><div class="value">{{ $stats['products'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Orders</div><div class="value">{{ $stats['orders'] }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Sales (30 days)</div><div class="value fs-4">{{ $m($stats['sales30']) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="stat"><div class="label">Customers</div><div class="value">{{ $stats['customers'] }}</div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card-a mb-3"><div class="head">Subscription invoices <a href="{{ route('super.billing.index', ['store' => $store->id]) }}" class="small fw-normal">Billing</a></div><table class="table table-a">
            <thead><tr><th>Number</th><th>Description</th><th class="text-end">Amount</th><th>Status</th></tr></thead><tbody>
            @forelse ($store->subscriptionInvoices->sortByDesc('id') as $invoice)
                <tr><td class="fw-semibold">{{ $invoice->number }}<div class="small text-muted">due {{ $invoice->due_on->format('j M Y') }}</div></td><td class="small">{{ $invoice->description }}</td><td class="text-end">{{ $m($invoice->amount) }}</td><td><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($invoice->status) }}</span></td></tr>
            @empty
                <tr><td colspan="4" class="empty">No invoices (trial or free plan).</td></tr>
            @endforelse
        </tbody></table></div>

        <div class="card-a mb-3"><div class="head">Staff</div><table class="table table-a"><tbody>
            @foreach ($staff as $member)<tr><td>{{ $member->user->name }} <span class="small text-muted">{{ $member->user->email }}</span></td><td><span class="badge badge-soft">{{ $member->role->name }}</span></td><td class="small">{{ $member->status }}</td></tr>@endforeach
        </tbody></table>
            <form method="POST" action="{{ route('super.stores.owner', $store) }}" class="body border-top d-flex gap-2">@csrf<input name="email" type="email" class="form-control form-control-sm" placeholder="Transfer ownership to (existing account email)" required><button class="btn btn-sm btn-light text-nowrap" onclick="return confirm('Make this person the owner?')">Change owner</button></form>
        </div>

        <div class="card-a"><div class="head">Domains</div><table class="table table-a"><tbody>
            @foreach ($store->domains as $d)<tr><td>{{ $d->domain }} @if($d->is_primary)<span class="badge text-bg-dark">Main</span>@endif</td><td class="text-end"><form method="POST" action="{{ route('super.stores.domains.remove', [$store, $d]) }}" onsubmit="return confirm('Remove domain?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>@endforeach
        </tbody></table>
            <form method="POST" action="{{ route('super.stores.domains.add', $store) }}" class="body border-top d-flex gap-2 align-items-center">@csrf<input name="domain" class="form-control form-control-sm" placeholder="shop.example.com" required><label class="small text-nowrap"><input type="checkbox" name="primary" value="1"> Main</label><button class="btn btn-sm btn-light text-nowrap">Add domain</button></form>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card-a mb-3"><div class="head">Plan</div><form method="POST" action="{{ route('super.stores.plan', $store) }}" class="body">
            @csrf
            <div class="mb-2">Current: <strong>{{ $store->plan?->name ?? 'None' }}</strong>@if($sub = $store->subscriptions->where('status', 'active')->sortByDesc('ends_at')->first()) · until {{ $sub->ends_at->format('j M Y') }}@endif</div>
            <div class="d-flex gap-2"><select name="plan_id" class="form-select form-select-sm">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected($p->id === $store->plan_id)>{{ $p->name }}</option>@endforeach</select><select name="billing_cycle" class="form-select form-select-sm"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select><button class="btn btn-sm btn-brand text-nowrap" onclick="return confirm('Start a new subscription period on this plan?')">Change</button></div>
        </form></div>

        <div class="card-a mb-3"><div class="head">Store details</div><form method="POST" action="{{ route('super.stores.update', $store) }}" class="body">
            @csrf @method('PUT')
            <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $store->name }}" required></div>
            <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ $store->email }}"></div>
            <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $store->phone }}"></div>
            <button class="btn btn-sm btn-brand">Save</button>
        </form></div>

        <div class="card-a"><div class="head">Status</div><form method="POST" action="{{ route('super.stores.status', $store) }}" class="body">
            @csrf
            @if ($store->status === 'active')
                <input type="hidden" name="status" value="suspended">
                <input name="reason" class="form-control mb-2" placeholder="Reason shown to the owner" maxlength="200">
                <button class="btn btn-outline-danger" onclick="return confirm('Suspend this store? Shoppers will see a closed page.')"><i class="bi bi-pause-circle"></i> Suspend store</button>
            @else
                <input type="hidden" name="status" value="active">
                <button class="btn btn-success"><i class="bi bi-play-circle"></i> Reactivate store</button>
            @endif
        </form></div>
    </div>
</div>
@endsection
