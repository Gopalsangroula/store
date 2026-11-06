@extends('admin.layout', ['title' => 'Products'])

@section('content')
<div class="page-head">
    <div><h1>Products</h1><div class="sub">{{ $products->total() }} products</div></div>
    @staffcan('products.manage')<a href="{{ route('admin.products.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add product</a>@endstaffcan
</div>

<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name or SKU"></div>
    <div class="col-md-3"><select name="category" class="form-select"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><select name="status" class="form-select"><option value="">Any status</option>@foreach (['active', 'draft', 'archived'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Filter</button></div>
</div></form>

<div class="card-a">
    <div class="table-responsive">
        <table class="table table-a">
            <thead><tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($products as $product)
                @php $stock = $product->variants->sum(fn ($v) => $v->track_inventory ? $v->available() : 0); $tracked = $product->variants->contains('track_inventory', true); @endphp
                <tr>
                    <td style="width:56px"><img src="{{ $product->imageUrl() }}" class="thumb" alt=""></td>
                    <td><a href="{{ route('admin.products.edit', $product) }}" class="fw-semibold text-reset">{{ $product->name }}</a> @if($product->is_featured)<i class="bi bi-star-fill text-warning small" title="Featured"></i>@endif<div class="small text-muted">{{ $product->variants->count() }} {{ \Illuminate\Support\Str::plural('variant', $product->variants->count()) }} · {{ $product->variants->pluck('sku')->take(2)->implode(', ') }}</div></td>
                    <td class="small">{{ $product->category?->name ?? '—' }}</td>
                    <td class="fw-semibold">{{ \App\Support\Money::format($product->variants->min('price')) }}@if($product->variants->min('price') != $product->variants->max('price')) – {{ \App\Support\Money::format($product->variants->max('price')) }}@endif</td>
                    <td>@if ($tracked)<span class="badge {{ $stock <= 0 ? 'text-bg-danger' : ($stock <= 5 ? 'text-bg-warning' : 'badge-soft') }}">{{ $stock }} available</span>@else<span class="text-muted small">Not tracked</span>@endif</td>
                    <td><span class="badge {{ $product->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($product->status) }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ $store->url('/product/'.$product->slug) }}" target="_blank" class="btn btn-sm btn-light" title="View in store"><i class="bi bi-eye"></i></a>
                        @staffcan('products.manage')<a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>@endstaffcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">No products found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
