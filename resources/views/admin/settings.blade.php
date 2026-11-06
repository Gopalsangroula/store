@extends('admin.layout', ['title' => 'Settings'])

@section('content')
<div class="page-head"><div><h1>Store settings</h1><div class="sub">{{ \App\Http\Controllers\StoreAdmin\SettingsController::SECTIONS[$section][0] }}</div></div></div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="list-group">
            @foreach (\App\Http\Controllers\StoreAdmin\SettingsController::SECTIONS as $key => [$label, $icon])
                <a href="{{ route('admin.settings.show', $key) }}" class="list-group-item list-group-item-action @if($key === $section) active bg-dark border-dark @endif"><i class="bi bi-{{ $icon }} me-2"></i>{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div class="col-lg-9">

@if ($section === 'general')
    <form method="POST" action="{{ route('admin.settings.general') }}" enctype="multipart/form-data" class="card-a">
        @csrf @method('PUT')
        <div class="body row g-3">
            <div class="col-md-6"><label class="form-label">Store name</label><input name="name" class="form-control" value="{{ old('name', $store->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Logo</label><input type="file" name="logo" accept="image/*" class="form-control">@if($store->logoUrl())<div class="d-flex align-items-center gap-2 mt-2"><img src="{{ $store->logoUrl() }}" style="max-height:36px"><label class="small"><input type="checkbox" name="remove_logo" value="1"> Remove</label></div>@endif</div>
            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $store->email) }}"></div>
            <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $store->phone) }}"></div>
            <div class="col-md-3"><label class="form-label">WhatsApp</label><input name="whatsapp" class="form-control" value="{{ old('whatsapp', $store->whatsapp) }}" placeholder="+97798..."></div>
            <div class="col-md-8"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $store->address) }}"></div>
            <div class="col-md-4"><label class="form-label">PAN / VAT number</label><input name="pan_vat_number" class="form-control" value="{{ old('pan_vat_number', $store->pan_vat_number) }}"><div class="form-text">Printed on invoices.</div></div>
            <div class="col-md-8"><label class="form-label">Business hours</label><input name="business_hours" class="form-control" value="{{ old('business_hours', $store->setting('business_hours')) }}"></div>
            <div class="col-md-4"><label class="form-label">Order number prefix</label><input name="order_prefix" class="form-control text-uppercase" maxlength="6" value="{{ old('order_prefix', $store->setting('order_prefix')) }}"><div class="form-text">e.g. APH → APH-2026-000123</div></div>
            @foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $network)
                <div class="col-md-6"><label class="form-label">{{ ucfirst($network) }} link</label><input name="social_{{ $network }}" type="url" class="form-control" value="{{ old('social_'.$network, $store->setting('social_'.$network)) }}"></div>
            @endforeach
            <div class="col-12"><button class="btn btn-brand">Save profile</button></div>
        </div>
    </form>

@elseif ($section === 'storefront')
    <form method="POST" action="{{ route('admin.settings.storefront') }}" class="card-a">
        @csrf @method('PUT')
        <div class="body row g-3">
            <div class="col-12"><label class="form-label d-block">Theme</label>
                @foreach (['midnight' => ['Midnight', 'Dark hero, bold accent', '#0b0b0b'], 'daylight' => ['Daylight', 'Light and clean', '#eef4ff'], 'forest' => ['Forest', 'Deep green, natural', '#0b2419']] as $key => [$label, $hint, $bg])
                    <label class="border rounded-3 p-2 me-2 mb-2 d-inline-flex align-items-center gap-2" style="cursor:pointer"><input type="radio" name="theme" value="{{ $key }}" @checked($store->setting('theme', 'midnight') === $key)><span style="width:28px;height:28px;border-radius:6px;background:{{ $bg }};border:1px solid #0002"></span><span><strong>{{ $label }}</strong><br><span class="small text-muted">{{ $hint }}</span></span></label>
                @endforeach
            </div>
            <div class="col-md-3"><label class="form-label">Accent colour</label><input type="color" name="accent_color" class="form-control form-control-color w-100" value="{{ $store->setting('accent_color', '#f5c542') }}"></div>
            <div class="col-md-9"><label class="form-label">Tagline</label><input name="tagline" class="form-control" value="{{ $store->setting('tagline') }}" maxlength="200"><div class="form-text">Shown in the footer and in search results.</div></div>
            <div class="col-md-4"><label class="form-label">Hero badge</label><input name="hero_eyebrow" class="form-control" value="{{ $store->setting('hero_eyebrow') }}"></div>
            <div class="col-md-8"><label class="form-label">Hero heading</label><input name="hero_title" class="form-control" value="{{ $store->setting('hero_title') }}" required></div>
            <div class="col-12"><label class="form-label">Highlighted words</label><input name="hero_highlight" class="form-control" value="{{ $store->setting('hero_highlight') }}"><div class="form-text">Comma separated; they take turns after the heading in your accent colour, e.g. Call, Reel, Trip.</div></div>
            <div class="col-12"><label class="form-label">Hero text</label><textarea name="hero_text" rows="2" class="form-control" maxlength="300">{{ $store->setting('hero_text') }}</textarea></div>
            <div class="col-md-6"><label class="form-label">Featured section title</label><input name="featured_title" class="form-control" value="{{ $store->setting('featured_title') }}"></div>
            <div class="col-md-6"><label class="form-label">Featured section text</label><input name="featured_text" class="form-control" value="{{ $store->setting('featured_text') }}"></div>
            <div class="col-md-4"><label class="form-label">Delivery promise</label><input name="free_delivery_note" class="form-control" value="{{ $store->setting('free_delivery_note') }}"></div>
            <div class="col-md-4"><label class="form-label">Delivery time</label><input name="delivery_note" class="form-control" value="{{ $store->setting('delivery_note') }}"></div>
            <div class="col-md-4"><label class="form-label">Warranty / returns</label><input name="warranty_note" class="form-control" value="{{ $store->setting('warranty_note') }}"></div>
            <div class="col-12"><button class="btn btn-brand">Save storefront</button> <a href="{{ $store->url('/') }}" target="_blank" class="btn btn-light">Open store</a></div>
        </div>
    </form>

@elseif ($section === 'payments')
    @foreach ($methods as $method)
        <form method="POST" action="{{ route('admin.settings.payment', $method->code) }}" enctype="multipart/form-data" class="card-a mb-3">
            @csrf @method('PUT')
            <div class="head">
                <span>{{ \App\Models\PaymentMethod::CODES[$method->code] }}
                    @if ($method->is_enabled && $method->isReady())<span class="badge text-bg-success ms-1">On</span>@elseif(! $method->isReady())<span class="badge text-bg-warning ms-1">Needs setup</span>@else<span class="badge text-bg-secondary ms-1">Off</span>@endif
                </span>
                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked($method->is_enabled) id="on-{{ $method->code }}"><label class="form-check-label small" for="on-{{ $method->code }}">Offer at checkout</label></div>
            </div>
            <div class="body row g-3">
                <div class="col-md-4"><label class="form-label">Name at checkout</label><input name="name" class="form-control" value="{{ $method->name }}" required></div>
                <div class="col-md-8"><label class="form-label">Text under the name</label><input name="instructions" class="form-control" value="{{ $method->instructions }}"></div>
                @if ($method->code === 'khalti')
                    <div class="col-md-8"><label class="form-label">Khalti secret key</label><input name="secret_key" type="password" class="form-control" autocomplete="off" placeholder="{{ $method->configValue('secret_key') ? '•••••••• saved (leave empty to keep)' : 'From your Khalti merchant dashboard' }}"><div class="form-text">Find it in Khalti merchant panel → Keys. It is stored encrypted and never shown again.</div></div>
                    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="live" value="1" id="live" @checked($method->configValue('live'))><label class="form-check-label" for="live">Live mode (real money)</label><div class="form-text">Off = Khalti sandbox for testing.</div></div></div>
                    <div class="col-12 small text-muted">Customers pay on Khalti's page and return to your store. Every payment is confirmed with Khalti's server before an order is marked paid.</div>
                @elseif ($method->code === 'qr')
                    <div class="col-md-4">@if ($method->qrImageUrl())<img src="{{ $method->qrImageUrl() }}" class="border rounded" style="max-width:140px" alt="Current QR">@else<div class="empty border rounded small">No QR yet</div>@endif</div>
                    <div class="col-md-8 row g-2">
                        <div class="col-12"><label class="form-label">Upload your payment QR</label><input type="file" name="qr_image" accept="image/png,image/jpeg,image/webp" class="form-control"><div class="form-text">Your Fonepay, bank or wallet merchant QR. Customers scan it, pay, and upload a screenshot for you to check.</div></div>
                        <div class="col-6"><label class="form-label">Account name</label><input name="account_name" class="form-control" value="{{ $method->configValue('account_name') }}"></div>
                        <div class="col-6"><label class="form-label">Label under QR</label><input name="qr_label" class="form-control" value="{{ $method->configValue('qr_label') }}" placeholder="Fonepay / any bank app"></div>
                    </div>
                @elseif ($method->code === 'bank')
                    <div class="col-md-6"><label class="form-label">Bank</label><input name="bank_name" class="form-control" value="{{ $method->configValue('bank_name') }}"></div>
                    <div class="col-md-6"><label class="form-label">Branch</label><input name="branch" class="form-control" value="{{ $method->configValue('branch') }}"></div>
                    <div class="col-md-6"><label class="form-label">Account name</label><input name="account_name" class="form-control" value="{{ $method->configValue('account_name') }}"></div>
                    <div class="col-md-6"><label class="form-label">Account number</label><input name="account_number" class="form-control" value="{{ $method->configValue('account_number') }}"></div>
                @endif
                <div class="col-12"><button class="btn btn-brand btn-sm">Save {{ \App\Models\PaymentMethod::CODES[$method->code] }}</button></div>
            </div>
        </form>
    @endforeach

@elseif ($section === 'shipping')
    <div class="card-a mb-3">
        <div class="head">Delivery zones</div>
        <div class="body small text-muted pb-0">The zone that lists the shopper's city is used; a zone with no cities covers everywhere else.</div>
        @foreach ($zones as $zone)
            <form method="POST" action="{{ route('admin.settings.zones.save') }}" class="body border-bottom row g-2 align-items-end">
                @csrf <input type="hidden" name="id" value="{{ $zone->id }}">
                <div class="col-md-3"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" value="{{ $zone->name }}" required></div>
                <div class="col-md-4"><label class="form-label small">Cities</label><input name="cities" class="form-control form-control-sm" value="{{ $zone->cities }}" placeholder="Everywhere else"></div>
                <div class="col-md-1"><label class="form-label small">Rate</label><input name="rate" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ (float) $zone->rate }}"></div>
                <div class="col-md-1"><label class="form-label small">Free over</label><input name="free_over" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $zone->free_over ? (float) $zone->free_over : '' }}"></div>
                <div class="col-md-1"><label class="form-label small">Days</label><input name="estimated_days" class="form-control form-control-sm" value="{{ $zone->estimated_days }}"></div>
                <input type="hidden" name="sort" value="{{ $zone->sort }}">
                <div class="col-md-2 small"><label class="d-block"><input type="checkbox" name="cod_available" value="1" @checked($zone->cod_available)> Cash on delivery</label><label class="d-block"><input type="checkbox" name="is_active" value="1" @checked($zone->is_active)> Active</label></div>
                <div class="col-12 d-flex gap-2"><button class="btn btn-sm btn-brand">Save</button><button form="del-zone-{{ $zone->id }}" class="btn btn-sm btn-light text-danger" onclick="return confirm('Delete this zone?')">Delete</button></div>
            </form>
            <form id="del-zone-{{ $zone->id }}" method="POST" action="{{ route('admin.settings.zones.delete', $zone) }}">@csrf @method('DELETE')</form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.settings.zones.save') }}" class="card-a">
        @csrf
        <div class="head">Add zone</div>
        <div class="body row g-2">
            <div class="col-md-4"><input name="name" class="form-control" placeholder="Zone name, e.g. Pokhara" required></div>
            <div class="col-md-4"><input name="cities" class="form-control" placeholder="Cities, comma separated"></div>
            <div class="col-md-2"><input name="rate" type="number" step="0.01" min="0" class="form-control" placeholder="Rate" required></div>
            <div class="col-md-2"><input name="free_over" type="number" step="0.01" min="0" class="form-control" placeholder="Free over"></div>
            <input type="hidden" name="cod_available" value="1"><input type="hidden" name="is_active" value="1">
            <div class="col-12"><button class="btn btn-brand btn-sm">Add zone</button></div>
        </div>
    </form>

@elseif ($section === 'tax')
    <form method="POST" action="{{ route('admin.settings.tax') }}" class="card-a">
        @csrf @method('PUT')
        <div class="body row g-3">
            <div class="col-md-4"><label class="form-label">Tax name</label><input name="name" class="form-control" value="{{ $tax?->name ?? 'VAT' }}" required></div>
            <div class="col-md-4"><label class="form-label">Rate (%)</label><input name="rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ (float) ($tax?->rate ?? 13) }}" required></div>
            <div class="col-12">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="t-active" @checked($tax?->is_active ?? true)><label class="form-check-label" for="t-active">Charge this tax (turn off if you are not VAT registered)</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="is_inclusive" value="1" id="t-incl" @checked($tax?->is_inclusive ?? true)><label class="form-check-label" for="t-incl">My prices already include VAT</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="applies_to_shipping" value="1" id="t-ship" @checked($tax?->applies_to_shipping)><label class="form-check-label" for="t-ship">Charge VAT on delivery fees too</label></div>
            </div>
            <div class="col-12 small text-muted">VAT is worked out on each item's price after its share of any discount. With inclusive prices, VAT = price × 13 / 113 and the shopper pays the price shown.</div>
            <div class="col-12"><button class="btn btn-brand">Save tax</button></div>
        </div>
    </form>

@elseif ($section === 'currency')
    <form method="POST" action="{{ route('admin.settings.currency') }}" class="card-a">
        @csrf @method('PUT')
        <div class="head">Show prices in other currencies</div>
        <div class="body">
            @unless ($store->hasFeature('multi_currency'))<div class="alert alert-info small">Multi-currency is not in your plan; only {{ $store->base_currency }} is shown.</div>@endunless
            <p class="small text-muted">Orders are always charged in {{ $store->base_currency }}. Other currencies are for display, using the rate you set (1 {{ $store->base_currency }} = ?).</p>
            @php $enabled = explode(',', (string) $store->setting('currencies', $store->base_currency)); @endphp
            <table class="table table-a"><thead><tr><th>Show</th><th>Currency</th><th>Rate (1 {{ $store->base_currency }} =)</th></tr></thead><tbody>
            @foreach ($currencies as $c)
                <tr>
                    <td><input type="checkbox" name="currencies[]" value="{{ $c->code }}" @checked(in_array($c->code, $enabled) || $c->code === $store->base_currency) @disabled($c->code === $store->base_currency)></td>
                    <td>{{ $c->flag }} {{ $c->code }} · {{ $c->name }}</td>
                    <td>@if ($c->code === $store->base_currency) 1 (base) @else<input name="rates[{{ $c->code }}]" type="number" step="0.000001" min="0" class="form-control form-control-sm" style="max-width:160px" value="{{ isset($rates[$c->code]) ? rtrim(rtrim($rates[$c->code]->rate, '0'), '.') : '' }}">@endif</td>
                </tr>
            @endforeach
            </tbody></table>
            <button class="btn btn-brand">Save currencies</button>
        </div>
    </form>

@elseif ($section === 'domains')
    <div class="card-a mb-3">
        <div class="head">Your store addresses</div>
        <table class="table table-a"><tbody>
        @foreach ($domains as $d)
            <tr>
                <td><a href="{{ (str_contains($d->domain, 'localhost') || $d->domain === '127.0.0.1' ? 'http://' : 'https://').$d->domain }}" target="_blank" class="fw-semibold">{{ $d->domain }}</a> @if($d->is_primary)<span class="badge text-bg-dark">Main</span>@endif</td>
                <td class="text-end">
                    @unless ($d->is_primary)
                        <form method="POST" action="{{ route('admin.settings.domains.remove', $d->id) }}" class="d-inline">@csrf @method('DELETE')<input type="hidden" name="primary" value="1"><button class="btn btn-sm btn-light">Make main</button></form>
                        <form method="POST" action="{{ route('admin.settings.domains.remove', $d->id) }}" class="d-inline" onsubmit="return confirm('Remove this domain?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody></table>
    </div>
    <form method="POST" action="{{ route('admin.settings.domains.add') }}" class="card-a">
        @csrf
        <div class="head">Connect your own domain</div>
        <div class="body">
            <ol class="small">
                <li>In your domain's DNS, add a <strong>CNAME</strong> record for <code>shop.yourdomain.com</code> pointing to <code>{{ config('platform.domain') }}</code> (or an A record to the platform server's IP).</li>
                <li>Add the domain here. HTTPS is issued by the platform once DNS points to it.</li>
                <li>When it opens your store, make it your main address.</li>
            </ol>
            <div class="d-flex gap-2"><input name="domain" class="form-control" placeholder="shop.yourdomain.com" required><button class="btn btn-brand text-nowrap">Add domain</button></div>
        </div>
    </form>
@endif

    </div>
</div>
@endsection
