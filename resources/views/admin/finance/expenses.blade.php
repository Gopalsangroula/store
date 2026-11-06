@extends('admin.layout', ['title' => 'Expenses'])

@section('content')
<div class="page-head"><div><h1>Expenses</h1><div class="sub">This month: <strong>{{ \App\Support\Money::format($monthTotal) }}</strong>. Expenses go into your profit and loss report.</div></div></div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-a"><div class="table-responsive"><table class="table table-a">
            <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Paid from</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
            @forelse ($expenses as $e)
                <tr><td class="small">{{ $e->spent_on->format('j M Y') }}</td><td>{{ $e->description }}@if($e->reference)<div class="small text-muted">{{ $e->reference }}</div>@endif</td><td class="small">{{ $e->category?->name ?? '—' }}</td><td class="small">{{ ucfirst($e->paid_from) }}</td><td class="text-end fw-semibold">{{ \App\Support\Money::format($e->amount) }}</td></tr>
            @empty
                <tr><td colspan="5" class="empty">No expenses recorded.</td></tr>
            @endforelse
            </tbody>
        </table></div></div>
        <div class="mt-3">{{ $expenses->links() }}</div>
    </div>
    <div class="col-lg-4">
        <form method="POST" action="{{ route('admin.finance.expenses.store') }}" class="card-a">
            @csrf
            <div class="head">Record expense</div>
            <div class="body">
                <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control" required maxlength="200" value="{{ old('description') }}"></div>
                <div class="row g-2 mb-2"><div class="col-6"><label class="form-label">Amount</label><input name="amount" type="number" step="0.01" min="0.01" class="form-control" required value="{{ old('amount') }}"></div><div class="col-6"><label class="form-label">Date</label><input name="spent_on" type="date" class="form-control" value="{{ old('spent_on', now()->toDateString()) }}" required></div></div>
                <div class="mb-2"><label class="form-label">Category</label><select name="expense_category_id" class="form-select"><option value="">None</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="mb-2"><label class="form-label">Paid from</label><select name="paid_from" class="form-select"><option value="cash">Cash</option><option value="bank">Bank</option><option value="khalti">Khalti</option></select></div>
                <div class="mb-3"><label class="form-label">Reference</label><input name="reference" class="form-control" maxlength="100" placeholder="Bill no."></div>
                <button class="btn btn-brand w-100">Record expense</button>
            </div>
        </form>
    </div>
</div>
@endsection
