@extends('admin.layout', ['title' => $page->exists ? $page->title : 'New page'])

@section('content')
<form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
    @csrf @if ($page->exists) @method('PUT') @endif
    <div class="page-head">
        <div><a href="{{ route('admin.pages.index') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Pages</a><h1>{{ $page->exists ? $page->title : 'New page' }}</h1></div>
        <button class="btn btn-brand">Save page</button>
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="card-a"><div class="body">
            <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $page->title) }}" required></div>
            <div><label class="form-label">Content</label><textarea name="content" rows="16" class="form-control font-monospace small">{{ old('content', $page->content) }}</textarea><div class="form-text">HTML is allowed: &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;a&gt;, &lt;img&gt;, tables and YouTube or Google Maps embeds. Scripts are removed.</div></div>
        </div></div></div>
        <div class="col-xl-4"><div class="card-a"><div class="body">
            <div class="mb-3"><label class="form-label">Address</label><div class="input-group"><span class="input-group-text">/</span><input name="slug" class="form-control" value="{{ old('slug', $page->slug) }}" placeholder="made from the title"></div></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" @checked(old('is_published', $page->is_published))><label class="form-check-label" for="pub">Published</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="in_footer" value="1" id="foot" @checked(old('in_footer', $page->in_footer))><label class="form-check-label" for="foot">Link in footer</label></div>
        </div></div></div>
    </div>
</form>
@endsection
