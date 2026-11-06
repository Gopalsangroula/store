@extends('admin.layout', ['title' => 'Invoices'])

@section('content')
<div class="page-head">
    <div><h1>Sales invoices</h1><div class="sub">Issued automatically when an order is paid. Numbers run in order and are never reused.</div></div>
    <form class="d-flex gap-2"><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Number or customer"><button class="btn btn-outline-dark">Search</button></form>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Invoice</th><th>Order</th><th>Customer</th><th class="text-end">Taxable</th><th class="text-end">VAT</th><th class="text-end">Total</th><th>Status</th></tr></thead>
    <tbody>
    @forelse ($invoices as $invoice)
        <tr>
            <td><a href="{{ route('admin.orders.invoice', $invoice->order_id) }}" target="_blank" class="fw-semibold">{{ $invoice->number }}</a><div class="small text-muted">{{ $invoice->issued_at->format('j M Y') }}</div></td>
            <td><a href="{{ route('admin.orders.show', $invoice->order_id) }}">{{ $invoice->order?->number }}</a></td>
            <td>{{ $invoice->customer_name }}</td>
            <td class="text-end">{{ number_format($invoice->taxable_amount, 2) }}</td>
            <td class="text-end">{{ number_format($invoice->tax_total, 2) }}</td>
            <td class="text-end fw-semibold">{{ number_format($invoice->grand_total, 2) }}</td>
            <td><span class="badge {{ $invoice->status === 'issued' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($invoice->status) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">No invoices yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $invoices->links() }}</div>
@endsection
