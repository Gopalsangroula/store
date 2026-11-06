<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Platform owner panel: works on any host, needs a super admin.
            Route::middleware('web')->prefix('superadmin')->name('super.')->group(base_path('routes/superadmin.php'));

            // Store dashboard: the store comes from the host name.
            Route::middleware(['web', 'store:admin'])->prefix('admin')->name('admin.')->group(base_path('routes/storeadmin.php'));

            // Public API for apps and integrations, also per store host.
            Route::middleware(['api', 'store:admin'])->prefix('api/v1')->name('api.')->group(base_path('routes/api.php'));

            // The shop itself (registered last so its catch-all page route comes after the others).
            Route::middleware(['web', 'store'])->name('shop.')->group(base_path('routes/storefront.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind Cloudflare / a reverse proxy, the visitor IP and https come from these headers.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // The store must be known BEFORE route model binding runs, so {order} or {product}
        // in a URL is looked up inside this store only. Otherwise another store's id would load.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\ResolveStore::class,
        );

        $middleware->alias([
            'store' => \App\Http\Middleware\ResolveStore::class,
            'store.staff' => \App\Http\Middleware\StoreStaff::class,
            'super' => \App\Http\Middleware\SuperAdmin::class,
        ]);

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('admin/*') ? route('admin.dashboard')
            : ($request->is('superadmin/*') ? route('super.dashboard') : route('shop.account')));

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin/*') ? route('admin.login')
            : ($request->is('superadmin/*') ? route('super.login') : route('shop.account.login')));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
