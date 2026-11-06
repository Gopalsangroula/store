@extends('super.layout', ['title' => 'Activity'])

@section('content')
<div class="page-head">
    <div><h1>Activity log</h1><div class="sub">Sensitive admin actions across the platform.</div></div>
    <form><select name="store" class="form-select" onchange="this.form.submit()"><option value="">All stores</option>@foreach ($stores as $s)<option value="{{ $s->id }}" @selected(request('store') == $s->id)>{{ $s->name }}</option>@endforeach</select></form>
</div>
<div class="card-a"><div class="table-responsive"><table class="table table-a">
    <thead><tr><th>When</th><th>Store</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
        <tr><td class="small text-nowrap">{{ $log->created_at->format('j M Y, g:i A') }}</td><td class="small">{{ $log->store?->name ?? 'Platform' }}</td><td class="small">{{ $log->user?->name ?? 'System' }}</td><td><code>{{ $log->action }}</code></td><td class="small text-muted">{{ $log->properties ? json_encode($log->properties) : '' }}</td><td class="small text-muted">{{ $log->ip }}</td></tr>
    @empty
        <tr><td colspan="6" class="empty">No activity yet.</td></tr>
    @endforelse
    </tbody>
</table></div></div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
