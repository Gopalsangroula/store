@extends('admin.layout', ['title' => 'Pages'])

@section('content')
<div class="page-head">
    <div><h1>Pages</h1><div class="sub">About, contact, returns and privacy. Footer pages are linked at the bottom of every page.</div></div>
    <a href="{{ route('admin.pages.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New page</a>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Title</th><th>Address</th><th>Footer</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach ($pages as $page)
        <tr>
            <td class="fw-semibold">{{ $page->title }}</td>
            <td class="small"><a href="{{ $store->url('/'.$page->slug) }}" target="_blank">/{{ $page->slug }}</a></td>
            <td>{!! $page->in_footer ? '<i class="bi bi-check-circle text-success"></i>' : '—' !!}</td>
            <td><span class="badge {{ $page->is_published ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $page->is_published ? 'Published' : 'Hidden' }}</span></td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="d-inline" onsubmit="return confirm('Delete this page?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
