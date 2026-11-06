<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\CurrencyService;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant per request (scoped resets it between Octane requests and queued jobs).
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(CartService::class);
        $this->app->scoped(CurrencyService::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->rateLimits();

        // @staffcan('orders.manage') ... @endstaffcan for menus and buttons.
        Blade::if('staffcan', function (string $permission) {
            $user = auth('web')->user();
            $tenant = app(TenantContext::class);

            return $user && $tenant->has() && ($user->is_super_admin || $user->canInStore($tenant->store(), $permission));
        });

        // Price in the shopper's chosen display currency.
        Blade::directive('price', fn ($amount) => "<?php echo e(app(\App\Services\CurrencyService::class)->format({$amount}, request())); ?>");

        View::composer('storefront.*', function ($view) {
            $tenant = app(TenantContext::class);

            if (! $tenant->has()) {
                return;
            }

            $view->with('cartCount', app(CartService::class)->count(request()));
        });
    }

    private function rateLimits(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email', $request->input('phone'))).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('coupon', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('track', fn (Request $request) => Limit::perMinute(15)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
