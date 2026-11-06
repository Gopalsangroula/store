@extends('storefront.layout', ['title' => 'My account'])

@section('content')
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div><div class="section-kicker">My account</div><h1 class="section-title h2 mb-0">Namaste, {{ $customer->name }}</h1></div>
        <form method="POST" action="{{ route('shop.account.logout') }}">@csrf<button class="btn btn-outline-dark btn-sm">Sign out</button></form>
    </div>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel p-0">
                <div class="p-3 border-bottom fw-bold">My orders</div>
                @forelse ($orders as $order)
                    <a href="{{ route('shop.order', $order->number) }}" class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-bottom text-decoration-none">
                        <div><div class="fw-semibold">{{ $order->number }}</div><div class="small text-muted">{{ $order->placed_at->format('j M Y') }} · {{ $order->items->sum('quantity') }} items</div></div>
                        <div class="text-end"><div class="fw-bold">{{ $order->money($order->grand_total) }}</div><span class="badge text-bg-{{ $order->statusColor() }}">{{ $order->statusLabel() }}</span></div>
                    </a>
                @empty
                    <div class="p-4 text-center text-muted">No orders yet. <a href="{{ route('shop.shop') }}">Start shopping</a></div>
                @endforelse
            </div>
            <div class="mt-3">{{ $orders->links() }}</div>
        </div>
        <div class="col-lg-4">
            <form method="POST" action="{{ route('shop.account.update') }}" class="panel">
                @csrf @method('PUT')
                <h2 class="h6 fw-bold mb-3">My details</h2>
                <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $customer->name }}" required></div>
                <div class="mb-2"><label class="form-label">Mobile</label><input class="form-control" value="{{ $customer->phone }}" disabled></div>
                <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ $customer->email }}"></div>
                <h3 class="h6 fw-bold mb-2">Default delivery address</h3>
                <div class="mb-2"><input name="city" class="form-control" placeholder="City" value="{{ $address?->city }}"></div>
                <div class="mb-2"><input name="area" class="form-control" placeholder="Area / Tole" value="{{ $address?->area }}"></div>
                <div class="mb-2"><input name="address_line" class="form-control" placeholder="Street address" value="{{ $address?->address_line }}"></div>
                <div class="mb-3"><input name="landmark" class="form-control" placeholder="Landmark" value="{{ $address?->landmark }}"></div>
                <button class="btn btn-ink w-100">Save</button>
            </form>
        </div>
    </div>
</div>
@endsection
