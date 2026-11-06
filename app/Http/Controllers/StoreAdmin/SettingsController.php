<?php

namespace App\Http\Controllers\StoreAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\ShippingZone;
use App\Models\StoreDomain;
use App\Models\TaxRate;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public const SECTIONS = [
        'general' => ['Store profile', 'shop'],
        'storefront' => ['Storefront', 'palette'],
        'payments' => ['Payments', 'credit-card'],
        'shipping' => ['Delivery', 'truck'],
        'tax' => ['VAT & tax', 'percent'],
        'currency' => ['Currency', 'currency-exchange'],
        'domains' => ['Domains', 'globe'],
    ];

    public function show(string $section = 'general')
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return view('admin.settings', [
            'section' => $section,
            'methods' => PaymentMethod::orderBy('sort')->get(),
            'zones' => ShippingZone::orderBy('sort')->get(),
            'tax' => TaxRate::first(),
            'currencies' => Currency::orderBy('code')->get(),
            'rates' => ExchangeRate::where('effective_at', '<=', now())->orderBy('effective_at')->get()->keyBy('quote'),
            'domains' => StoreDomain::where('store_id', $this->currentStore()->id)->orderByDesc('is_primary')->get(),
        ]);
    }

    public function general(Request $request)
    {
        $store = $this->currentStore();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:250',
            'pan_vat_number' => 'nullable|string|max:30',
            'logo' => 'nullable|'.Media::IMAGE_RULE,
            'business_hours' => 'nullable|string|max:100',
            'social_facebook' => 'nullable|url|max:250',
            'social_instagram' => 'nullable|url|max:250',
            'social_tiktok' => 'nullable|url|max:250',
            'social_youtube' => 'nullable|url|max:250',
            'order_prefix' => 'nullable|alpha_num|max:6',
        ]);

        $fields = collect($data)->only(['name', 'email', 'phone', 'whatsapp', 'address', 'pan_vat_number'])->all();

        if ($request->hasFile('logo')) {
            $fields['logo_path'] = Media::store($request->file('logo'), $store->id, 'branding');
        }

        if ($request->boolean('remove_logo')) {
            $fields['logo_path'] = null;
        }

        $store->update($fields);
        $store->setSettings(collect($data)->only(['business_hours', 'social_facebook', 'social_instagram', 'social_tiktok', 'social_youtube', 'order_prefix'])->map(fn ($v) => $v ?? '')->all());
        ActivityLog::record('settings.general');

        return back()->with('status', 'Store profile saved.');
    }

    public function storefront(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:midnight,daylight,forest',
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'tagline' => 'nullable|string|max:200',
            'hero_eyebrow' => 'nullable|string|max:60',
            'hero_title' => 'required|string|max:80',
            'hero_highlight' => 'nullable|string|max:120',
            'hero_text' => 'nullable|string|max:300',
            'featured_title' => 'nullable|string|max:80',
            'featured_text' => 'nullable|string|max:150',
            'free_delivery_note' => 'nullable|string|max:60',
            'delivery_note' => 'nullable|string|max:60',
            'warranty_note' => 'nullable|string|max:60',
        ]);

        $this->currentStore()->setSettings(array_map(fn ($v) => $v ?? '', $data));

        return back()->with('status', 'Storefront saved. Open your store to see it.');
    }

    public function payment(Request $request, string $method)
    {
        abort_unless(isset(PaymentMethod::CODES[$method]), 404);
        $paymentMethod = PaymentMethod::where('code', $method)->firstOrFail();
        $config = $paymentMethod->config ?? [];

        $rules = ['name' => 'required|string|max:60', 'instructions' => 'nullable|string|max:500', 'is_enabled' => 'boolean'];
        $rules += match ($method) {
            'khalti' => ['secret_key' => 'nullable|string|max:200', 'live' => 'boolean'],
            'qr' => ['qr_image' => 'nullable|'.Media::IMAGE_RULE, 'account_name' => 'nullable|string|max:120', 'qr_label' => 'nullable|string|max:120'],
            'bank' => ['bank_name' => 'nullable|string|max:120', 'account_name' => 'nullable|string|max:120', 'account_number' => 'nullable|string|max:60', 'branch' => 'nullable|string|max:120'],
            default => [],
        };
        $data = $request->validate($rules);

        if ($method === 'khalti') {
            // An empty box keeps the saved key, so it never has to be shown again.
            if (! empty($data['secret_key'])) {
                $config['secret_key'] = trim($data['secret_key']);
            }
            $config['live'] = $request->boolean('live');
        } elseif ($method === 'qr') {
            if ($request->hasFile('qr_image')) {
                $config['qr_image'] = Media::store($request->file('qr_image'), $this->currentStore()->id, 'payments');
            }
            $config['account_name'] = $data['account_name'] ?? null;
            $config['qr_label'] = $data['qr_label'] ?? null;
        } elseif ($method === 'bank') {
            $config = array_merge($config, collect($data)->only(['bank_name', 'account_name', 'account_number', 'branch'])->all());
        }

        $paymentMethod->fill([
            'name' => $data['name'],
            'instructions' => $data['instructions'] ?? null,
            'config' => $config,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        if ($paymentMethod->is_enabled && ! $paymentMethod->isReady()) {
            return back()->with('error', $method === 'khalti' ? 'Add your Khalti secret key before switching Khalti on.' : 'Upload your payment QR code before switching QR payments on.');
        }

        $paymentMethod->save();
        ActivityLog::record('settings.payment', $paymentMethod, ['enabled' => $paymentMethod->is_enabled]);

        return back()->with('status', $paymentMethod->name.' saved.');
    }

    public function saveZone(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', Rule::exists('shipping_zones', 'id')->where('store_id', $this->currentStore()->id)],
            'name' => 'required|string|max:100',
            'cities' => 'nullable|string|max:3000',
            'rate' => 'required|numeric|min:0',
            'free_over' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|string|max:30',
            'sort' => 'nullable|integer|min:0',
        ]);
        $data['cod_available'] = $request->boolean('cod_available');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] = (int) ($data['sort'] ?? 0);

        ShippingZone::updateOrCreate(['id' => $data['id'] ?? null], collect($data)->except('id')->all());

        return back()->with('status', 'Delivery zone saved.');
    }

    public function deleteZone(ShippingZone $zone)
    {
        $zone->delete();

        return back()->with('status', 'Delivery zone deleted.');
    }

    public function tax(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:30', 'rate' => 'required|numeric|min:0|max:100']);

        TaxRate::updateOrCreate(['id' => TaxRate::value('id')], $data + [
            'is_inclusive' => $request->boolean('is_inclusive'),
            'applies_to_shipping' => $request->boolean('applies_to_shipping'),
            'is_active' => $request->boolean('is_active'),
        ]);
        ActivityLog::record('settings.tax', null, $data);

        return back()->with('status', 'Tax settings saved. New orders use them; past orders keep the tax they were charged.');
    }

    public function currency(Request $request)
    {
        $store = $this->currentStore();
        $data = $request->validate([
            'currencies' => 'array',
            'currencies.*' => 'exists:currencies,code',
            'rates' => 'array',
            'rates.*' => 'nullable|numeric|min:0.000001|max:1000000',
        ]);

        $codes = array_values(array_unique(array_merge([$store->base_currency], $data['currencies'] ?? [])));
        $store->setSettings(['currencies' => implode(',', $codes)]);

        // A new rate is a new row: orders already placed keep the rate of their day.
        $current = ExchangeRate::orderBy('effective_at')->get()->keyBy('quote');

        foreach ($data['rates'] ?? [] as $code => $rate) {
            if ($rate !== null && $code !== $store->base_currency && (float) ($current[$code]->rate ?? 0) !== (float) $rate) {
                ExchangeRate::create(['base' => $store->base_currency, 'quote' => $code, 'rate' => $rate, 'effective_at' => now()]);
            }
        }

        return back()->with('status', 'Currency settings saved.');
    }

    public function addDomain(Request $request)
    {
        if (! $this->currentStore()->hasFeature('custom_domain')) {
            return back()->with('error', 'Custom domains are not part of your plan.');
        }

        $request->merge(['domain' => strtolower(trim(preg_replace('#^https?://#', '', (string) $request->domain), '/ '))]);
        $data = $request->validate(['domain' => ['required', 'max:190', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/', 'unique:store_domains,domain']]);

        StoreDomain::create(['store_id' => $this->currentStore()->id, 'domain' => $data['domain'], 'is_primary' => false]);
        ActivityLog::record('settings.domain_added', null, $data);

        return back()->with('status', 'Domain added. Point its DNS to the platform, then make it primary once it loads your store.');
    }

    public function removeDomain(Request $request, int $domain)
    {
        $row = StoreDomain::where('store_id', $this->currentStore()->id)->findOrFail($domain);

        if ($request->boolean('primary')) {
            StoreDomain::where('store_id', $this->currentStore()->id)->update(['is_primary' => false]);
            $row->update(['is_primary' => true]);
            Cache::forget('store-host:'.$row->domain);

            return back()->with('status', $row->domain.' is now your main address.');
        }

        if ($row->is_primary || str_ends_with($row->domain, '.'.config('platform.domain'))) {
            return back()->with('error', 'The main address and the platform address cannot be removed.');
        }

        Cache::forget('store-host:'.$row->domain);
        $row->delete();

        return back()->with('status', 'Domain removed.');
    }
}
