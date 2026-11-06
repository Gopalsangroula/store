@extends('super.layout', ['title' => 'Settings'])

@section('content')
<div class="page-head"><div><h1>Platform settings</h1><div class="sub">Platform domain: <code>{{ config('platform.domain') }}</code> (set PLATFORM_DOMAIN in .env). Client stores get <code>slug.{{ config('platform.domain') }}</code> automatically.</div></div></div>
<form method="POST" action="{{ route('super.settings.save') }}" class="card-a" style="max-width:760px">
    @csrf @method('PUT')
    <div class="body row g-3">
        <div class="col-md-6"><label class="form-label">Support email</label><input name="support_email" type="email" class="form-control" value="{{ $settings['support_email'] ?? '' }}"></div>
        <div class="col-md-6"><label class="form-label">Support phone</label><input name="support_phone" class="form-control" value="{{ $settings['support_phone'] ?? '' }}"></div>
        <div class="col-12"><label class="form-label">How clients pay you</label><textarea name="billing_bank" rows="3" class="form-control" placeholder="Bank, account, Khalti ID...">{{ $settings['billing_bank'] ?? '' }}</textarea></div>
        <div class="col-12"><label class="form-label">Notice to show store owners</label><input name="maintenance_note" class="form-control" value="{{ $settings['maintenance_note'] ?? '' }}"></div>
        <div class="col-12"><button class="btn btn-brand">Save</button></div>
    </div>
</form>
<div class="card-a mt-3" style="max-width:760px"><div class="head">Scheduled jobs</div><div class="body small">
    Run <code>php artisan schedule:work</code> (or a cron of <code>php artisan schedule:run</code> every minute) so these run:
    <ul class="mb-0 mt-2"><li><code>store:release-stock</code> every 5 minutes: cancels unpaid online orders and frees their stock.</li><li><code>store:summaries</code> nightly: rebuilds report summaries.</li><li><code>platform:billing</code> daily: raises renewal invoices and suspends stores past the grace period ({{ config('platform.grace_days') }} days).</li></ul>
</div></div>
@endsection
