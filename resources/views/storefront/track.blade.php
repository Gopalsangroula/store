@extends('storefront.layout', ['title' => 'Track your order'])

@section('content')
<div class="container py-5" style="max-width: 520px">
    <div class="text-center mb-4">
        <i class="bi bi-box-seam fs-1"></i>
        <h1 class="section-title h2 mt-2">Track your order</h1>
        <p class="text-muted">Enter your order number and the mobile number you used at checkout.</p>
    </div>
    <form method="POST" action="{{ route('shop.track.lookup') }}" class="panel">
        @csrf
        <div class="mb-3"><label class="form-label" for="number">Order number</label><input id="number" name="number" class="form-control text-uppercase" placeholder="ORD-2026-000001" value="{{ old('number') }}" required></div>
        <div class="mb-3"><label class="form-label" for="phone">Mobile number</label><input id="phone" name="phone" class="form-control" placeholder="98XXXXXXXX" value="{{ old('phone') }}" inputmode="tel" required></div>
        <button class="btn btn-accent w-100 py-2">Find my order</button>
    </form>
</div>
@endsection
