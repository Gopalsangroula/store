<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>Invoice {{ $invoice->number }} · {{ $store->name }}</title>
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <style>
        body { background: #f1f2f4; font-size: 14px; }
        .sheet { background: #fff; max-width: 820px; margin: 24px auto; padding: 40px; border-radius: 8px; }
        .sheet h1 { font-size: 26px; font-weight: 800; letter-spacing: .04em; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            @if ($store->logoUrl())<img src="{{ $store->logoUrl() }}" alt="" style="max-height:56px" class="mb-2">@endif
            <div class="fw-bold fs-5">{{ $store->name }}</div>
            <div class="text-muted small">{{ $store->address }}<br>{{ $store->phone }} {{ $store->email ? '· '.$store->email : '' }}@if($store->pan_vat_number)<br>PAN/VAT: {{ $store->pan_vat_number }}@endif</div>
        </div>
        <div class="text-end">
            <h1>{{ $invoice->tax_total > 0 ? 'TAX INVOICE' : 'INVOICE' }}</h1>
            <div><strong>{{ $invoice->number }}</strong></div>
            <div class="small text-muted">Issued {{ $invoice->issued_at->format('j M Y') }}<br>Order {{ $order->number }}</div>
            @if ($invoice->status === 'credited')<span class="badge text-bg-secondary">Credited</span>@endif
        </div>
    </div>
    <div class="mb-4">
        <div class="small text-muted text-uppercase fw-bold">Bill to</div>
        <div class="fw-semibold">{{ $invoice->customer_name }}</div>
        <div class="small">{{ $invoice->billing_address }}<br>{{ $invoice->customer_phone }} {{ $invoice->customer_email ? '· '.$invoice->customer_email : '' }}</div>
    </div>
    <table class="table">
        <thead class="table-light"><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Discount</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}@if($item->sku)<div class="small text-muted">{{ $item->sku }}</div>@endif</td>
                <td class="text-end">{{ $item->quantity }}</td>
                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-end">{{ $item->discount_amount > 0 ? number_format($item->discount_amount, 2) : '' }}</td>
                <td class="text-end">{{ number_format($item->unit_price * $item->quantity - $item->discount_amount, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="row justify-content-end"><div class="col-md-5">
        <table class="table table-sm">
            <tr><td>Subtotal</td><td class="text-end">{{ number_format($invoice->subtotal, 2) }}</td></tr>
            @if ($invoice->discount_total > 0)<tr><td>Discount</td><td class="text-end">- {{ number_format($invoice->discount_total, 2) }}</td></tr>@endif
            @if ($invoice->shipping_total > 0)<tr><td>Delivery</td><td class="text-end">{{ number_format($invoice->shipping_total, 2) }}</td></tr>@endif
            <tr><td>Taxable amount</td><td class="text-end">{{ number_format($invoice->taxable_amount, 2) }}</td></tr>
            <tr><td>{{ $invoice->tax_label ?? 'Tax' }}</td><td class="text-end">{{ number_format($invoice->tax_total, 2) }}</td></tr>
            <tr class="fw-bold fs-6"><td>Total ({{ $invoice->currency }})</td><td class="text-end">{{ number_format($invoice->grand_total, 2) }}</td></tr>
        </table>
    </div></div>
    @foreach ($invoice->creditNotes ?? [] as $note)
        <div class="small text-muted">Credit note {{ $note->number }} on {{ $note->issued_at->format('j M Y') }}: {{ number_format($note->amount, 2) }} ({{ $note->reason }})</div>
    @endforeach
    <p class="small text-muted mt-4 mb-0">Thank you for shopping with {{ $store->name }}. This invoice was issued electronically.</p>
    <div class="text-center mt-4 no-print"><button onclick="window.print()" class="btn btn-dark">Print / Save as PDF</button></div>
</div>
</body>
</html>
