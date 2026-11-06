@extends('storefront.layout', ['title' => $category?->name ?? ($search ? 'Search: '.$search : 'Shop')])

@section('content')
<div class="container py-4 py-lg-5">
    <nav aria-label="breadcrumb" class="small mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('shop.home') }}" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item @if(! $category) active @endif"><a href="{{ route('shop.shop') }}" class="text-decoration-none">Shop</a></li>
            @if ($category)<li class="breadcrumb-item active">{{ $category->name }}</li>@endif
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="section-title h2 mb-1">{{ $category?->name ?? ($search ? 'Results for "'.$search.'"' : 'Shop all') }}</h1>
            <div class="text-muted small">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</div>
        </div>
        <form class="d-flex gap-2" method="GET">
            @if ($search)<input type="hidden" name="q" value="{{ $search }}">@endif
            <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Sort">
                @foreach (['featured' => 'Featured', 'newest' => 'Newest', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'] as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($categories->count() > 1)
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('shop.shop') }}" @class(['category-chip', 'active' => ! $category])>All</a>
            @foreach ($categories as $item)
                <a href="{{ route('shop.category', $item->slug) }}" @class(['category-chip', 'active' => $category?->id === $item->id])>{{ $item->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="row g-4">
        @forelse ($products as $product)
            <div class="col-sm-6 col-lg-4">@include('storefront.partials.product-card', ['product' => $product])</div>
        @empty
            <div class="col-12"><div class="panel text-center py-5"><i class="bi bi-search fs-2 text-muted"></i><p class="mt-2 mb-0">Nothing found. Try another word or <a href="{{ route('shop.shop') }}">browse everything</a>.</p></div></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</div>
@endsection
