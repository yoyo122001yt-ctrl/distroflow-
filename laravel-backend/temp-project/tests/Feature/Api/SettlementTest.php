<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\DeliveryReturn;
use App\Models\DeliveryStop;
use App\Models\DriverProfile;
use App\Models\DriverSettlement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RetailStore;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\SalesOrder;
use App\Models\Truck;
use App\Models\TruckInventory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver;
    private Warehouse $warehouse;
    private Truck $truck;
    private Route $route;
    private RouteAssignment $assignment;
    private RetailStore $store;
    private Product $product;
    private Batch $batch;
    private SalesOrder $salesOrder;
    private OrderItem $orderItem;
    private Delivery $delivery;
    private DeliveryStop $deliveryStop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->driver = User::create([
            'name' => 'John Driver',
            'email' => 'driver@test.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
            'is_active' => true,
        ]);

        DriverProfile::create([
            'user_id' => $this->driver->id,
            'license_number' => 'LIC-12345',
            'status' => 'available',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'is_active' => true,
        ]);

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
            'cost_price' => 5.00,
            'selling_price' => 10.00,
            'unit' => 'bottle',
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-SODA-01',
            'expiry_date' => now()->addDays(90),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->salesOrder = SalesOrder::create([
            'order_number' => 'ORD-SETTLE-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
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
            'total_price' => 800.00,
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

    // ---------------------------------------------------------------
    // GET /api/settlements
    // ---------------------------------------------------------------

    public function test_index_lists_settlements(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlements retrieved'])
            ->assertJsonPath('data.data', fn ($data) => count($data) >= 1);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');
        $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements?status=pending');

        $response->assertStatus(200);

        $settlements = $response->json('data.data');
        foreach ($settlements as $settlement) {
            $this->assertEquals('pending', $settlement['status']);
        }
    }

    public function test_index_filters_by_driver_id(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');

        $driver2 = User::create([
            'name' => 'Driver Two',
            'email' => 'driver2@test.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
        ]);

        $delivery2 = Delivery::create([
            'delivery_number' => 'DEL-DRV2',
            'route_assignment_id' => $this->assignment->id,
            'driver_id' => $driver2->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => today(),
            'status' => 'completed',
        ]);

        DriverSettlement::create([
            'driver_id' => $driver2->id,
            'delivery_id' => $delivery2->id,
            'route_assignment_id' => $this->assignment->id,
            'settlement_date' => today(),
            'status' => 'pending',
            'total_sales' => 200.00,
            'expected_cash' => 200.00,
            'actual_cash' => 200.00,
            'cash_variance' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/settlements?driver_id={$this->driver->id}");

        $response->assertStatus(200);

        $settlements = $response->json('data.data');
        foreach ($settlements as $settlement) {
            $this->assertEquals($this->driver->id, $settlement['driver_id']);
        }
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements?date_from='.now()->subDay()->format('Y-m-d').'&date_to='.now()->addDay()->format('Y-m-d'));

        $response->assertStatus(200);

        $settlements = $response->json('data.data');
        $this->assertNotEmpty($settlements);
    }

    public function test_index_filters_flagged_only(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');
        $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements?flagged_only=1');

        $response->assertStatus(200);

        $settlements = $response->json('data.data');
        foreach ($settlements as $settlement) {
            $this->assertEquals('flagged', $settlement['status']);
        }
    }

    // ---------------------------------------------------------------
    // POST /api/settlements
    // ---------------------------------------------------------------

    public function test_store_calculates_settlement_for_delivery(): void
    {
        $this->createCompletedDeliveryData();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settlements', [
                'delivery_id' => $this->delivery->id,
                'actual_cash' => 750.00,
                'inventory' => [
                    [
                        'batch_id' => $this->batch->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Settlement created']);

        $this->assertDatabaseHas('driver_settlements', [
            'driver_id' => $this->driver->id,
            'delivery_id' => $this->delivery->id,
            'status' => 'flagged',
        ]);
    }

    public function test_store_sets_actual_cash_and_variance(): void
    {
        $this->createCompletedDeliveryData();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settlements', [
                'delivery_id' => $this->delivery->id,
                'actual_cash' => 700.00,
                'inventory' => [
                    [
                        'batch_id' => $this->batch->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $settlement = DriverSettlement::where('delivery_id', $this->delivery->id)->first();
        $this->assertNotNull($settlement);
        $this->assertEquals(700.00, (float) $settlement->actual_cash);
        $this->assertEquals(100.00, (float) $settlement->cash_variance);
    }

    public function test_store_validates_missing_delivery_id(): void
    {
        $this->createCompletedDeliveryData();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settlements', [
                'actual_cash' => 750.00,
                'inventory' => [
                    [
                        'batch_id' => $this->batch->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_id']);
    }

    public function test_store_validates_nonexistent_delivery(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/settlements', [
                'delivery_id' => 9999,
                'actual_cash' => 750.00,
                'inventory' => [
                    [
                        'batch_id' => $this->batch->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['delivery_id']);
    }

    public function test_store_marks_flagged_when_cash_variance_exceeds_threshold(): void
    {
        $this->createCompletedDeliveryData();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/settlements', [
                'delivery_id' => $this->delivery->id,
                'actual_cash' => 700.00,
                'inventory' => [
                    [
                        'batch_id' => $this->batch->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $settlement = DriverSettlement::where('delivery_id', $this->delivery->id)->first();
        $this->assertEquals('flagged', $settlement->status);
    }

    // ---------------------------------------------------------------
    // GET /api/settlements/{id}
    // ---------------------------------------------------------------

    public function test_show_returns_settlement_with_relationships(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/settlements/{$settlement->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement retrieved'])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'driver',
                    'route_assignment',
                    'delivery',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements/9999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // PUT /api/settlements/{id}
    // ---------------------------------------------------------------

    public function test_update_modifies_pending_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/settlements/{$settlement->id}", [
                'actual_cash' => 800.00,
                'notes' => 'Updated cash amount',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement updated']);

        $settlement->refresh();
        $this->assertEquals(800.00, (float) $settlement->actual_cash);
        $this->assertEquals('Updated cash amount', $settlement->notes);
    }

    public function test_update_recalculates_cash_variance(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/settlements/{$settlement->id}", [
                'actual_cash' => 600.00,
            ]);

        $response->assertStatus(200);

        $settlement->refresh();
        $expectedVariance = $settlement->expected_cash - 600.00;
        $this->assertEquals($expectedVariance, (float) $settlement->cash_variance);
    }

    public function test_update_blocks_approved_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('approved');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/settlements/{$settlement->id}", [
                'actual_cash' => 999.00,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot modify an approved settlement']);
    }

    public function test_update_allows_flagged_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/settlements/{$settlement->id}", [
                'notes' => 'Investigating variance',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement updated']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/settlements/{id}
    // ---------------------------------------------------------------

    public function test_destroy_deletes_pending_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/settlements/{$settlement->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement deleted']);

        $this->assertDatabaseMissing('driver_settlements', ['id' => $settlement->id]);
    }

    public function test_destroy_deletes_flagged_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/settlements/{$settlement->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement deleted']);

        $this->assertDatabaseMissing('driver_settlements', ['id' => $settlement->id]);
    }

    public function test_destroy_blocks_approved_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('approved');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/settlements/{$settlement->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot delete an approved settlement']);
    }

    // ---------------------------------------------------------------
    // POST /api/settlements/{id}/approve
    // ---------------------------------------------------------------

    public function test_approve_changes_status_to_approved(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/approve");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement approved']);

        $settlement->refresh();
        $this->assertEquals('approved', $settlement->status);
        $this->assertEquals($this->admin->id, $settlement->approved_by);
        $this->assertNotNull($settlement->approved_at);
    }

    public function test_approve_flags_settlement(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/approve");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement approved']);

        $settlement->refresh();
        $this->assertEquals('approved', $settlement->status);
    }

    public function test_approve_blocks_already_approved(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('approved');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/approve");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Settlement is already approved']);
    }

    public function test_approve_stores_approver_and_timestamp(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/approve");

        $settlement->refresh();
        $this->assertEquals($this->admin->id, $settlement->approved_by);
        $this->assertNotNull($settlement->approved_at);
    }

    // ---------------------------------------------------------------
    // POST /api/settlements/{id}/reject
    // ---------------------------------------------------------------

    public function test_reject_changes_status_to_rejected_with_reason(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/reject", [
                'reason' => 'Cash shortage not explained',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Settlement rejected']);

        $settlement->refresh();
        $this->assertEquals('rejected', $settlement->status);
        $this->assertEquals('Cash shortage not explained', $settlement->notes);
    }

    public function test_reject_uses_default_reason_when_not_provided(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('pending');

        $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/reject");

        $settlement->refresh();
        $this->assertEquals('rejected', $settlement->status);
        $this->assertEquals('Rejected', $settlement->notes);
    }

    public function test_reject_blocks_already_rejected(): void
    {
        $this->createCompletedDeliveryData();
        $settlement = $this->createSettlement('rejected');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/settlements/{$settlement->id}/reject", [
                'reason' => 'Already rejected',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Settlement is already rejected']);
    }

    // ---------------------------------------------------------------
    // GET /api/settlements/unreconciled
    // ---------------------------------------------------------------

    public function test_unreconciled_returns_pending_and_flagged_settlements(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');
        $this->createSettlement('flagged');

        $delivery2 = Delivery::create([
            'delivery_number' => 'DEL-APPROVED',
            'route_assignment_id' => $this->assignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now()->subDays(2),
            'status' => 'completed',
        ]);

        DriverSettlement::create([
            'driver_id' => $this->driver->id,
            'delivery_id' => $delivery2->id,
            'route_assignment_id' => $this->assignment->id,
            'settlement_date' => now()->subDays(2),
            'status' => 'approved',
            'total_sales' => 100.00,
            'expected_cash' => 100.00,
            'actual_cash' => 100.00,
            'cash_variance' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements/unreconciled');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Unreconciled settlements retrieved'])
            ->assertJsonStructure([
                'data' => [
                    ['id', 'status', 'driver'],
                ],
                'meta' => ['total_count', 'flagged_count', 'pending_count'],
            ]);

        $data = $response->json('data');
        foreach ($data as $settlement) {
            $this->assertContains($settlement['status'], ['pending', 'flagged']);
        }
    }

    public function test_unreconciled_filters_by_driver_id(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');

        $driver2 = User::create([
            'name' => 'Driver Two',
            'email' => 'driver2@test.com',
            'password' => bcrypt('password'),
            'role' => 'driver',
        ]);

        $delivery2 = Delivery::create([
            'delivery_number' => 'DEL-DRV2',
            'route_assignment_id' => $this->assignment->id,
            'driver_id' => $driver2->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => today(),
            'status' => 'completed',
        ]);

        DriverSettlement::create([
            'driver_id' => $driver2->id,
            'delivery_id' => $delivery2->id,
            'route_assignment_id' => $this->assignment->id,
            'settlement_date' => today(),
            'status' => 'pending',
            'total_sales' => 200.00,
            'expected_cash' => 200.00,
            'actual_cash' => 200.00,
            'cash_variance' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/settlements/unreconciled?driver_id={$this->driver->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $settlement) {
            $this->assertEquals($this->driver->id, $settlement['driver_id']);
        }
    }

    public function test_unreconciled_includes_meta_counts(): void
    {
        $this->createCompletedDeliveryData();
        $this->createSettlement('pending');
        $this->createSettlement('flagged');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/settlements/unreconciled');

        $response->assertStatus(200);

        $meta = $response->json('meta');
        $this->assertArrayHasKey('total_count', $meta);
        $this->assertArrayHasKey('flagged_count', $meta);
        $this->assertArrayHasKey('pending_count', $meta);
        $this->assertGreaterThanOrEqual(2, $meta['total_count']);
    }

    // ---------------------------------------------------------------
    // Unauthenticated access
    // ---------------------------------------------------------------

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/settlements')->assertStatus(401);
        $this->postJson('/api/settlements', [])->assertStatus(401);
        $this->getJson('/api/settlements/1')->assertStatus(401);
        $this->putJson('/api/settlements/1', [])->assertStatus(401);
        $this->deleteJson('/api/settlements/1')->assertStatus(401);
        $this->postJson('/api/settlements/1/approve')->assertStatus(401);
        $this->postJson('/api/settlements/1/reject')->assertStatus(401);
        $this->getJson('/api/settlements/unreconciled')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------

    private function createCompletedDeliveryData(): void
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
            'amount' => 750.00,
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
    }

    private function createSettlement(string $status): DriverSettlement
    {
        return DriverSettlement::create([
            'driver_id' => $this->driver->id,
            'delivery_id' => $this->delivery->id,
            'route_assignment_id' => $this->assignment->id,
            'settlement_date' => today(),
            'status' => $status,
            'total_sales' => 800.00,
            'total_returns' => 0,
            'expected_cash' => 800.00,
            'actual_cash' => $status === 'flagged' ? 700.00 : 800.00,
            'cash_variance' => $status === 'flagged' ? 100.00 : 0,
            'starting_inventory_value' => 0,
            'loaded_value' => 1000.00,
            'sales_value' => 800.00,
            'returns_value' => 0,
            'expected_end_inventory_value' => 200.00,
            'actual_end_inventory_value' => $status === 'flagged' ? 100.00 : 200.00,
            'inventory_variance' => $status === 'flagged' ? 100.00 : 0,
        ]);
    }
}
