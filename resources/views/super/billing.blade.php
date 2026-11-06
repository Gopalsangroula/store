@extends('super.layout', ['title' => 'Billing'])

@php $m = fn ($v) => \App\Support\Money::format($v, 'NPR'); @endphp

@section('content')
<div class="page-head">
    <div><h1>Subscription billing</h1><div class="sub">Outstanding: <strong>{{ $m($unpaidTotal) }}</strong>. This is Appharu's income, kept apart from client store sales.</div></div>
    <form method="POST" action="{{ route('super.billing.renewals') }}">@csrf<button class="btn btn-light"><i class="bi bi-arrow-repeat"></i> Run renewals now</button></form>
</div>
<form class="card-a mb-3 filters"><div class="body row g-2">
    <div class="col-md-5"><select name="store" class="form-select"><option value="">All stores</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-5"><select name="status" class="form-select"><option value="">Any status</option>@foreach (['unpaid', 'paid', 'void'] as $s)<option @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select></div>
    <div class="col-md-2 d-grid"><button class="btn btn-outline-dark">Filter</button></div>
</div></form>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Invoice</th><th>Store</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th>Status</th><th style="width:360px">Record payment</th></tr></thead>
    <tbody>
    @forelse ($invoices as $invoice)
        <tr>
            <td class="fw-semibold">{{ $invoice->number }}<div class="small text-muted">due {{ $invoice->due_on->format('j M Y') }}</div></td>
            <td><a href="{{ route('super.stores.show', $invoice->store_id) }}">{{ $invoice->store?->name }}</a></td>
            <td class="small">{{ $invoice->description }}</td>
            <td class="text-end">{{ $m($invoice->amount) }}</td>
            <td class="text-end">{{ $m($invoice->paid_amount) }}</td>
            <td><span class="badge text-bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->due_on->isPast() ? 'danger' : 'warning') }}">{{ $invoice->status === 'unpaid' && $invoice->due_on->isPast() ? 'Overdue' : ucfirst($invoice->status) }}</span></td>
            <td>
                @if ($invoice->status === 'unpaid')
                    <form method="POST" action="{{ route('super.billing.payment', $invoice) }}" class="d-flex gap-1">
                        @csrf
                        <input name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance() }}" value="{{ $invoice->balance() }}" class="form-control form-control-sm" style="max-width:100px">
                        <select name="method" class="form-select form-select-sm" style="max-width:90px"><option value="bank">Bank</option><option value="khalti">Khalti</option><option value="esewa">eSewa</option><option value="qr">QR</option><option value="cash">Cash</option></select>
                        <input name="reference" class="form-control form-control-sm" placeholder="Ref">
                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                    </form>
                @else
                    <span class="small text-muted">{{ $invoice->payments->map(fn ($p) => $p->method.' '.$p->paid_at->format('j M'))->implode(', ') }}</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">No invoices.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $invoices->links() }}</div>
@endsection
