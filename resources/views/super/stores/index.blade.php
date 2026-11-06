@extends('super.layout', ['title' => 'Stores'])

@section('content')
<div class="page-head">
    <div><h1>Stores</h1><div class="sub">{{ $stores->total() }} client stores</div></div>
    <a href="{{ route('super.stores.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New store</a>
</div>
<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-7"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or slug"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">Any status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Search</button></div>
</div></form>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Store</th><th>Owner</th><th>Plan</th><th>Paid until</th><th class="text-end">Orders (30d)</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach ($stores as $s)
        <tr>
            <td><a href="{{ route('super.stores.show', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted">{{ $s->primaryDomain() }}</div></td>
            <td class="small">{{ $s->owner?->name }}<div class="text-muted">{{ $s->owner?->email }}</div></td>
            <td>{{ $s->plan?->name ?? '—' }}</td>
            <td class="small">@if($s->currentSubscription){{ $s->currentSubscription->ends_at->format('j M Y') }}@if($s->currentSubscription->ends_at->isPast()) <span class="badge text-bg-danger">Overdue</span>@endif @else — @endif</td>
            <td class="text-end">{{ $orders[$s->id] ?? 0 }}</td>
            <td><span class="badge {{ $s->status === 'active' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($s->status) }}</span></td>
            <td class="text-end text-nowrap"><a href="{{ $s->url('/') }}" target="_blank" class="btn btn-sm btn-light" title="Open store"><i class="bi bi-box-arrow-up-right"></i></a> <a href="{{ $s->url('/admin') }}" target="_blank" class="btn btn-sm btn-light" title="Store admin"><i class="bi bi-speedometer2"></i></a></td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
<div class="mt-3">{{ $stores->links() }}</div>
@endsection
