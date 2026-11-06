<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * Display currency. Shoppers can view prices in another currency, but orders are always
 * charged and stored in the store's base currency (NPR), with the rate recorded on the order.
 */
class CurrencyService
{
    public const COOKIE = 'currency';

    private ?array $rates = null;

    public function __construct(private TenantContext $tenant)
    {
    }

    /** @return \Illuminate\Support\Collection<int, Currency> */
    public function enabled()
    {
        $store = $this->tenant->store();
        $codes = array_filter(array_map('trim', explode(',', (string) $store->setting('currencies', $store->base_currency))));

        if (! $store->hasFeature('multi_currency')) {
            $codes = [$store->base_currency];
        }

        $codes = array_values(array_unique(array_merge([$store->base_currency], $codes)));

        return Currency::whereIn('code', $codes)->get()->sortBy(fn ($c) => array_search($c->code, $codes))->values();
    }

    public function selected(Request $request): string
    {
        $code = strtoupper((string) $request->cookie(self::COOKIE));

        return $this->enabled()->contains('code', $code) && $this->rate($code) ? $code : $this->tenant->store()->base_currency;
    }

    public function rate(string $quote): ?float
    {
        $base = $this->tenant->store()->base_currency;

        if ($quote === $base) {
            return 1.0;
        }

        $this->rates ??= ExchangeRate::where('base', $base)
            ->where('effective_at', '<=', now())
            ->orderBy('effective_at')
            ->get()
            ->pluck('rate', 'quote')
            ->map(fn ($rate) => (float) $rate)
            ->all();

        return $this->rates[$quote] ?? null;
    }

    public function format(string|float|null $amount, Request $request): string
    {
        $code = $this->selected($request);

        return Money::format($amount, $code, $this->rate($code));
    }
}
