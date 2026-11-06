@extends('admin.layout', ['title' => $po->number])

@section('content')
<div class="page-head">
    <div><a href="{{ route('admin.purchase-orders.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Purchase orders</a><h1>{{ $po->number }} <span class="badge text-bg-{{ ['ordered' => 'warning', 'received' => 'success', 'cancelled' => 'secondary'][$po->status] ?? 'secondary' }} fs-6 align-middle">{{ ucfirst($po->status) }}</span></h1></div>
    @if ($po->status === 'ordered')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.purchase-orders.cancel', $po) }}" onsubmit="return confirm('Cancel this purchase order?')">@csrf<button class="btn btn-light">Cancel PO</button></form>
            <form method="POST" action="{{ route('admin.purchase-orders.receive', $po) }}" onsubmit="return confirm('Mark all items as received? Stock will be added.')">@csrf<button class="btn btn-accent"><i class="bi bi-box-arrow-in-down"></i> Receive goods</button></form>
        </div>
    @endif
</div>
<div class="row g-3">
    <div class="col-xl-8"><div class="card-a"><div class="table-responsive"><table class="table table-a">
        <thead><tr><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Unit cost</th><th class="text-end">Line</th></tr></thead>
        <tbody>
        @foreach ($po->items as $item)
            <tr><td>{{ $item->variant->label() }} <span class="small text-muted">{{ $item->variant->sku }}</span></td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">{{ $item->received_quantity }}</td><td class="text-end">{{ \App\Support\Money::format($item->unit_cost) }}</td><td class="text-end">{{ \App\Support\Money::format($item->unit_cost * $item->quantity) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-end">{{ \App\Support\Money::format($po->total) }}</th></tr></tfoot>
    </table></div></div></div>
    <div class="col-xl-4"><div class="card-a"><div class="body"><dl class="kv mb-0">
        <dt>Supplier</dt><dd>{{ $po->supplier?->name ?? '—' }}</dd>
        <dt>Warehouse</dt><dd>{{ $po->warehouse->name }}</dd>
        <dt>Ordered</dt><dd>{{ $po->ordered_on?->format('j M Y') }}</dd>
        @if ($po->received_at)<dt>Received</dt><dd>{{ $po->received_at->format('j M Y, g:i A') }}</dd>@endif
        @if ($po->note)<dt>Note</dt><dd>{{ $po->note }}</dd>@endif
    </dl></div></div></div>
</div>
@endsection
