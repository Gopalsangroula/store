@extends('admin.layout', ['title' => 'Customers'])

@section('content')
<div class="page-head"><div><h1>Customers</h1><div class="sub">{{ $customers->total() }} people have ordered or signed up.</div></div></div>
<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-7"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, phone or email"></div>
    <div class="col-md-3"><select name="sort" class="form-select"><option value="">Most recent order</option><option value="spent" @selected(request('sort') === 'spent')>Top spenders</option></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Search</button></div>
</div></form>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Name</th><th>Phone</th><th>Account</th><th class="text-end">Orders</th><th class="text-end">Spent</th><th>Last order</th></tr></thead>
    <tbody>
    @forelse ($customers as $c)
        <tr>
            <td><a href="{{ route('admin.customers.show', $c) }}" class="fw-semibold">{{ $c->name }}</a><div class="small text-muted">{{ $c->email }}</div></td>
            <td>{{ $c->phone }}</td>
            <td>{!! $c->hasAccount() ? '<span class="badge text-bg-success">Registered</span>' : '<span class="badge badge-soft">Guest</span>' !!}</td>
            <td class="text-end">{{ $c->orders_count }}</td>
            <td class="text-end fw-semibold">{{ \App\Support\Money::format($c->total_spent) }}</td>
            <td class="small">{{ $c->last_order_at?->format('j M Y') ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No customers yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $customers->links() }}</div>
@endsection
