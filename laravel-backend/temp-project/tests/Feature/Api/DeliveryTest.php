<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Truck;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\Batch;
use App\Models\OrderItem;
use App\Models\SalesOrder;
use App\Models\Product;
use App\Models\RetailStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $driver;
    private Warehouse $warehouse;
    private Truck $truck;
    private Route $route;
    private RouteAssignment $routeAssignment;
    private RetailStore $store;
    private Product $product;
    private Batch $batch;
    private SalesOrder $salesOrder;
    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = new User;
        $this->adminUser->name = 'Admin';
        $this->adminUser->email = 'admin@test.com';
        $this->adminUser->password = bcrypt('password');
        $this->adminUser->role = 'admin';
        $this->adminUser->save();

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'is_active' => true,
        ]);

        $this->driver = new User;
        $this->driver->name = 'Driver One';
        $this->driver->email = 'driver@test.com';
        $this->driver->password = bcrypt('password');
        $this->driver->role = 'driver';
        $this->driver->is_active = true;
        $this->driver->save();

        $this->truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'ABC-1234',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->route = Route::create([
            'code' => 'RTE-001',
            'name' => 'Downtown Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $this->routeAssignment = RouteAssignment::create([
            'route_id' => $this->route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'assignment_date' => now(),
            'status' => 'scheduled',
        ]);

        $this->store = new RetailStore;
        $this->store->code = 'STR-001';
        $this->store->business_name = 'Test Store';
        $this->store->store_type = 'grocery';
        $this->store->contact_person = 'John';
        $this->store->phone = '0123456789';
        $this->store->email = 'store@test.com';
        $this->store->address = '123 Test St';
        $this->store->city = 'Test City';
        $this->store->state = 'Test State';
        $this->store->latitude = 30.0444;
        $this->store->longitude = 31.2357;
        $this->store->credit_limit = 50000;
        $this->store->current_balance = 0;
        $this->store->payment_terms = 'net_30';
        $this->store->status = 'active';
        $this->store->save();

        $this->product = new Product;
        $this->product->name = 'Test Product';
        $this->product->sku = 'SKU-TEST-001';
        $this->product->barcode = '1234567890123';
        $this->product->cost_price = 5.00;
        $this->product->selling_price = 10.00;
        $this->product->unit = 'piece';
        $this->product->is_active = true;
        $this->product->save();

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-001',
            'expiry_date' => now()->addYear(),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 10.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->salesOrder = SalesOrder::create([
            'order_number' => 'ORD-TEST-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->adminUser->id,
            'order_date' => now(),
            'status' => 'approved',
            'subtotal' => 100.00,
            'total' => 108.25,
            'balance_due' => 108.25,
        ]);

        $this->orderItem = OrderItem::create([
            'sales_order_id' => $this->salesOrder->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 10.00,
            'total_price' => 100.00,
            'status' => 'pending',
        ]);

        RouteStop::create([
            'route_id' => $this->route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_index_lists_deliveries(): void
    {
        Delivery::create([
            'delivery_number' => 'DEL-TEST-001',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'pending',
        ]);
        Delivery::create([
            'delivery_number' => 'DEL-TEST-002',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/deliveries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'delivery_number', 'status', 'driver', 'truck', 'route_assignment']],
                    'current_page', 'total',
                ],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Deliveries retrieved']);
    }

    public function test_store_creates_delivery(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/deliveries', [
                'route_assignment_id' => $this->routeAssignment->id,
                'delivery_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'delivery_number', 'status'], 'message'])
            ->assertJsonFragment(['message' => 'Delivery created']);

        $this->assertDatabaseHas('deliveries', [
            'route_assignment_id' => $this->routeAssignment->id,
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_route_assignment(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/deliveries', [
                'route_assignment_id' => 9999,
                'delivery_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['route_assignment_id']);
    }

    public function test_show_returns_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-SHOW',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/deliveries/{$delivery->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'delivery_number', 'status'], 'message'])
            ->assertJsonFragment(['message' => 'Delivery retrieved']);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/deliveries/9999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_pending_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-UPDATE',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/deliveries/{$delivery->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Delivery updated']);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_update_rejects_non_pending_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-NOUPDATE',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/deliveries/{$delivery->id}", [
                'notes' => 'Should fail',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot modify delivery that has started']);
    }

    public function test_destroy_deletes_pending_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-DELETE',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/deliveries/{$delivery->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Delivery deleted']);

        $this->assertDatabaseMissing('deliveries', ['id' => $delivery->id]);
    }

    public function test_destroy_rejects_non_pending_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-NODELETE',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/deliveries/{$delivery->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot delete delivery that has started']);
    }

    public function test_stops_returns_delivery_stops(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-STOPS',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'pending',
        ]);

        $routeStop = \App\Models\RouteStop::where('route_id', $this->route->id)->first();

        DeliveryStop::create([
            'delivery_id' => $delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/deliveries/{$delivery->id}/stops");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'delivery_id', 'retail_store', 'stop_order']], 'message'])
            ->assertJsonFragment(['message' => 'Delivery stops retrieved']);
    }

    public function test_complete_completes_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-COMPLETE',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/deliveries/{$delivery->id}/complete", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 10,
                    ],
                ],
                'notes' => 'Completed successfully',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['delivery', 'settlement'], 'message'])
            ->assertJsonFragment(['message' => 'Delivery completed successfully']);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => 'completed',
            'total_sales' => 100.00,
        ]);
    }

    public function test_complete_rejects_already_completed_delivery(): void
    {
        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TEST-ALREADY',
            'route_assignment_id' => $this->routeAssignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/deliveries/{$delivery->id}/complete", [
                'items' => [
                    [
                        'order_item_id' => $this->orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Delivery is already completed']);
    }
}
