<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/login');
});

// Session-based login for driver/warehouse pages
Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');

// Driver Trip Page (session-based auth, requires driver role)
Route::middleware(['auth', \App\Http\Middleware\CheckRole::class . ':driver'])->group(function () {
    Route::get('/driver/trip', [App\Http\Controllers\Driver\DriverTripController::class, 'showTripPage'])->name('driver.trip');
    Route::post('/driver/trip/start', [App\Http\Controllers\Driver\DriverTripController::class, 'startTrip'])->name('driver.trip.start');
    Route::post('/driver/trip/end', [App\Http\Controllers\Driver\DriverTripController::class, 'endTrip'])->name('driver.trip.end');
    Route::post('/driver/location', [App\Http\Controllers\Api\DriverLocationController::class, 'updateLocation'])->name('api.driver.location');
});

// Store Portal (requires store role)
Route::middleware(['auth', \App\Http\Middleware\CheckRole::class . ':store'])->prefix('store')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\StorePortalController::class, 'dashboard'])->name('store.dashboard');
    Route::get('/products', [App\Http\Controllers\StorePortalController::class, 'products'])->name('store.products');
    Route::get('/cart', [App\Http\Controllers\StorePortalController::class, 'cart'])->name('store.cart');
    Route::post('/cart/add', [App\Http\Controllers\StorePortalController::class, 'addToCart'])->name('store.cart.add');
    Route::post('/cart/remove', [App\Http\Controllers\StorePortalController::class, 'removeFromCart'])->name('store.cart.remove');
    Route::post('/cart/update', [App\Http\Controllers\StorePortalController::class, 'updateCart'])->name('store.cart.update');
    Route::post('/order/place', [App\Http\Controllers\StorePortalController::class, 'placeOrder'])->name('store.order.place');
    Route::get('/orders', [App\Http\Controllers\StorePortalController::class, 'orders'])->name('store.orders');
    Route::get('/orders/{id}', [App\Http\Controllers\StorePortalController::class, 'orderDetail'])->name('store.order.detail');
    Route::get('/track/{deliveryId}', [App\Http\Controllers\StorePortalController::class, 'trackDelivery'])->name('store.track');
    Route::get('/profile', [App\Http\Controllers\StorePortalController::class, 'profile'])->name('store.profile');
    Route::post('/profile/update', [App\Http\Controllers\StorePortalController::class, 'updateProfile'])->name('store.profile.update');
});

// WebSocket broadcast auth
Route::post('/broadcasting/auth', [App\Http\Controllers\Api\BroadcastAuthController::class, 'authenticate'])->middleware('auth:sanctum');
