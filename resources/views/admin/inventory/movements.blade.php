@extends('admin.layout', ['title' => 'Stock movements'])

@section('content')
<div class="page-head">
    <div><h1>Stock movements</h1><div class="sub">Every change to stock, in order. Movements are never edited or deleted.</div></div>
    <form class="d-flex gap-2">
        @if (request('variant'))<input type="hidden" name="variant" value="{{ request('variant') }}">@endif
        <select name="type" class="form-select" onchange="this.form.submit()"><option value="">All types</option>@foreach (\App\Models\InventoryTransaction::TYPES as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select>
        @if (request('variant'))<a href="{{ route('admin.inventory.movements') }}" class="btn btn-light text-nowrap">All items</a>@endif
    </form>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>When</th><th>Item</th><th>Warehouse</th><th>Type</th><th class="text-end">Change</th><th class="text-end">Balance</th><th>Note</th><th>By</th></tr></thead>
    <tbody>
    @forelse ($movements as $m)
        <tr>
            <td class="small text-nowrap">{{ $m->created_at->format('j M Y, g:i A') }}</td>
            <td>{{ $m->variant?->product?->name }} <span class="text-muted small">{{ $m->variant?->name }}</span></td>
            <td class="small">{{ $m->warehouse?->code }}</td>
            <td><span class="badge badge-soft">{{ \App\Models\InventoryTransaction::TYPES[$m->type] ?? $m->type }}</span></td>
            <td class="text-end fw-bold {{ $m->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
            <td class="text-end">{{ $m->balance_after }}</td>
            <td class="small">{{ $m->note }}</td>
            <td class="small text-muted">{{ $m->user?->name ?? 'System' }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">No movements.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
