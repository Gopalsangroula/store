<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ShopController;
use Illuminate\Support\Facades\Route;

// The shop. Every route here runs for the store found from the host name.

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'index'])->name('shop');
Route::get('/category/{slug}', [ShopController::class, 'category'])->name('category');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product');
Route::post('/product/{slug}/review', [ProductController::class, 'review'])->middleware('throttle:coupon')->name('product.review');

Route::get('/cart', [CartController::class, 'show'])->name('cart');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/buy-now', [CartController::class, 'buyNow'])->name('cart.buy');
Route::patch('/cart/{item}', [CartController::class, 'update'])->whereNumber('item')->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');
Route::post('/cart/coupon', [CartController::class, 'coupon'])->middleware('throttle:coupon')->name('cart.coupon');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:coupon')->name('checkout.quote');
Route::post('/checkout', [CheckoutController::class, 'place'])->middleware('throttle:checkout')->name('checkout.place');

Route::get('/order/{number}', [OrderController::class, 'show'])->name('order');
Route::get('/order/{number}/pay', [OrderController::class, 'pay'])->name('order.pay');
Route::post('/order/{number}/pay/manual', [OrderController::class, 'submitManual'])->middleware('throttle:checkout')->name('order.pay.manual');
Route::post('/order/{number}/pay/khalti', [OrderController::class, 'khalti'])->middleware('throttle:checkout')->name('order.pay.khalti');
Route::get('/payment/khalti/return', [OrderController::class, 'khaltiReturn'])->name('payment.khalti.return');
Route::get('/order/{number}/invoice', [OrderController::class, 'invoice'])->name('order.invoice');

Route::get('/track', [OrderController::class, 'track'])->name('track');
Route::post('/track', [OrderController::class, 'trackLookup'])->middleware('throttle:track')->name('track.lookup');

Route::get('/currency/{code}', [PageController::class, 'currency'])->where('code', '[A-Za-z]{3}')->name('currency');

Route::middleware('guest:customer')->group(function () {
    Route::get('/account/login', [AccountController::class, 'loginForm'])->name('account.login');
    Route::post('/account/login', [AccountController::class, 'login'])->middleware('throttle:login');
    Route::get('/account/register', [AccountController::class, 'registerForm'])->name('account.register');
    Route::post('/account/register', [AccountController::class, 'register'])->middleware('throttle:login');
});

Route::middleware('auth:customer')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account/logout', [AccountController::class, 'logout'])->name('account.logout');
});

Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('page');
