<?php

use App\Http\Controllers\SuperAdmin as S;
use Illuminate\Support\Facades\Route;

// Platform owner panel: all stores, plans and platform billing.

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [S\AuthController::class, 'form'])->name('login');
    Route::post('/login', [S\AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [S\AuthController::class, 'logout'])->name('logout');

Route::middleware('super')->group(function () {
    Route::get('/', [S\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/stores', [S\StoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/create', [S\StoreController::class, 'create'])->name('stores.create');
    Route::post('/stores', [S\StoreController::class, 'store'])->name('stores.store');
    Route::get('/stores/{store}', [S\StoreController::class, 'show'])->name('stores.show');
    Route::put('/stores/{store}', [S\StoreController::class, 'update'])->name('stores.update');
    Route::post('/stores/{store}/status', [S\StoreController::class, 'status'])->name('stores.status');
    Route::post('/stores/{store}/plan', [S\StoreController::class, 'plan'])->name('stores.plan');
    Route::post('/stores/{store}/domains', [S\StoreController::class, 'addDomain'])->name('stores.domains.add');
    Route::delete('/stores/{store}/domains/{domain}', [S\StoreController::class, 'removeDomain'])->name('stores.domains.remove');
    Route::post('/stores/{store}/owner', [S\StoreController::class, 'owner'])->name('stores.owner');

    Route::resource('plans', S\PlanController::class)->except('show', 'destroy');

    Route::get('/billing', [S\BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/{invoice}/payment', [S\BillingController::class, 'payment'])->name('billing.payment');
    Route::post('/billing/renewals', [S\BillingController::class, 'renewals'])->name('billing.renewals');

    Route::get('/activity', [S\DashboardController::class, 'activity'])->name('activity');
    Route::get('/settings', [S\DashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [S\DashboardController::class, 'saveSettings'])->name('settings.save');
});
