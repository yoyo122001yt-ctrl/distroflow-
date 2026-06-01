<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseInventoryController;
use App\Http\Controllers\Api\RetailStoreController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\TruckController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DriverMobileController;
use App\Http\Controllers\Api\SettingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Warehouse Operations
    Route::prefix('warehouse')->group(function () {
        Route::post('/receive', [WarehouseInventoryController::class, 'receive']);
        Route::get('/inventory', [WarehouseInventoryController::class, 'inventory']);
        Route::post('/pick', [WarehouseInventoryController::class, 'pick']);
        Route::post('/load', [WarehouseInventoryController::class, 'load']);
        Route::post('/adjust', [WarehouseInventoryController::class, 'adjust']);
        Route::get('/expiring', [WarehouseInventoryController::class, 'expiring']);
    });

    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('products/{id}/batches', [ProductController::class, 'batches']);

    // Retail Stores
    Route::apiResource('stores', RetailStoreController::class);
    Route::get('stores/{id}/orders', [RetailStoreController::class, 'orders']);
    Route::get('stores/{id}/balance', [RetailStoreController::class, 'balance']);
    Route::post('stores/{id}/credit-limit', [RetailStoreController::class, 'updateCreditLimit']);
    Route::post('stores/{id}/prices', [RetailStoreController::class, 'setPrices']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Purchase Orders
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);

    // Sales Orders
    Route::apiResource('orders', SalesOrderController::class);
    Route::post('orders/{id}/approve', [SalesOrderController::class, 'approve']);
    Route::post('orders/{id}/assign-route', [SalesOrderController::class, 'assignRoute']);
    Route::post('orders/{id}/cancel', [SalesOrderController::class, 'cancel']);

    // Routes
    Route::apiResource('routes', RouteController::class);
    Route::get('routes/{id}/stops', [RouteController::class, 'stops']);
    Route::post('routes/{id}/optimize', [RouteController::class, 'optimize']);
    Route::post('routes/{id}/assign', [RouteController::class, 'assign']);
    Route::get('routes/{id}/manifest', [RouteController::class, 'manifest']);

    // Drivers
    Route::apiResource('drivers', DriverController::class);
    Route::get('drivers/{id}/settlements', [DriverController::class, 'settlements']);

    // Trucks
    Route::apiResource('trucks', TruckController::class);

    // Deliveries
    Route::apiResource('deliveries', DeliveryController::class);
    Route::get('deliveries/{id}/stops', [DeliveryController::class, 'stops']);
    Route::post('deliveries/{id}/complete', [DeliveryController::class, 'complete']);

    // Settlements
    Route::apiResource('settlements', SettlementController::class);
    Route::post('settlements/{id}/approve', [SettlementController::class, 'approve']);
    Route::get('settlements/unreconciled', [SettlementController::class, 'unreconciled']);

    // Invoices
    Route::get('invoices', [App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::post('invoices/{id}/send', [App\Http\Controllers\Api\InvoiceController::class, 'send']);
    Route::get('invoices/{id}/pdf', [App\Http\Controllers\Api\InvoiceController::class, 'pdf']);
    Route::post('invoices/{id}/record-payment', [App\Http\Controllers\Api\InvoiceController::class, 'recordPayment']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('route-settlement', [ReportController::class, 'routeSettlement']);
        Route::get('sales-by-store', [ReportController::class, 'salesByStore']);
        Route::get('sales-by-product', [ReportController::class, 'salesByProduct']);
        Route::get('expiry', [ReportController::class, 'expiry']);
        Route::get('driver-performance', [ReportController::class, 'driverPerformance']);
        Route::get('credit-aging', [ReportController::class, 'creditAging']);
        Route::get('profit-by-route', [ReportController::class, 'profitByRoute']);
        Route::get('truck-inventory', [ReportController::class, 'truckInventory']);
        Route::post('export', [ReportController::class, 'export']);
    });

    // Settings
    Route::get('settings', [SettingController::class, 'index']);
    Route::post('settings', [SettingController::class, 'update']);
});

// Driver Mobile Routes (authenticated with driver role)
Route::middleware(['auth:sanctum', 'role:driver'])->prefix('driver')->group(function () {
    Route::get('/today-route', [DriverMobileController::class, 'todayRoute']);
    Route::get('/route-stops', [DriverMobileController::class, 'routeStops']);
    Route::get('/stop/{stopId}', [DriverMobileController::class, 'stopDetail']);
    Route::post('/stop/{stopId}/arrive', [DriverMobileController::class, 'arrive']);
    Route::post('/stop/{stopId}/deliver', [DriverMobileController::class, 'deliver']);
    Route::post('/stop/{stopId}/collect-payment', [DriverMobileController::class, 'collectPayment']);
    Route::post('/stop/{stopId}/signature', [DriverMobileController::class, 'signature']);
    Route::post('/stop/{stopId}/photos', [DriverMobileController::class, 'photos']);
    Route::get('/truck-inventory', [DriverMobileController::class, 'truckInventory']);
    Route::post('/truck-inventory/sync', [DriverMobileController::class, 'syncInventory']);
    Route::post('/start-shift', [DriverMobileController::class, 'startShift']);
    Route::post('/end-shift', [DriverMobileController::class, 'endShift']);
});
