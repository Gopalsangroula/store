@extends('admin.layout', ['title' => 'Credit notes'])

@section('content')
<div class="page-head"><div><h1>Credit notes</h1><div class="sub">Issued for refunds. Invoices are never edited; a credit note reduces them instead.</div></div></div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Number</th><th>Invoice</th><th>Order</th><th>Reason</th><th class="text-end">VAT</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    @forelse ($notes as $note)
        <tr>
            <td class="fw-semibold">{{ $note->number }}<div class="small text-muted">{{ $note->issued_at->format('j M Y') }}</div></td>
            <td>{{ $note->invoice?->number }}</td>
            <td><a href="{{ route('admin.orders.show', $note->order_id) }}">{{ $note->order?->number }}</a></td>
            <td class="small">{{ $note->reason }}</td>
            <td class="text-end">{{ number_format($note->tax_amount, 2) }}</td>
            <td class="text-end fw-semibold">{{ number_format($note->amount, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty">No credit notes.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $notes->links() }}</div>
@endsection
