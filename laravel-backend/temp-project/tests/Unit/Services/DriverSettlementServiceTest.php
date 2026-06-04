<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\DriverSettlementService;
use App\Models\DriverSettlement;
use App\Models\User;
use App\Models\Truck;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\DeliveryStop;
use App\Models\RouteStop;
use App\Models\TruckInventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Batch;
use App\Models\RetailStore;
use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\Warehouse;
use Database\Factories\UserFactory;
use Database\Factories\ProductFactory;
use Database\Factories\RetailStoreFactory;

class DriverSettlementServiceTest extends TestCase
{
    private DriverSettlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DriverSettlementService();
    }

    public function test_calculate_settlement_creates_settlement_with_correct_calculations(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'Beverages', 'slug' => 'beverages']);
        $product = ProductFactory::new()->create([
            'category_id' => $category->id,
            'selling_price' => 10.00,
            'cost_price' => 6.00,
        ]);
        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-001',
            'quantity' => 200,
            'available_quantity' => 200,
            'cost_price' => 6.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);

        $product2 = ProductFactory::new()->create([
            'category_id' => $category->id,
            'selling_price' => 25.00,
            'cost_price' => 15.00,
        ]);
        $batch2 = Batch::create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'B-002',
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 15.00,
            'expiry_date' => '2026-12-31',
            'received_date' => today(),
            'status' => 'available',
        ]);

        $store = RetailStoreFactory::new()->create();
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'ABC-1234',
            'status' => 'available',
        ]);
        $route = Route::create([
            'code' => 'R-001',
            'name' => 'Route 1',
            'warehouse_id' => $warehouse->id,
        ]);

        $routeStop = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
        ]);

        $routeAssignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => today(),
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-001',
            'route_assignment_id' => $routeAssignment->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'delivery_date' => today(),
            'status' => 'completed',
        ]);

        $deliveryStop = DeliveryStop::create([
            'delivery_id' => $delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
        ]);

        $salesOrder = SalesOrder::create([
            'order_number' => 'ORD-001',
            'retail_store_id' => $store->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $driver->id,
            'order_date' => today(),
            'status' => 'completed',
        ]);

        $orderItem = OrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product->id,
            'quantity_ordered' => 12,
            'unit_price' => 10.00,
            'total_price' => 120.00,
        ]);

        $orderItem2 = OrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $product2->id,
            'quantity_ordered' => 6,
            'unit_price' => 25.00,
            'total_price' => 150.00,
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'sales_order_id' => $salesOrder->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity_loaded' => 12,
            'quantity_delivered' => 10,
            'quantity_returned' => 2,
            'unit_price' => 10.00,
            'total_price' => 120.00,
        ]);

        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'sales_order_id' => $salesOrder->id,
            'order_item_id' => $orderItem2->id,
            'product_id' => $product2->id,
            'batch_id' => $batch2->id,
            'quantity_loaded' => 6,
            'quantity_delivered' => 5,
            'quantity_returned' => 1,
            'unit_price' => 25.00,
            'total_price' => 150.00,
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $deliveryStop->id,
            'delivery_id' => $delivery->id,
            'sales_order_id' => $salesOrder->id,
            'amount' => 200.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        DeliveryReturn::create([
            'delivery_id' => $delivery->id,
            'delivery_stop_id' => $deliveryStop->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'return_reason' => 'expired',
            'condition' => 'expired',
        ]);

        DeliveryReturn::create([
            'delivery_id' => $delivery->id,
            'delivery_stop_id' => $deliveryStop->id,
            'product_id' => $product2->id,
            'batch_id' => $batch2->id,
            'quantity' => 1,
            'return_reason' => 'damaged',
            'condition' => 'damaged',
        ]);

        TruckInventory::create([
            'truck_id' => $truck->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'starting_quantity' => 100,
            'quantity' => 88,
        ]);

        TruckInventory::create([
            'truck_id' => $truck->id,
            'product_id' => $product2->id,
            'batch_id' => $batch2->id,
            'starting_quantity' => 50,
            'quantity' => 44,
        ]);

        $settlement = $this->service->calculateSettlement($delivery->id);

        $this->assertInstanceOf(DriverSettlement::class, $settlement);
        $this->assertEquals($driver->id, $settlement->driver_id);
        $this->assertEquals($routeAssignment->id, $settlement->route_assignment_id);
        $this->assertEquals($delivery->id, $settlement->delivery_id);

        $expectedTotalSales = (10 * 10.00) + (5 * 25.00);
        $this->assertEquals($expectedTotalSales, (float)$settlement->total_sales);

        $expectedTotalReturns = (2 * 6.00) + (1 * 15.00);
        $this->assertEquals($expectedTotalReturns, (float)$settlement->total_returns);

        $expectedCash = $expectedTotalSales - $expectedTotalReturns;
        $this->assertEquals($expectedCash, (float)$settlement->expected_cash);
        $this->assertEquals(200.00, (float)$settlement->actual_cash);

        $expectedCashVariance = $expectedCash - 200.00;
        $this->assertEquals($expectedCashVariance, (float)$settlement->cash_variance);

        $expectedStartingInv = (100 * 6.00) + (50 * 15.00);
        $this->assertEquals($expectedStartingInv, (float)$settlement->starting_inventory_value);

        $expectedLoaded = (12 * 10.00) + (6 * 25.00);
        $this->assertEquals($expectedLoaded, (float)$settlement->loaded_value);

        $expectedSalesValue = $expectedTotalSales;
        $this->assertEquals($expectedSalesValue, (float)$settlement->sales_value);

        $expectedReturnsValue = $expectedTotalReturns;
        $this->assertEquals($expectedReturnsValue, (float)$settlement->returns_value);

        $expectedEndInv = $expectedStartingInv + $expectedLoaded - $expectedSalesValue - $expectedReturnsValue;
        $this->assertEquals($expectedEndInv, (float)$settlement->expected_end_inventory_value);

        $expectedActualEnd = (88 * 6.00) + (44 * 15.00);
        $this->assertEquals($expectedActualEnd, (float)$settlement->actual_end_inventory_value);

        $expectedInventoryVariance = $expectedEndInv - $expectedActualEnd;
        $this->assertEquals($expectedInventoryVariance, (float)$settlement->inventory_variance);
    }

    public function test_settlement_is_flagged_when_cash_variance_exceeds_threshold(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['category_id' => $category->id, 'selling_price' => 10.00, 'cost_price' => 5.00]);
        $batch = Batch::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => 'B-001', 'quantity' => 100, 'available_quantity' => 100, 'cost_price' => 5.00, 'expiry_date' => '2026-12-31', 'received_date' => today(), 'status' => 'available']);
        $store = RetailStoreFactory::new()->create();
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create(['code' => 'TRK-001', 'plate_number' => 'ABC-1234', 'status' => 'available']);
        $route = Route::create(['code' => 'R-001', 'name' => 'Route 1', 'warehouse_id' => $warehouse->id]);
        $routeStop = RouteStop::create(['route_id' => $route->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);
        $delivery = Delivery::create(['delivery_number' => 'DEL-002', 'route_assignment_id' => $routeAssignment->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'delivery_date' => today(), 'status' => 'completed']);
        $deliveryStop = DeliveryStop::create(['delivery_id' => $delivery->id, 'route_stop_id' => $routeStop->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $salesOrder = SalesOrder::create(['order_number' => 'ORD-002', 'retail_store_id' => $store->id, 'warehouse_id' => $warehouse->id, 'created_by' => $driver->id, 'order_date' => today(), 'status' => 'completed']);
        $orderItem = OrderItem::create(['sales_order_id' => $salesOrder->id, 'product_id' => $product->id, 'quantity_ordered' => 10, 'unit_price' => 10.00, 'total_price' => 100.00]);

        DeliveryItem::create(['delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'order_item_id' => $orderItem->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'quantity_loaded' => 10, 'quantity_delivered' => 10, 'quantity_returned' => 0, 'unit_price' => 10.00, 'total_price' => 100.00]);

        DeliveryPayment::create(['delivery_stop_id' => $deliveryStop->id, 'delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'amount' => 0.00, 'payment_method' => 'cash', 'status' => 'completed']);

        TruckInventory::create(['truck_id' => $truck->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'starting_quantity' => 100, 'quantity' => 100]);

        $settlement = $this->service->calculateSettlement($delivery->id);

        $this->assertEquals('flagged', $settlement->status);
        $this->assertGreaterThan(10, abs((float)$settlement->cash_variance));
    }

    public function test_settlement_is_pending_when_variance_within_thresholds(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['category_id' => $category->id, 'selling_price' => 10.00, 'cost_price' => 10.00]);
        $batch = Batch::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => 'B-002', 'quantity' => 200, 'available_quantity' => 200, 'cost_price' => 10.00, 'expiry_date' => '2026-12-31', 'received_date' => today(), 'status' => 'available']);
        $store = RetailStoreFactory::new()->create();
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create(['code' => 'TRK-002', 'plate_number' => 'XYZ-5678', 'status' => 'available']);
        $route = Route::create(['code' => 'R-002', 'name' => 'Route 2', 'warehouse_id' => $warehouse->id]);
        $routeStop = RouteStop::create(['route_id' => $route->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);
        $delivery = Delivery::create(['delivery_number' => 'DEL-003', 'route_assignment_id' => $routeAssignment->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'delivery_date' => today(), 'status' => 'completed']);
        $deliveryStop = DeliveryStop::create(['delivery_id' => $delivery->id, 'route_stop_id' => $routeStop->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $salesOrder = SalesOrder::create(['order_number' => 'ORD-003', 'retail_store_id' => $store->id, 'warehouse_id' => $warehouse->id, 'created_by' => $driver->id, 'order_date' => today(), 'status' => 'completed']);
        $orderItem = OrderItem::create(['sales_order_id' => $salesOrder->id, 'product_id' => $product->id, 'quantity_ordered' => 5, 'unit_price' => 10.00, 'total_price' => 50.00]);

        DeliveryItem::create(['delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'order_item_id' => $orderItem->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'quantity_loaded' => 5, 'quantity_delivered' => 5, 'quantity_returned' => 0, 'unit_price' => 10.00, 'total_price' => 50.00]);

        DeliveryPayment::create(['delivery_stop_id' => $deliveryStop->id, 'delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'amount' => 50.00, 'payment_method' => 'cash', 'status' => 'completed']);

        TruckInventory::create(['truck_id' => $truck->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'starting_quantity' => 100, 'quantity' => 100]);

        $settlement = $this->service->calculateSettlement($delivery->id);

        $this->assertEquals('pending', $settlement->status);
        $this->assertEquals(0, (float)$settlement->cash_variance);
        $this->assertEquals(0, (float)$settlement->inventory_variance);
    }

    public function test_approve_settlement_updates_status_and_approver(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'WH-01']);
        $category = ProductCategory::create(['name' => 'General', 'slug' => 'general']);
        $product = ProductFactory::new()->create(['category_id' => $category->id, 'selling_price' => 10.00, 'cost_price' => 5.00]);
        $batch = Batch::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'batch_number' => 'B-003', 'quantity' => 100, 'available_quantity' => 100, 'cost_price' => 5.00, 'expiry_date' => '2026-12-31', 'received_date' => today(), 'status' => 'available']);
        $store = RetailStoreFactory::new()->create();
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $manager = UserFactory::new()->create(['role' => 'admin']);
        $truck = Truck::create(['code' => 'TRK-003', 'plate_number' => 'DEF-9012', 'status' => 'available']);
        $route = Route::create(['code' => 'R-003', 'name' => 'Route 3', 'warehouse_id' => $warehouse->id]);
        $routeStop = RouteStop::create(['route_id' => $route->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);
        $delivery = Delivery::create(['delivery_number' => 'DEL-004', 'route_assignment_id' => $routeAssignment->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'delivery_date' => today(), 'status' => 'completed']);
        $deliveryStop = DeliveryStop::create(['delivery_id' => $delivery->id, 'route_stop_id' => $routeStop->id, 'retail_store_id' => $store->id, 'stop_order' => 1]);
        $salesOrder = SalesOrder::create(['order_number' => 'ORD-004', 'retail_store_id' => $store->id, 'warehouse_id' => $warehouse->id, 'created_by' => $driver->id, 'order_date' => today(), 'status' => 'completed']);
        $orderItem = OrderItem::create(['sales_order_id' => $salesOrder->id, 'product_id' => $product->id, 'quantity_ordered' => 5, 'unit_price' => 10.00, 'total_price' => 50.00]);

        DeliveryItem::create(['delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'order_item_id' => $orderItem->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'quantity_loaded' => 5, 'quantity_delivered' => 5, 'quantity_returned' => 0, 'unit_price' => 10.00, 'total_price' => 50.00]);
        DeliveryPayment::create(['delivery_stop_id' => $deliveryStop->id, 'delivery_id' => $delivery->id, 'sales_order_id' => $salesOrder->id, 'amount' => 50.00, 'payment_method' => 'cash', 'status' => 'completed']);
        TruckInventory::create(['truck_id' => $truck->id, 'product_id' => $product->id, 'batch_id' => $batch->id, 'starting_quantity' => 100, 'quantity' => 100]);

        $settlement = $this->service->calculateSettlement($delivery->id);

        $approved = $this->service->approveSettlement($settlement->id, $manager->id);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($manager->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_approve_settlement_throws_exception_for_nonexistent_settlement(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->approveSettlement(99999, 1);
    }

    public function test_needs_manager_review_returns_true_when_cash_variance_exceeds_threshold(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create(['code' => 'TRK-MGR', 'plate_number' => 'MGR-001', 'status' => 'available']);
        $route = Route::create(['code' => 'R-MGR', 'name' => 'Manager Route', 'warehouse_id' => $warehouse->id]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);
        $delivery = Delivery::create(['delivery_number' => 'DEL-MGR', 'route_assignment_id' => $routeAssignment->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'delivery_date' => today(), 'status' => 'completed']);

        $settlement = DriverSettlement::create([
            'driver_id' => $driver->id,
            'route_assignment_id' => $routeAssignment->id,
            'delivery_id' => $delivery->id,
            'settlement_date' => today(),
            'status' => 'pending',
            'total_sales' => 100,
            'total_returns' => 0,
            'expected_cash' => 100,
            'actual_cash' => 50,
            'cash_variance' => 50,
            'starting_inventory_value' => 0,
            'loaded_value' => 0,
            'sales_value' => 0,
            'returns_value' => 0,
            'expected_end_inventory_value' => 0,
            'actual_end_inventory_value' => 0,
            'inventory_variance' => 0,
        ]);

        $this->assertTrue($this->service->needsManagerReview($settlement));
    }

    public function test_needs_manager_review_returns_false_when_variances_are_within_thresholds(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create(['code' => 'TRK-MGR2', 'plate_number' => 'MGR-002', 'status' => 'available']);
        $route = Route::create(['code' => 'R-MGR2', 'name' => 'Manager Route 2', 'warehouse_id' => $warehouse->id]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);
        $delivery = Delivery::create(['delivery_number' => 'DEL-MGR2', 'route_assignment_id' => $routeAssignment->id, 'driver_id' => $driver->id, 'truck_id' => $truck->id, 'delivery_date' => today(), 'status' => 'completed']);

        $settlement = DriverSettlement::create([
            'driver_id' => $driver->id,
            'route_assignment_id' => $routeAssignment->id,
            'delivery_id' => $delivery->id,
            'settlement_date' => today(),
            'status' => 'pending',
            'total_sales' => 100,
            'total_returns' => 0,
            'expected_cash' => 100,
            'actual_cash' => 100,
            'cash_variance' => 0,
            'starting_inventory_value' => 0,
            'loaded_value' => 0,
            'sales_value' => 0,
            'returns_value' => 0,
            'expected_end_inventory_value' => 0,
            'actual_end_inventory_value' => 0,
            'inventory_variance' => 0,
        ]);

        $this->assertFalse($this->service->needsManagerReview($settlement));
    }
}
