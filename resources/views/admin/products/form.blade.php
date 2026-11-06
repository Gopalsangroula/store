@extends('admin.layout', ['title' => $product->exists ? $product->name : 'New product'])

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      x-data="{ variants: @js(collect(old('variants', $variants->values()))->values())
                  .map(v => ({ ...v, track_inventory: [true, 1, '1'].includes(v.track_inventory), allow_preorder: [true, 1, '1'].includes(v.allow_preorder), is_active: [true, 1, '1'].includes(v.is_active) })),
                add() { this.variants.push({ id: null, name: '', sku: '', price: '', compare_at_price: '', cost_price: '', swatch: '', track_inventory: true, allow_preorder: false, is_active: true, stock: 0, opening_stock: 0 }) } }">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <div class="page-head">
        <div>
            <a href="{{ route('admin.products.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Products</a>
            <h1>{{ $product->exists ? $product->name : 'New product' }}</h1>
        </div>
        <div class="d-flex gap-2">
            @if ($product->exists)<a href="{{ $store->url('/product/'.$product->slug) }}" target="_blank" class="btn btn-light"><i class="bi bi-eye"></i> View</a>@endif
            <button class="btn btn-brand"><i class="bi bi-check-lg"></i> Save product</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card-a mb-3"><div class="body">
                <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $product->name) }}" required maxlength="200"></div>
                <div class="mb-3"><label class="form-label">Short description <span class="text-muted fw-normal">(shown near the price)</span></label><textarea name="short_description" rows="2" class="form-control" maxlength="500">{{ old('short_description', $product->short_description) }}</textarea></div>
                <div><label class="form-label">Full description</label><textarea name="description" rows="8" class="form-control font-monospace small" maxlength="60000">{{ old('description', $product->description) }}</textarea><div class="form-text">Basic HTML is allowed: paragraphs, lists, bold, links, images, YouTube embeds. Scripts are removed.</div></div>
            </div></div>

            <div class="card-a mb-3">
                <div class="head">Variants, prices and stock <button type="button" class="btn btn-sm btn-light" @click="add()"><i class="bi bi-plus"></i> Add variant</button></div>
                <div class="table-responsive">
                    <table class="table table-a variant-table">
                        <thead><tr><th>Option</th><th>SKU</th><th>Price</th><th>Compare at</th><th>Cost</th><th>Colour</th><th>Stock</th><th>Settings</th><th></th></tr></thead>
                        <tbody>
                        <template x-for="(v, i) in variants" :key="i">
                            <tr>
                                <td><input type="hidden" :name="`variants[${i}][id]`" :value="v.id"><input class="form-control form-control-sm" :name="`variants[${i}][name]`" x-model="v.name" placeholder="e.g. Black" maxlength="120"></td>
                                <td><input class="form-control form-control-sm text-uppercase" :name="`variants[${i}][sku]`" x-model="v.sku" required maxlength="100"></td>
                                <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" :name="`variants[${i}][price]`" x-model="v.price" required></td>
                                <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" :name="`variants[${i}][compare_at_price]`" x-model="v.compare_at_price" title="Old price, shown crossed out"></td>
                                <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" :name="`variants[${i}][cost_price]`" x-model="v.cost_price" title="What you paid, for profit reports"></td>
                                <td><input class="form-control form-control-sm form-control-color" type="color" :name="`variants[${i}][swatch]`" :value="v.swatch || '#222326'" @input="v.swatch = $event.target.value" style="width:42px"></td>
                                <td>
                                    <template x-if="v.id"><span class="small"><span x-text="v.stock"></span> on hand<br><a href="{{ route('admin.inventory.index') }}" class="small">Adjust</a></span></template>
                                    <template x-if="!v.id"><input class="form-control form-control-sm" type="number" min="0" :name="`variants[${i}][opening_stock]`" x-model="v.opening_stock" title="Opening stock in your main warehouse"></template>
                                </td>
                                <td class="small text-nowrap">
                                    <input type="hidden" :name="`variants[${i}][track_inventory]`" :value="v.track_inventory ? 1 : 0">
                                    <input type="hidden" :name="`variants[${i}][allow_preorder]`" :value="v.allow_preorder ? 1 : 0">
                                    <input type="hidden" :name="`variants[${i}][is_active]`" :value="v.is_active ? 1 : 0">
                                    <label class="d-block"><input type="checkbox" x-model="v.track_inventory"> Track stock</label>
                                    <label class="d-block"><input type="checkbox" x-model="v.allow_preorder"> Pre-order when sold out</label>
                                    <label class="d-block"><input type="checkbox" x-model="v.is_active"> For sale</label>
                                    <input type="file" :name="`variants[${i}][image]`" accept="image/*" class="form-control form-control-sm mt-1" title="Picture for this option">
                                </td>
                                <td><button type="button" class="btn btn-sm btn-light text-danger" @click="variants.splice(i, 1)" x-show="variants.length > 1" aria-label="Remove variant"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
                <div class="body pt-0 small text-muted">Prices include VAT if your tax setting says prices are VAT-inclusive. Stock for existing variants is changed in Inventory, so every change is recorded.</div>
            </div>

            <div class="card-a"><div class="head">Search engines</div><div class="body row g-3">
                <div class="col-md-6"><label class="form-label">Page title</label><input name="seo_title" class="form-control" value="{{ old('seo_title', $product->seo_title) }}" maxlength="200"></div>
                <div class="col-md-6"><label class="form-label">URL slug</label><input name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" maxlength="200" placeholder="made from the name"></div>
                <div class="col-12"><label class="form-label">Meta description</label><textarea name="seo_description" rows="2" class="form-control" maxlength="500">{{ old('seo_description', $product->seo_description) }}</textarea></div>
            </div></div>
        </div>

        <div class="col-xl-4">
            <div class="card-a mb-3"><div class="body">
                <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select">@foreach (['active' => 'Active (for sale)', 'draft' => 'Draft (hidden)', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $product->status) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">None</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Brand</label><select name="brand_id" class="form-select"><option value="">None</option>@foreach ($brands as $b)<option value="{{ $b->id }}" @selected(old('brand_id', $product->brand_id) == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Badge</label><input name="badge" class="form-control" value="{{ old('badge', $product->badge) }}" maxlength="60" placeholder="Best seller, Limited time, New"></div>
                <div class="mb-3"><label class="form-label">Sort order</label><input name="sort" type="number" min="0" class="form-control" value="{{ old('sort', $product->sort ?? 0) }}"></div>
                <input type="hidden" name="is_featured" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="featured" @checked(old('is_featured', $product->is_featured))><label class="form-check-label" for="featured">Feature on the home page</label></div>
                <input type="hidden" name="is_taxable" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_taxable" value="1" id="taxable" @checked(old('is_taxable', $product->is_taxable ?? true))><label class="form-check-label" for="taxable">VAT applies</label></div>
            </div></div>

            <div class="card-a mb-3">
                <div class="head">Pictures</div>
                <div class="body">
                    @if ($product->exists && $product->images->isNotEmpty())
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ($product->images as $image)
                                <div class="position-relative">
                                    <img src="{{ $image->url() }}" class="thumb" style="width:80px;height:80px" alt="">
                                    <button form="del-image-{{ $image->id }}" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1" aria-label="Remove picture" onclick="return confirm('Remove this picture?')"><i class="bi bi-x"></i></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control">
                    <div class="form-text">JPG, PNG or WebP up to 4 MB each. The first picture is the main one.</div>
                </div>
            </div>

            @if ($product->exists)
                <button form="delete-product" class="btn btn-outline-danger w-100" onclick="return confirm('Remove this product from the store? Past orders are kept.')"><i class="bi bi-trash"></i> Delete product</button>
            @endif
        </div>
    </div>
</form>

@if ($product->exists)
    @foreach ($product->images as $image)
        <form id="del-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}">@csrf @method('DELETE')</form>
    @endforeach
    <form id="delete-product" method="POST" action="{{ route('admin.products.destroy', $product) }}">@csrf @method('DELETE')</form>
@endif
@endsection
