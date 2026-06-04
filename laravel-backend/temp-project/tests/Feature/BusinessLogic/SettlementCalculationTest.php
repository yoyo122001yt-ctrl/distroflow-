<?php

namespace Tests\Feature\BusinessLogic;

use App\Models\Batch;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\DeliveryStop;
use App\Models\DriverProfile;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RetailStore;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\SalesOrder;
use App\Models\Truck;
use App\Models\TruckInventory;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DriverSettlementService;
use Tests\TestCase;

class SettlementCalculationTest extends TestCase
{
    private DriverSettlementService $settlementService;

    private Warehouse $warehouse;
    private Route $route;
    private Truck $truck;
    private User $driver;
    private User $manager;
    private RetailStore $store;
    private Product $product;
    private Batch $batch;
    private RouteAssignment $assignment;
    private SalesOrder $salesOrder;
    private OrderItem $orderItem;
    private Delivery $delivery;
    private DeliveryStop $deliveryStop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settlementService = app(DriverSettlementService::class);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
        ]);

        $this->route = Route::create([
            'code' => 'RTE-NORTH',
            'name' => 'North Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $this->truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'ABC-1234',
            'status' => 'active',
        ]);

        $this->driver = User::create([
            'name' => 'John Driver',
            'email' => 'driver@example.com',
            'password' => 'password',
            'role' => 'driver',
        ]);

        DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'LIC-12345',
            'status' => 'available',
        ]);

        $this->manager = User::create([
            'name' => 'Admin Manager',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Corner Grocery',
            'status' => 'active',
        ]);

        $routeStop = RouteStop::create([
            'route_id' => $this->route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
        ]);

        $this->assignment = RouteAssignment::create([
            'route_id' => $this->route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'assignment_date' => today(),
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Test Soda',
            'sku' => 'SODA-001',
            'selling_price' => 10.00,
            'cost_price' => 10.00,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-SODA-01',
            'expiry_date' => now()->addDays(90),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 10.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->salesOrder = SalesOrder::create([
            'order_number' => 'ORD-SETTLE-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->manager->id,
            'order_date' => today(),
            'status' => 'approved',
            'subtotal' => 800.00,
            'total' => 800.00,
            'balance_due' => 800.00,
        ]);

        $this->orderItem = OrderItem::create([
            'sales_order_id' => $this->salesOrder->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_picked' => 100,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 1000.00,
            'status' => 'delivered',
        ]);

        $this->delivery = Delivery::create([
            'delivery_number' => 'DEL-SETTLE-001',
            'route_assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => today(),
            'status' => 'completed',
            'total_sales' => 800.00,
            'total_collected' => 750.00,
            'total_returns' => 50.00,
        ]);

        $this->deliveryStop = DeliveryStop::create([
            'delivery_id' => $this->delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'status' => 'completed',
        ]);
    }

    public function test_calculates_pending_settlement_when_within_variance_threshold(): void
    {
        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryReturn::create([
            'delivery_id' => $this->delivery->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity' => 5,
            'return_reason' => 'expired',
            'condition' => 'expired',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 750.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 15,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $this->assertEquals(800.00, (float) $settlement->total_sales);
        $this->assertEquals(50.00, (float) $settlement->total_returns);
        $this->assertEquals(750.00, (float) $settlement->expected_cash);
        $this->assertEquals(750.00, (float) $settlement->actual_cash);
        $this->assertEquals(0, (float) $settlement->cash_variance);

        $this->assertEquals(1000.00, (float) $settlement->loaded_value);
        $this->assertEquals(800.00, (float) $settlement->sales_value);
        $this->assertEquals(50.00, (float) $settlement->returns_value);

        $this->assertEquals(150.00, (float) $settlement->expected_end_inventory_value);
        $this->assertEquals(150.00, (float) $settlement->actual_end_inventory_value);
        $this->assertEquals(0, (float) $settlement->inventory_variance);

        $this->assertEquals('pending', $settlement->status);
        $this->assertEquals($this->delivery->id, $settlement->delivery_id);
        $this->assertEquals($this->driver->id, $settlement->driver_id);
    }

    public function test_marks_settlement_as_flagged_when_cash_variance_exceeds_threshold(): void
    {
        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryReturn::create([
            'delivery_id' => $this->delivery->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity' => 5,
            'return_reason' => 'expired',
            'condition' => 'expired',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 700.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 15,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $this->assertEquals(750.00, (float) $settlement->expected_cash);
        $this->assertEquals(700.00, (float) $settlement->actual_cash);
        $this->assertEquals(50.00, (float) $settlement->cash_variance);

        $this->assertEquals('flagged', $settlement->status);
    }

    public function test_marks_settlement_as_flagged_when_inventory_variance_exceeds_threshold(): void
    {
        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryReturn::create([
            'delivery_id' => $this->delivery->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity' => 5,
            'return_reason' => 'expired',
            'condition' => 'expired',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 750.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 10,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $this->assertEquals(0, (float) $settlement->cash_variance);
        $this->assertEquals(150.00, (float) $settlement->expected_end_inventory_value);
        $this->assertEquals(100.00, (float) $settlement->actual_end_inventory_value);
        $this->assertEquals(50.00, (float) $settlement->inventory_variance);

        $this->assertEquals('flagged', $settlement->status);
    }

    public function test_approve_settlement_changes_status_to_approved(): void
    {
        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 700.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 20,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $this->assertEquals('flagged', $settlement->status);

        $approved = $this->settlementService->approveSettlement($settlement->id, $this->manager->id);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($this->manager->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_needs_manager_review_returns_correctly(): void
    {
        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 800.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 20,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $this->assertFalse($this->settlementService->needsManagerReview($settlement));

        $settlement->cash_variance = 50.00;
        $settlement->save();

        $this->assertTrue($this->settlementService->needsManagerReview($settlement->fresh()));
    }

    public function test_handles_multiple_delivery_items_correctly(): void
    {
        $productB = Product::create([
            'name' => 'Test Juice',
            'sku' => 'JUICE-001',
            'selling_price' => 5.00,
            'cost_price' => 5.00,
        ]);

        $batchB = Batch::create([
            'product_id' => $productB->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-JUICE-01',
            'expiry_date' => now()->addDays(60),
            'quantity' => 200,
            'available_quantity' => 200,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $orderItemB = OrderItem::create([
            'sales_order_id' => $this->salesOrder->id,
            'product_id' => $productB->id,
            'quantity_ordered' => 200,
            'quantity_delivered' => 150,
            'unit_price' => 5.00,
            'total_price' => 750.00,
            'status' => 'delivered',
        ]);

        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $this->orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity_loaded' => 100,
            'quantity_delivered' => 80,
            'unit_price' => 10.00,
            'total_price' => 800.00,
            'status' => 'delivered',
        ]);

        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'order_item_id' => $orderItemB->id,
            'product_id' => $productB->id,
            'batch_id' => $batchB->id,
            'quantity_loaded' => 200,
            'quantity_delivered' => 150,
            'unit_price' => 5.00,
            'total_price' => 750.00,
            'status' => 'delivered',
        ]);

        DeliveryPayment::create([
            'delivery_stop_id' => $this->deliveryStop->id,
            'delivery_id' => $this->delivery->id,
            'sales_order_id' => $this->salesOrder->id,
            'amount' => 1550.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'starting_quantity' => 0,
            'quantity' => 20,
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $productB->id,
            'batch_id' => $batchB->id,
            'starting_quantity' => 0,
            'quantity' => 50,
        ]);

        $settlement = $this->settlementService->calculateSettlement($this->delivery->id);

        $expectedTotalSales = (80 * 10.00) + (150 * 5.00);
        $this->assertEquals($expectedTotalSales, (float) $settlement->total_sales);

        $expectedExpectedCash = $expectedTotalSales;
        $this->assertEquals($expectedExpectedCash, (float) $settlement->expected_cash);
        $this->assertEquals(1550.00, (float) $settlement->actual_cash);
        $this->assertEquals(0, (float) $settlement->cash_variance);

        $expectedLoadedValue = (100 * 10.00) + (200 * 5.00);
        $this->assertEquals($expectedLoadedValue, (float) $settlement->loaded_value);

        $expectedEndValue = (100 * 10.00) + (200 * 5.00) - (80 * 10.00) - (150 * 5.00);
        $this->assertEquals($expectedEndValue, (float) $settlement->expected_end_inventory_value);

        $actualEndValue = (20 * 10.00) + (50 * 5.00);
        $this->assertEquals($actualEndValue, (float) $settlement->actual_end_inventory_value);

        $this->assertEquals('pending', $settlement->status);
    }
}
