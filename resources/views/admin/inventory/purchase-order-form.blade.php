@extends('admin.layout', ['title' => 'New purchase order'])

@section('content')
<form method="POST" action="{{ route('admin.purchase-orders.store') }}"
      x-data="{ variants: @js($variants), items: [{ variant_id: '', quantity: 1, unit_cost: '' }],
                total() { return this.items.reduce((s, i) => s + (Number(i.quantity) || 0) * (Number(i.unit_cost) || 0), 0) },
                pick(i) { const v = this.variants.find(x => x.id == this.items[i].variant_id); if (v && !this.items[i].unit_cost) this.items[i].unit_cost = v.cost || '' } }">
    @csrf
    <div class="page-head">
        <div><a href="{{ route('admin.purchase-orders.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Purchase orders</a><h1>New purchase order</h1></div>
        <button class="btn btn-brand">Create purchase order</button>
    </div>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card-a">
                <div class="head">Items <button type="button" class="btn btn-sm btn-light" @click="items.push({ variant_id: '', quantity: 1, unit_cost: '' })"><i class="bi bi-plus"></i> Add line</button></div>
                <div class="table-responsive"><table class="table table-a">
                    <thead><tr><th>Item</th><th style="width:120px">Qty</th><th style="width:150px">Unit cost</th><th class="text-end">Line</th><th></th></tr></thead>
                    <tbody>
                    <template x-for="(item, i) in items" :key="i">
                        <tr>
                            <td><select class="form-select form-select-sm" :name="`items[${i}][variant_id]`" x-model="item.variant_id" @change="pick(i)" required><option value="">Choose...</option><template x-for="v in variants" :key="v.id"><option :value="v.id" x-text="v.label"></option></template></select></td>
                            <td><input class="form-control form-control-sm" type="number" min="1" :name="`items[${i}][quantity]`" x-model="item.quantity" required></td>
                            <td><input class="form-control form-control-sm" type="number" min="0" step="0.01" :name="`items[${i}][unit_cost]`" x-model="item.unit_cost" required></td>
                            <td class="text-end" x-text="'Rs. ' + ((Number(item.quantity) || 0) * (Number(item.unit_cost) || 0)).toLocaleString('en-IN')"></td>
                            <td><button type="button" class="btn btn-sm btn-light text-danger" @click="items.splice(i, 1)" x-show="items.length > 1"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    </template>
                    </tbody>
                    <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end" x-text="'Rs. ' + total().toLocaleString('en-IN')"></th><th></th></tr></tfoot>
                </table></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card-a"><div class="body">
                <div class="mb-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">None</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select><div class="form-text"><a href="{{ route('admin.suppliers.index') }}">Manage suppliers</a></div></div>
                <div class="mb-3"><label class="form-label">Receive into</label><select name="warehouse_id" class="form-select">@foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Order date</label><input type="date" name="ordered_on" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div><label class="form-label">Note</label><textarea name="note" rows="3" class="form-control"></textarea></div>
            </div></div>
        </div>
    </div>
</form>
@endsection
