@extends('admin.layout', ['title' => 'Stock overview'])

@section('content')
<div class="page-head">
    <div><h1>Stock overview</h1><div class="sub">Available = on hand − reserved by open orders. Stock value at cost: <strong>{{ \App\Support\Money::format($value) }}</strong></div></div>
    <a href="{{ route('admin.inventory.movements') }}" class="btn btn-light"><i class="bi bi-clock-history"></i> All movements</a>
</div>

<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-6"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search product or SKU"></div>
    <div class="col-md-4"><select name="filter" class="form-select"><option value="">All tracked items</option><option value="low" @selected(request('filter') === 'low')>Low (5 or fewer)</option><option value="out" @selected(request('filter') === 'out')>Out of stock</option></select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Filter</button></div>
</div></form>

<div class="card-a" x-data="{ v: null, label: '' }">
    <div class="table-responsive"><table class="table table-a">
        <thead><tr><th>Item</th><th>SKU</th>@foreach ($warehouses as $w)<th class="text-center">{{ $w->code }}</th>@endforeach<th class="text-center">Reserved</th><th class="text-center">Available</th><th></th></tr></thead>
        <tbody>
        @forelse ($variants as $variant)
            @php $rows = $variant->inventory->keyBy('warehouse_id'); $available = $variant->available(); @endphp
            <tr>
                <td><span class="fw-semibold">{{ $variant->product->name }}</span> <span class="text-muted">{{ $variant->name }}</span></td>
                <td class="small text-muted">{{ $variant->sku }}</td>
                @foreach ($warehouses as $w)<td class="text-center">{{ $rows[$w->id]->on_hand ?? 0 }}</td>@endforeach
                <td class="text-center text-muted">{{ $variant->inventory->sum('reserved') }}</td>
                <td class="text-center"><span class="badge {{ $available <= 0 ? 'text-bg-danger' : ($available <= 5 ? 'text-bg-warning' : 'badge-soft') }}">{{ $available }}</span></td>
                <td class="text-end text-nowrap">
                    @staffcan('inventory.manage')
                        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#adjust" @click="v = {{ $variant->id }}; label = @js($variant->label())">Adjust</button>
                        @if ($warehouses->count() > 1)<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#transfer" @click="v = {{ $variant->id }}; label = @js($variant->label())">Move</button>@endif
                    @endstaffcan
                    <a href="{{ route('admin.inventory.movements', ['variant' => $variant->id]) }}" class="btn btn-sm btn-light" title="History"><i class="bi bi-clock-history"></i></a>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + $warehouses->count() }}" class="empty">Nothing to show.</td></tr>
        @endforelse
        </tbody>
    </table></div>

    <div class="modal fade" id="adjust" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.inventory.adjust') }}" class="modal-content">
        @csrf <input type="hidden" name="variant_id" :value="v">
        <div class="modal-header"><h5 class="modal-title">Adjust stock: <span x-text="label"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">Action</label><select name="mode" class="form-select"><option value="add">Add units</option><option value="remove">Remove units</option><option value="set">Set count to</option></select></div>
            <div class="col-6"><label class="form-label">Quantity</label><input name="quantity" type="number" min="0" class="form-control" required></div>
            <div class="col-12"><label class="form-label">Reason</label><input name="reason" class="form-control" required maxlength="200" placeholder="Stock count, damaged, received from supplier..."></div>
            <div class="col-12"><label class="form-label">Unit cost <span class="text-muted fw-normal">(optional)</span></label><input name="unit_cost" type="number" step="0.01" min="0" class="form-control"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-brand">Save adjustment</button></div>
    </form></div></div>

    <div class="modal fade" id="transfer" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('admin.inventory.transfer') }}" class="modal-content">
        @csrf <input type="hidden" name="variant_id" :value="v">
        <div class="modal-header"><h5 class="modal-title">Move stock: <span x-text="label"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-6"><label class="form-label">From</label><select name="from_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">To</label><select name="to_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}" @selected($loop->index === 1)>{{ $w->name }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">Quantity</label><input name="quantity" type="number" min="1" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Note</label><input name="note" class="form-control" maxlength="200"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-brand">Move stock</button></div>
    </form></div></div>
</div>
@endsection
