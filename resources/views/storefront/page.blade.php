@extends('storefront.layout', ['title' => $page->title])

@section('content')
<div class="container py-5" style="max-width: 820px">
    <h1 class="section-title mb-4">{{ $page->title }}</h1>
    <div class="prose">{!! \App\Support\HtmlSanitizer::clean($page->content) !!}</div>
    @if ($page->slug === 'contact')
        <div class="row g-3 mt-4">
            @if ($store->phone)<div class="col-md-4"><div class="panel h-100"><i class="bi bi-telephone fs-4"></i><div class="fw-bold mt-2">Call</div><a href="tel:{{ $store->phone }}">{{ $store->phone }}</a></div></div>@endif
            @if ($store->email)<div class="col-md-4"><div class="panel h-100"><i class="bi bi-envelope fs-4"></i><div class="fw-bold mt-2">Email</div><a href="mailto:{{ $store->email }}">{{ $store->email }}</a></div></div>@endif
            @if ($store->address)<div class="col-md-4"><div class="panel h-100"><i class="bi bi-geo-alt fs-4"></i><div class="fw-bold mt-2">Visit</div>{{ $store->address }}</div></div>@endif
        </div>
    @endif
</div>
@endsection
