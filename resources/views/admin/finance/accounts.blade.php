@extends('admin.layout', ['title' => 'Accounts'])

@section('content')
<div class="page-head">
    <div><h1>Chart of accounts</h1><div class="sub">Double-entry books kept automatically from sales, payments, refunds, purchases and expenses.</div></div>
    <a href="{{ route('admin.finance.journal') }}" class="btn btn-light"><i class="bi bi-journal-text"></i> Journal</a>
</div>
<div @class(['alert', 'alert-success' => abs($debits - $credits) < 0.005, 'alert-danger' => abs($debits - $credits) >= 0.005])>
    Trial balance: debits {{ number_format($debits, 2) }} · credits {{ number_format($credits, 2) }} — {{ abs($debits - $credits) < 0.005 ? 'balanced' : 'NOT balanced, please contact support' }}.
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Code</th><th>Account</th><th>Type</th><th class="text-end">Debits</th><th class="text-end">Credits</th><th class="text-end">Balance</th></tr></thead>
    <tbody>
    @foreach ($accounts as $account)
        @php
            $row = $balances[$account->code] ?? null;
            $d = (float) ($row->debit ?? 0); $c = (float) ($row->credit ?? 0);
            $balance = in_array($account->type, ['asset', 'expense']) ? $d - $c : $c - $d;
        @endphp
        <tr>
            <td class="font-monospace">{{ $account->code }}</td>
            <td><a href="{{ route('admin.finance.journal', ['account' => $account->id]) }}" class="text-reset">{{ $account->name }}</a></td>
            <td><span class="badge badge-soft">{{ ucfirst($account->type) }}</span></td>
            <td class="text-end">{{ number_format($d, 2) }}</td>
            <td class="text-end">{{ number_format($c, 2) }}</td>
            <td class="text-end fw-semibold">{{ number_format($balance, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
