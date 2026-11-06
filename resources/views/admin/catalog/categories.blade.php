@extends('admin.layout', ['title' => 'Categories'])

@section('content')
<div class="page-head"><div><h1>Categories</h1><div class="sub">Group products so shoppers can browse them.</div></div></div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-a"><div class="table-responsive"><table class="table table-a">
            <thead><tr><th>Name</th><th>Parent</th><th>Products</th><th>Visible</th><th></th></tr></thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td class="fw-semibold">{{ $category->name }}<div class="small text-muted">/category/{{ $category->slug }}</div></td>
                    <td class="small">{{ $category->parent?->name ?? '—' }}</td>
                    <td>{{ $category->products_count }}</td>
                    <td>{!! $category->is_active ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-eye-slash text-muted"></i>' !!}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No categories yet.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
    </div>
    <div class="col-lg-5">
        <form method="POST" enctype="multipart/form-data" action="{{ $editing->exists ? route('admin.categories.update', $editing) : route('admin.categories.store') }}" class="card-a">
            @csrf @if ($editing->exists) @method('PUT') @endif
            <div class="head">{{ $editing->exists ? 'Edit '.$editing->name : 'Add category' }} @if($editing->exists)<a href="{{ route('admin.categories.index') }}" class="small fw-normal">Cancel</a>@endif</div>
            <div class="body">
                <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $editing->name) }}" required></div>
                <div class="mb-3"><label class="form-label">URL slug</label><input name="slug" class="form-control" value="{{ old('slug', $editing->slug) }}" placeholder="made from the name"></div>
                <div class="mb-3"><label class="form-label">Inside</label><select name="parent_id" class="form-select"><option value="">Top level</option>@foreach ($categories->where('id', '!=', $editing->id) as $c)<option value="{{ $c->id }}" @selected(old('parent_id', $editing->parent_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" rows="2" class="form-control">{{ old('description', $editing->description) }}</textarea></div>
                <div class="row g-2 mb-3"><div class="col-6"><label class="form-label">Sort</label><input name="sort" type="number" min="0" class="form-control" value="{{ old('sort', $editing->sort ?? 0) }}"></div><div class="col-6"><label class="form-label">Picture</label><input type="file" name="image" accept="image/*" class="form-control"></div></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $editing->is_active))><label class="form-check-label" for="active">Show in store</label></div>
                <button class="btn btn-brand w-100">{{ $editing->exists ? 'Save' : 'Add category' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
