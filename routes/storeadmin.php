<?php

use App\Http\Controllers\StoreAdmin as A;
use Illuminate\Support\Facades\Route;

// Store dashboard at <store host>/admin. The store comes from the host name and every
// page checks the signed-in user works for that store with the right permission.

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [A\AuthController::class, 'form'])->name('login');
    Route::post('/login', [A\AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [A\AuthController::class, 'logout'])->name('logout');

Route::middleware('store.staff')->group(function () {
    Route::get('/', [A\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/billing', [A\BillingController::class, 'index'])->name('billing');

    // Catalog
    Route::middleware('store.staff:products.view')->group(function () {
        Route::get('/products', [A\ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware('store.staff:products.manage')->group(function () {
        Route::get('/products/create', [A\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [A\ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [A\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [A\ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [A\ProductController::class, 'destroy'])->name('products.destroy');
        Route::delete('/products/{product}/images/{image}', [A\ProductController::class, 'destroyImage'])->name('products.images.destroy');
        Route::resource('categories', A\CategoryController::class)->except('show');
        Route::resource('brands', A\BrandController::class)->except('show');
    });
    Route::middleware('store.staff:reviews.manage')->group(function () {
        Route::get('/reviews', [A\ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('/reviews/{review}', [A\ReviewController::class, 'update'])->name('reviews.update');
        Route::delete('/reviews/{review}', [A\ReviewController::class, 'destroy'])->name('reviews.destroy');
    });

    // Inventory
    Route::middleware('store.staff:inventory.view')->group(function () {
        Route::get('/inventory', [A\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [A\InventoryController::class, 'movements'])->name('inventory.movements');
    });
    Route::middleware('store.staff:inventory.manage')->group(function () {
        Route::post('/inventory/adjust', [A\InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('/inventory/transfer', [A\InventoryController::class, 'transfer'])->name('inventory.transfer');
        Route::resource('warehouses', A\WarehouseController::class)->except('show', 'destroy');
    });
    Route::middleware('store.staff:purchases.manage')->group(function () {
        Route::resource('suppliers', A\SupplierController::class)->except('show');
        Route::resource('purchase-orders', A\PurchaseOrderController::class)->only('index', 'create', 'store', 'show');
        Route::post('/purchase-orders/{purchase_order}/receive', [A\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchase_order}/cancel', [A\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    });

    // Sales
    Route::middleware('store.staff:orders.view')->group(function () {
        Route::get('/orders', [A\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [A\OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/invoice', [A\OrderController::class, 'invoice'])->name('orders.invoice');
        Route::get('/orders/{order}/slip', [A\OrderController::class, 'slip'])->name('orders.slip');
        Route::get('/carts', [A\OrderController::class, 'carts'])->name('carts.index');
    });
    Route::middleware('store.staff:orders.manage')->group(function () {
        Route::post('/orders/{order}/status', [A\OrderController::class, 'status'])->name('orders.status');
        Route::post('/orders/{order}/note', [A\OrderController::class, 'note'])->name('orders.note');
    });
    Route::middleware('store.staff:payments.verify')->group(function () {
        Route::post('/payments/{payment}/verify', [A\OrderController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('/payments/{payment}/reject', [A\OrderController::class, 'rejectPayment'])->name('payments.reject');
        Route::post('/payments/{payment}/recheck', [A\OrderController::class, 'recheckKhalti'])->name('payments.recheck');
        Route::get('/payments/{payment}/proof', [A\OrderController::class, 'proof'])->name('payments.proof');
    });
    Route::post('/orders/{order}/refund', [A\OrderController::class, 'refund'])->middleware('store.staff:refunds.manage')->name('orders.refund');
    Route::middleware('store.staff:customers.view')->group(function () {
        Route::get('/customers', [A\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [A\CustomerController::class, 'show'])->name('customers.show');
    });

    // Marketing
    Route::resource('coupons', A\CouponController::class)->except('show')->middleware('store.staff:coupons.manage');
    Route::middleware('store.staff:content.manage')->group(function () {
        Route::resource('banners', A\BannerController::class)->except('show');
        Route::resource('pages', A\PageController::class)->except('show');
    });

    // Finance
    Route::middleware('store.staff:finance.view')->group(function () {
        Route::get('/finance/invoices', [A\FinanceController::class, 'invoices'])->name('finance.invoices');
        Route::get('/finance/payments', [A\FinanceController::class, 'payments'])->name('finance.payments');
        Route::get('/finance/credit-notes', [A\FinanceController::class, 'creditNotes'])->name('finance.credit-notes');
        Route::get('/finance/accounts', [A\FinanceController::class, 'accounts'])->name('finance.accounts');
        Route::get('/finance/journal', [A\FinanceController::class, 'journal'])->name('finance.journal');
        Route::get('/reports/{report?}', [A\ReportController::class, 'show'])->name('reports');
    });
    Route::middleware('store.staff:expenses.manage')->group(function () {
        Route::get('/finance/expenses', [A\FinanceController::class, 'expenses'])->name('finance.expenses');
        Route::post('/finance/expenses', [A\FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    });

    // Settings
    Route::middleware('store.staff:settings.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/{section?}', [A\SettingsController::class, 'show'])->name('show');
        Route::put('/general', [A\SettingsController::class, 'general'])->name('general');
        Route::put('/storefront', [A\SettingsController::class, 'storefront'])->name('storefront');
        Route::put('/payments/{method}', [A\SettingsController::class, 'payment'])->name('payment');
        Route::post('/shipping', [A\SettingsController::class, 'saveZone'])->name('zones.save');
        Route::delete('/shipping/{zone}', [A\SettingsController::class, 'deleteZone'])->name('zones.delete');
        Route::put('/tax', [A\SettingsController::class, 'tax'])->name('tax');
        Route::put('/currency', [A\SettingsController::class, 'currency'])->name('currency');
        Route::post('/domains', [A\SettingsController::class, 'addDomain'])->name('domains.add');
        Route::delete('/domains/{domain}', [A\SettingsController::class, 'removeDomain'])->name('domains.remove');
    });
    Route::middleware('store.staff:staff.manage')->group(function () {
        Route::get('/staff', [A\StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [A\StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [A\StaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff}', [A\StaffController::class, 'destroy'])->name('staff.destroy');
        Route::post('/staff/tokens', [A\StaffController::class, 'createToken'])->name('tokens.store');
        Route::delete('/staff/tokens/{token}', [A\StaffController::class, 'revokeToken'])->whereNumber('token')->name('tokens.destroy');
        Route::post('/roles', [A\StaffController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [A\StaffController::class, 'updateRole'])->name('roles.update');
    });
});
