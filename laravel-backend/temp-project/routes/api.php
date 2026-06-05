<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WarehouseInventoryController;
use App\Http\Controllers\Api\WarehouseController;
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
use App\Http\Controllers\Api\PickingController;
use App\Http\Controllers\Api\LoadingController;

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

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [App\Http\Controllers\Api\DashboardController::class, 'stats']);
        Route::get('sales-trend', [App\Http\Controllers\Api\DashboardController::class, 'salesTrend']);
    });

    // Warehouse Operations
    Route::apiResource('warehouses', WarehouseController::class)->only(['index']);
    Route::prefix('warehouse')->group(function () {
        Route::post('/receive', [WarehouseInventoryController::class, 'receive']);
        Route::get('/inventory', [WarehouseInventoryController::class, 'inventory']);
        Route::post('/pick', [WarehouseInventoryController::class, 'pick']);
        Route::post('/load', [WarehouseInventoryController::class, 'load']);
        Route::post('/adjust', [WarehouseInventoryController::class, 'adjust']);
        Route::get('/expiring', [WarehouseInventoryController::class, 'expiring']);
    });

    // Inventory (frontend uses /inventory/batches)
    Route::get('inventory/batches', [WarehouseInventoryController::class, 'inventory']);
    Route::post('inventory/batches/{id}/adjust', [WarehouseInventoryController::class, 'adjust']);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::get('products/{id}/batches', [ProductController::class, 'batches']);

    // Categories
    Route::get('categories', [App\Http\Controllers\Api\CategoryController::class, 'index']);
    Route::post('categories', [App\Http\Controllers\Api\CategoryController::class, 'store']);

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

    // Sales Orders (specific routes BEFORE apiResource)
    Route::get('orders/status-breakdown', [SalesOrderController::class, 'statusBreakdown']);
    Route::post('orders/{id}/approve', [SalesOrderController::class, 'approve']);
    Route::post('orders/{id}/assign-route', [SalesOrderController::class, 'assignRoute']);
    Route::post('orders/{id}/cancel', [SalesOrderController::class, 'cancel']);
    Route::apiResource('orders', SalesOrderController::class);

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

    // Settlements (specific routes BEFORE apiResource to avoid route collision)
    Route::get('settlements/unreconciled', [SettlementController::class, 'unreconciled']);
    Route::post('settlements/generate', [SettlementController::class, 'generate']);
    Route::post('settlements/{id}/approve', [SettlementController::class, 'approve']);
    Route::post('settlements/{id}/reject', [SettlementController::class, 'reject']);
    Route::apiResource('settlements', SettlementController::class);

    // Invoices
    Route::get('invoices', [App\Http\Controllers\Api\InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [App\Http\Controllers\Api\InvoiceController::class, 'show']);
    Route::post('invoices/{id}/send', [App\Http\Controllers\Api\InvoiceController::class, 'send']);
    Route::get('invoices/{id}/pdf', [App\Http\Controllers\Api\InvoiceController::class, 'pdf']);
    Route::post('invoices/{id}/record-payment', [App\Http\Controllers\Api\InvoiceController::class, 'recordPayment']);

    // Settings
    Route::get('settings', [SettingController::class, 'index']);
    Route::post('settings', [SettingController::class, 'update']);

    // Warehouse Tracking (admin, manager, warehouse_manager)
    Route::middleware('role:admin,warehouse_manager,manager')->prefix('warehouse')->group(function () {
        Route::get('/active-drivers', [App\Http\Controllers\Warehouse\TrackingController::class, 'getActiveDrivers']);
        Route::get('/driver/{driverId}/route', [App\Http\Controllers\Warehouse\TrackingController::class, 'getDriverRoute']);
    });

    // Picking
    Route::prefix('picking')->group(function () {
        Route::get('/', [PickingController::class, 'index']);
        Route::post('/generate', [PickingController::class, 'generate']);
        Route::post('/{id}/start', [PickingController::class, 'start']);
        Route::get('/{pickId}', [PickingController::class, 'show']);
        Route::post('/{pickId}/items/{itemId}/pick', [PickingController::class, 'pickItem']);
        Route::post('/{id}/complete', [PickingController::class, 'complete']);
    });

    // Loading
    Route::prefix('loading')->group(function () {
        Route::get('/', [LoadingController::class, 'index']);
        Route::post('/', [LoadingController::class, 'store']);
        Route::post('/{id}/start', [LoadingController::class, 'start']);
        Route::post('/{id}/complete', [LoadingController::class, 'complete']);
    });

    // Store Portal API
    Route::prefix('store')->middleware('role:store')->group(function () {
        Route::get('/products', [App\Http\Controllers\Api\StoreApiController::class, 'products']);
        Route::get('/products/search', [App\Http\Controllers\Api\StoreApiController::class, 'searchProducts']);
        Route::get('/products/popular', [App\Http\Controllers\Api\StoreApiController::class, 'popularProducts']);
        Route::get('/products/recommended', [App\Http\Controllers\Api\StoreApiController::class, 'recommendedProducts']);
        Route::get('/categories', [App\Http\Controllers\Api\StoreApiController::class, 'categories']);
        Route::get('/cart', [App\Http\Controllers\Api\StoreApiController::class, 'getCart']);
        Route::post('/cart/update', [App\Http\Controllers\Api\StoreApiController::class, 'updateCart']);
        Route::delete('/cart/item/{id}', [App\Http\Controllers\Api\StoreApiController::class, 'removeFromCart']);
        Route::post('/orders/place', [App\Http\Controllers\Api\StoreApiController::class, 'placeOrder']);
        Route::get('/orders', [App\Http\Controllers\Api\StoreApiController::class, 'getOrders']);
        Route::get('/orders/{id}', [App\Http\Controllers\Api\StoreApiController::class, 'getOrderDetail']);
        Route::get('/track/{id}', [App\Http\Controllers\Api\StoreApiController::class, 'trackDelivery']);
    });

    // Import
    Route::prefix('products')->group(function () {
        Route::post('/import', [App\Http\Controllers\Api\ImportController::class, 'importProducts']);
        Route::get('/import/template', [App\Http\Controllers\Api\ImportController::class, 'downloadTemplate']);
    });

    // Sync (driver offline)
    Route::post('/driver/sync', [App\Http\Controllers\Api\SyncController::class, 'sync'])->middleware('role:driver');

    // Activity Logs
    Route::get('/activity-logs', [App\Http\Controllers\Api\ActivityLogController::class, 'index']);
    Route::get('/activity-logs/export', [App\Http\Controllers\Api\ActivityLogController::class, 'export']);

    // Enhanced Reports
    Route::prefix('reports')->group(function () {
        Route::get('export/{type}', [ReportController::class, 'exportReport']);
        Route::get('{type}', [ReportController::class, 'show']);
    });

    // Profile update (for store portal)
    Route::put('/auth/profile', [App\Http\Controllers\Api\AuthController::class, 'updateProfile']);
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
    Route::get('/my-deliveries', [App\Http\Controllers\Driver\DriverTripController::class, 'getMyDeliveries']);
    Route::post('/stop/{stopId}/complete', [App\Http\Controllers\Driver\DriverTripController::class, 'markStopComplete']);
});
