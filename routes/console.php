<?php

use App\Models\Store;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SubscriptionService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('store:release-stock', function (InventoryService $inventory) {
    $this->info('Released '.$inventory->releaseExpired().' unpaid orders.');
})->purpose('Cancel unpaid online orders whose payment window has passed and free their stock');

Artisan::command('store:summaries {--days=2}', function (TenantContext $tenant) {
    foreach (Store::all() as $store) {
        $tenant->run($store, function () {
            for ($i = 0; $i < (int) $this->option('days'); $i++) {
                app(ReportService::class)->refreshDay(now()->subDays($i));
            }
        });
    }
    $this->info('Daily summaries rebuilt.');
})->purpose('Rebuild the daily sales summaries used by store reports');

Artisan::command('platform:billing', function (SubscriptionService $subscriptions) {
    $this->info('Renewal invoices raised: '.$subscriptions->generateRenewals());
    $this->info('Stores suspended: '.$subscriptions->enforce());
})->purpose('Raise subscription renewal invoices and suspend stores past their grace period');

Schedule::command('store:release-stock')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('store:summaries')->dailyAt('00:30');
Schedule::command('platform:billing')->dailyAt('06:00');
