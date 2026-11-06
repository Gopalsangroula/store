<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\StoreDomain;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finds the store from the host name and makes it the request's tenant.
 *
 *  1. An exact match in store_domains (custom domains, and localhost for development).
 *  2. <slug>.<platform domain>, e.g. tea.store.appharu.com.
 *
 * Unknown hosts get a 404, so a forged Host header never reaches store data.
 * Usage: "store" for the shop (a suspended store shows a closed page), "store:admin"
 * for the store dashboard (still reachable so the owner can settle billing).
 */
class ResolveStore
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function handle(Request $request, Closure $next, string $area = 'shop'): Response
    {
        $store = $this->find(strtolower($request->getHost()));

        if (! $store) {
            abort(404, 'Store not found');
        }

        $this->tenant->set($store);
        View::share('store', $store);

        // A customer account belongs to one store only.
        if (Auth::guard('customer')->check() && Auth::guard('customer')->user()->store_id !== $store->id) {
            Auth::guard('customer')->logout();
        }

        if ($area === 'shop' && ! $store->isActive()) {
            return response()->view('storefront.closed', ['store' => $store], 503);
        }

        return $next($request);
    }

    public function find(string $host): ?Store
    {
        $storeId = Cache::remember('store-host:'.$host, 60, function () use ($host) {
            $id = StoreDomain::where('domain', $host)->value('store_id');

            if (! $id) {
                $platform = strtolower((string) config('platform.domain'));

                if ($platform && str_ends_with($host, '.'.$platform)) {
                    $slug = substr($host, 0, -strlen('.'.$platform));
                    $id = str_contains($slug, '.') ? null : Store::where('slug', $slug)->value('id');
                }
            }

            return $id ?: 0;
        });

        return $storeId ? Store::with('domains', 'plan')->find($storeId) : null;
    }
}
