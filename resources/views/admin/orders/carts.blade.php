@extends('admin.layout', ['title' => 'Abandoned carts'])

@section('content')
<div class="page-head"><div><h1>Abandoned carts</h1><div class="sub">Carts left for over an hour without ordering. A friendly call often wins the sale.</div></div></div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Last active</th><th>Shopper</th><th>Items</th><th class="text-end">Value</th></tr></thead>
    <tbody>
    @forelse ($carts as $cart)
        <tr>
            <td class="small">{{ $cart->updated_at->diffForHumans() }}</td>
            <td>@if ($cart->customer){{ $cart->customer->name }} <a href="tel:{{ $cart->customer->phone }}" class="small">{{ $cart->customer->phone }}</a>@elseif($cart->contact_phone)<a href="tel:{{ $cart->contact_phone }}">{{ $cart->contact_phone }}</a>@else<span class="text-muted">Guest</span>@endif</td>
            <td class="small">@foreach ($cart->items as $item){{ $item->quantity }} × {{ $item->variant?->product?->name }} {{ $item->variant?->name }}@if(! $loop->last), @endif @endforeach</td>
            <td class="text-end fw-semibold">{{ \App\Support\Money::format($cart->items->sum(fn ($i) => (float) $i->variant?->price * $i->quantity)) }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">No abandoned carts.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $carts->links() }}</div>
@endsection
