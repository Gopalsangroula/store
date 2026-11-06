@extends('admin.layout', ['title' => 'Journal'])

@section('content')
<div class="page-head">
    <div><a href="{{ route('admin.finance.accounts') }}" class="small text-muted"><i class="bi bi-arrow-left"></i> Accounts</a><h1>Journal</h1><div class="sub">Every entry balances: total debits equal total credits.</div></div>
    <form><select name="account" class="form-select" onchange="this.form.submit()"><option value="">All accounts</option>@foreach ($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->code }} {{ $a->name }}</option>@endforeach</select></form>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>Date</th><th>Entry</th><th>Account</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
    <tbody>
    @forelse ($entries as $entry)
        @foreach ($entry->lines as $line)
            <tr @class(['border-top' => $loop->first])>
                <td class="small">{{ $loop->first ? $entry->entry_date->format('j M Y') : '' }}</td>
                <td class="small">{{ $loop->first ? $entry->memo : '' }}</td>
                <td class="small {{ $line->credit > 0 ? 'ps-4' : '' }}">{{ $line->account->code }} {{ $line->account->name }}</td>
                <td class="text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                <td class="text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="5" class="empty">No entries yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $entries->links() }}</div>
@endsection
