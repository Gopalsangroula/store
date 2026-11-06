<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="robots" content="noindex">
    <title>Packing slip {{ $order->number }}</title>
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <style>body { font-size: 14px; } .sheet { max-width: 720px; margin: 24px auto; } @media print { .no-print { display: none; } .sheet { margin: 0; } }</style>
</head>
<body>
<div class="sheet">
    <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
        <div><div class="fw-bold fs-5">{{ $store->name }}</div><div class="small">{{ $store->phone }}</div></div>
        <div class="text-end"><div class="fw-bold fs-4">{{ $order->number }}</div><div class="small">{{ $order->placed_at->format('j M Y') }}</div></div>
    </div>
    <div class="row mb-3">
        <div class="col-7 border rounded p-3">
            <div class="small text-muted text-uppercase fw-bold">Deliver to</div>
            <div class="fs-5 fw-bold">{{ $order->customer_name }}</div>
            <div class="fs-6">{{ $order->customer_phone }}</div>
            <div>{{ $order->fullAddress() }}</div>
            @if ($order->shipping_landmark)<div>Near {{ $order->shipping_landmark }}</div>@endif
        </div>
        <div class="col-5 text-center d-flex flex-column justify-content-center">
            @if ($order->payment_method === 'cod' && ! $order->isPaid())
                <div class="border border-3 border-dark rounded p-2"><div class="fw-bold">COLLECT CASH</div><div class="fs-3 fw-bold">{{ $order->money($order->balanceDue()) }}</div></div>
            @else
                <div class="border border-3 border-success rounded p-2 text-success"><div class="fw-bold fs-4">PAID</div><div class="small">Do not collect money</div></div>
            @endif
        </div>
    </div>
    <table class="table table-bordered">
        <thead class="table-light"><tr><th style="width:40px"></th><th>Item</th><th>SKU</th><th class="text-end">Qty</th></tr></thead>
        <tbody>@foreach ($order->items as $item)<tr><td>☐</td><td>{{ $item->product_name }} {{ $item->variant_name }}</td><td>{{ $item->sku }}</td><td class="text-end fw-bold">{{ $item->quantity }}</td></tr>@endforeach</tbody>
    </table>
    @if ($order->customer_note)<div class="border rounded p-2 small"><strong>Customer note:</strong> {{ $order->customer_note }}</div>@endif
    <div class="text-center mt-4 no-print"><button onclick="window.print()" class="btn btn-dark">Print</button></div>
</div>
</body>
</html>
