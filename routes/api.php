<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

// /api/v1 for the store found from the host name.
// Public: catalog. Staff token (Sanctum, created in the store's Staff page): orders.

Route::middleware('throttle:api')->group(function () {
    Route::get('/products', [CatalogController::class, 'products'])->name('products');
    Route::get('/products/{slug}', [CatalogController::class, 'product'])->name('product');
    Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/{number}', [OrderController::class, 'show'])->name('orders.show');
    });
});
