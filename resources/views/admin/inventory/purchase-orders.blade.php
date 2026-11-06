@extends('admin.layout', ['title' => 'Purchase orders'])

@section('content')
<div class="page-head">
    <div><h1>Purchase orders</h1><div class="sub">Order stock from suppliers; receiving a PO adds the stock.</div></div>
    <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New purchase order</a>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Number</th><th>Supplier</th><th>Warehouse</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($orders as $po)
        <tr>
            <td><a href="{{ route('admin.purchase-orders.show', $po) }}" class="fw-semibold">{{ $po->number }}</a><div class="small text-muted">{{ $po->ordered_on?->format('j M Y') }}</div></td>
            <td>{{ $po->supplier?->name ?? '—' }}</td>
            <td>{{ $po->warehouse->name }}</td>
            <td>{{ $po->items_count }}</td>
            <td class="fw-semibold">{{ \App\Support\Money::format($po->total) }}</td>
            <td><span class="badge text-bg-{{ ['ordered' => 'warning', 'received' => 'success', 'cancelled' => 'secondary'][$po->status] ?? 'secondary' }}">{{ ucfirst($po->status) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No purchase orders yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
