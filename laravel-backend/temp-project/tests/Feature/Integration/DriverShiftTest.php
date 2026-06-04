<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Batch;
use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RouteAssignment;
use App\Models\Truck;
use App\Models\TruckInventory;
use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DriverProfile;
use App\Models\DriverTrip;
use App\Models\DriverSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver;
    private Warehouse $warehouse;
    private RetailStore $store;
    private Product $product;
    private Product $product2;
    private Truck $truck;
    private Route $route;
    private RouteStop $routeStop;
    private RouteAssignment $assignment;
    private Batch $batch;
    private Batch $batch2;
    private SalesOrder $order;
    private OrderItem $orderItem;
    private OrderItem $orderItem2;

    protected function setUp(): void
    {
        parent::setUp();

        User::resolveRelationUsing('retailStore', function ($user) {
            return $user->belongsTo(RetailStore::class, 'warehouse_id', 'warehouse_id');
        });

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->driver = User::create([
            'name' => 'Test Driver',
            'email' => 'driver@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'driver',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'DL-12345',
            'license_expiry' => now()->addYears(2),
            'pay_rate' => 150.00,
            'pay_type' => 'daily',
            'status' => 'available',
        ]);

        $this->store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Test Store',
            'store_type' => 'grocery',
            'contact_person' => 'Omar',
            'phone' => '+201223344556',
            'address' => '10 Main St',
            'city' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'credit_limit' => 50000,
            'current_balance' => 0,
            'payment_terms' => 'net_30',
            'status' => 'active',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $category = ProductCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Supplier Co',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Product A',
            'sku' => 'PRD-A',
            'unit' => 'piece',
            'cost_price' => 10.00,
            'selling_price' => 20.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'name' => 'Product B',
            'sku' => 'PRD-B',
            'unit' => 'piece',
            'cost_price' => 5.00,
            'selling_price' => 12.00,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-001',
            'expiry_date' => now()->addMonths(6),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 10.00,
            'supplier_id' => $supplier->id,
            'received_date' => now()->subDays(5),
            'status' => 'available',
        ]);

        $this->batch2 = Batch::create([
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-002',
            'expiry_date' => now()->addMonths(3),
            'quantity' => 200,
            'available_quantity' => 200,
            'cost_price' => 5.00,
            'supplier_id' => $supplier->id,
            'received_date' => now()->subDays(3),
            'status' => 'available',
        ]);

        $this->truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'ABC-1234',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->route = Route::create([
            'code' => 'RTE-001',
            'name' => 'Test Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $this->routeStop = RouteStop::create([
            'route_id' => $this->route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'is_active' => true,
        ]);

        $this->assignment = RouteAssignment::create([
            'route_id' => $this->route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'assignment_date' => now(),
            'status' => 'scheduled',
        ]);

        $this->order = SalesOrder::create([
            'order_number' => 'ORD-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'total' => 500.00,
            'balance_due' => 500.00,
        ]);

        $this->orderItem = OrderItem::create([
            'sales_order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 25,
            'quantity_picked' => 25,
            'unit_price' => 20.00,
            'total_price' => 500.00,
            'status' => 'picked',
        ]);

        $this->orderItem2 = OrderItem::create([
            'sales_order_id' => $this->order->id,
            'product_id' => $this->product2->id,
            'quantity_ordered' => 0,
            'quantity_picked' => 0,
            'quantity_delivered' => 0,
            'unit_price' => 12.00,
            'total_price' => 0,
            'status' => 'delivered',
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product->id,
            'batch_id' => $this->batch->id,
            'quantity' => 25,
            'starting_quantity' => 25,
        ]);

        TruckInventory::create([
            'truck_id' => $this->truck->id,
            'product_id' => $this->product2->id,
            'batch_id' => $this->batch2->id,
            'quantity' => 0,
            'starting_quantity' => 0,
        ]);
    }

    public function test_driver_start_shift(): void
    {
        $response = $this->actingAs($this->driver)
            ->postJson('/api/driver/start-shift');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['delivery', 'assignment'],
                'message',
            ]);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)
            ->where('delivery_date', today())->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('in_transit', $delivery->status);
        $this->assertNotNull($delivery->started_at);

        $this->assignment->refresh();
        $this->assertEquals('active', $this->assignment->status);

        $this->driver->driverProfile->refresh();
        $this->assertEquals('busy', $this->driver->driverProfile->status);
    }

    public function test_driver_arrives_at_stop(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');

        $response = $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'message']);

        $deliveryStop = DeliveryStop::where('route_stop_id', $this->routeStop->id)->first();
        $this->assertNotNull($deliveryStop);
        $this->assertEquals('arrived', $deliveryStop->status);
        $this->assertNotNull($deliveryStop->arrived_at);
    }

    public function test_driver_delivers_items(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");

        $response = $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $deliveryItem = DeliveryItem::where('product_id', $this->product->id)->first();
        $this->assertNotNull($deliveryItem);
        $this->assertEqualsWithDelta(20, (float) $deliveryItem->quantity_delivered, 0.01);

        $this->orderItem->refresh();
        $this->assertEqualsWithDelta(20, (float) $this->orderItem->quantity_delivered, 0.01);

        $deliveryStop = DeliveryStop::where('route_stop_id', $this->routeStop->id)->first();
        $this->assertEquals('delivered', $deliveryStop->status);
    }

    public function test_driver_collects_payment(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 20,
                    ],
                ],
            ]);

        $response = $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/collect-payment", [
                'amount' => 400.00,
                'payment_method' => 'cash',
                'notes' => 'Cash payment received',
            ]);

        $response->assertStatus(201);

        $payment = DeliveryPayment::where('amount', 400.00)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('cash', $payment->payment_method);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)->first();
        $this->assertEqualsWithDelta(400.00, (float) $delivery->total_collected, 0.01);
    }

    public function test_driver_captures_signature(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 25,
                    ],
                ],
            ]);

        $response = $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/signature", [
                'signature' => 'base64encodedsignaturedata',
                'name' => 'Store Manager',
            ]);

        $response->assertStatus(200);

        $deliveryStop = DeliveryStop::where('route_stop_id', $this->routeStop->id)->first();
        $this->assertEquals('base64encodedsignaturedata', $deliveryStop->signature);
    }

    public function test_driver_ends_shift_and_settlement_calculated(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 25,
                    ],
                ],
            ]);
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/collect-payment", [
                'amount' => 500.00,
                'payment_method' => 'cash',
            ]);

        $response = $this->actingAs($this->driver)
            ->postJson('/api/driver/end-shift', [
                'actual_cash' => 500.00,
                'notes' => 'Shift completed successfully',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['delivery', 'settlement'],
                'message',
            ]);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)->first();
        $this->assertEquals('completed', $delivery->status);
        $this->assertNotNull($delivery->completed_at);

        $settlement = DriverSettlement::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($settlement);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->total_sales, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->expected_cash, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->actual_cash, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $settlement->cash_variance, 0.01);
        $this->assertContains($settlement->status, ['pending', 'flagged']);
    }

    public function test_settlement_flagged_on_cash_variance(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 25,
                    ],
                ],
            ]);
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/collect-payment", [
                'amount' => 500.00,
                'payment_method' => 'cash',
            ]);

        $this->actingAs($this->driver)
            ->postJson('/api/driver/end-shift', [
                'actual_cash' => 480.00,
                'notes' => 'Short by 20',
            ]);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)->first();
        $settlement = DriverSettlement::where('delivery_id', $delivery->id)->first();
        $this->assertEquals('flagged', $settlement->status);
        $this->assertEqualsWithDelta(20.00, (float) $settlement->cash_variance, 0.01);
    }

    public function test_manager_approves_settlement(): void
    {
        $this->actingAs($this->driver)->postJson('/api/driver/start-shift');
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive");
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 25,
                    ],
                ],
            ]);
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/collect-payment", [
                'amount' => 500.00,
                'payment_method' => 'cash',
            ]);

        $this->actingAs($this->driver)
            ->postJson('/api/driver/end-shift', [
                'actual_cash' => 500.00,
            ]);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)->first();
        $settlement = DriverSettlement::where('delivery_id', $delivery->id)->first();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/approve");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement approved']);

        $settlement->refresh();
        $this->assertEquals('approved', $settlement->status);
        $this->assertNotNull($settlement->approved_at);
        $this->assertEquals($this->admin->id, $settlement->approved_by);
    }

    public function test_complete_driver_shift_lifecycle(): void
    {
        // Start shift
        $startResponse = $this->actingAs($this->driver)
            ->postJson('/api/driver/start-shift');
        $startResponse->assertStatus(200);

        $delivery = Delivery::where('route_assignment_id', $this->assignment->id)
            ->where('delivery_date', today())->first();
        $this->assertEquals('in_transit', $delivery->status);

        // Arrive at stop
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/arrive")
            ->assertStatus(200);

        // Deliver items
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/deliver", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 25,
                    ],
                ],
            ])->assertStatus(200);

        // Collect payment
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/collect-payment", [
                'amount' => 500.00,
                'payment_method' => 'cash',
            ])->assertStatus(201);

        // Capture signature
        $this->actingAs($this->driver)
            ->postJson("/api/driver/stop/{$this->routeStop->id}/signature", [
                'signature' => 'delivery_signed_abc123',
            ])->assertStatus(200);

        // End shift
        $endResponse = $this->actingAs($this->driver)
            ->postJson('/api/driver/end-shift', [
                'actual_cash' => 500.00,
                'notes' => 'All deliveries complete',
            ]);
        $endResponse->assertStatus(200);

        // Verify all statuses
        $delivery->refresh();
        $this->assertEquals('completed', $delivery->status);
        $this->assertEqualsWithDelta(500.00, (float) $delivery->total_sales, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $delivery->total_collected, 0.01);

        $this->assignment->refresh();
        $this->assertEquals('completed', $this->assignment->status);

        $this->driver->driverProfile->refresh();
        $this->assertEquals('available', $this->driver->driverProfile->status);

        $this->order->refresh();
        $this->assertEquals('delivered', $this->order->status);

        $this->orderItem->refresh();
        $this->assertEquals('delivered', $this->orderItem->status);

        $settlement = DriverSettlement::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($settlement);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->total_sales, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->expected_cash, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $settlement->actual_cash, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $settlement->cash_variance, 0.01);
    }
}
