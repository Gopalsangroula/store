@extends('admin.layout', ['title' => 'Banners'])

@section('content')
<div class="page-head"><div><h1>Banners</h1><div class="sub">The two promo cards under the hero on your home page (the first two active ones are shown).</div></div></div>
<div class="row g-3">
    <div class="col-lg-7">
        @forelse ($banners as $banner)
            <div class="card-a mb-2"><div class="body d-flex gap-3 align-items-center">
                @if ($banner->imageUrl())<img src="{{ $banner->imageUrl() }}" class="thumb" style="width:64px;height:64px" alt="">@endif
                <div class="flex-grow-1"><div class="small text-muted">{{ $banner->eyebrow }}</div><div class="fw-bold">{{ $banner->title }}</div><div class="small">{{ $banner->subtitle }}</div></div>
                <span class="badge {{ $banner->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $banner->is_active ? 'Shown' : 'Hidden' }}</span>
                <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Delete this banner?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            </div></div>
        @empty
            <div class="card-a"><div class="empty">No banners. Without banners the home page goes straight to products.</div></div>
        @endforelse
    </div>
    <div class="col-lg-5">
        <form method="POST" enctype="multipart/form-data" action="{{ $editing->exists ? route('admin.banners.update', $editing) : route('admin.banners.store') }}" class="card-a">
            @csrf @if ($editing->exists) @method('PUT') @endif
            <div class="head">{{ $editing->exists ? 'Edit banner' : 'Add banner' }} @if($editing->exists)<a href="{{ route('admin.banners.index') }}" class="small fw-normal">Cancel</a>@endif</div>
            <div class="body">
                <div class="mb-2"><label class="form-label">Small heading</label><input name="eyebrow" class="form-control" value="{{ old('eyebrow', $editing->eyebrow) }}" placeholder="New arrival"></div>
                <div class="mb-2"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $editing->title) }}" required></div>
                <div class="mb-2"><label class="form-label">Text</label><textarea name="subtitle" rows="2" class="form-control">{{ old('subtitle', $editing->subtitle) }}</textarea></div>
                <div class="row g-2 mb-2"><div class="col-8"><label class="form-label">Link</label><input name="link" class="form-control" value="{{ old('link', $editing->link) }}" placeholder="/product/..."></div><div class="col-4"><label class="form-label">Button</label><input name="button_text" class="form-control" value="{{ old('button_text', $editing->button_text) }}"></div></div>
                <div class="row g-2 mb-3"><div class="col-8"><label class="form-label">Picture</label><input type="file" name="image" accept="image/*" class="form-control"></div><div class="col-4"><label class="form-label">Sort</label><input name="sort" type="number" min="0" class="form-control" value="{{ old('sort', $editing->sort ?? 0) }}"></div></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="b-active" @checked(old('is_active', $editing->is_active))><label class="form-check-label" for="b-active">Show on home page</label></div>
                <button class="btn btn-brand w-100">Save banner</button>
            </div>
        </form>
    </div>
</div>
@endsection
