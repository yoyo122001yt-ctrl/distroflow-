<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\OrderItem;
use App\Models\PickList;
use App\Models\PickListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseZone;
use App\Models\WarehouseStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private WarehouseZone $zone;
    private WarehouseLocation $location;
    private Product $product;
    private ProductCategory $category;
    private Supplier $supplier;
    private \App\Models\RetailStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'is_active' => true,
        ]);

        $this->store = \App\Models\RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Test Store',
            'store_type' => 'grocery',
            'contact_person' => 'Omar',
            'phone' => '+201223344556',
            'credit_limit' => 50000,
            'current_balance' => 0,
            'payment_terms' => 'net_30',
            'status' => 'active',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->zone = WarehouseZone::create([
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Zone A',
            'code' => 'ZA',
            'type' => 'storage',
            'is_active' => true,
        ]);

        $this->location = WarehouseLocation::create([
            'warehouse_zone_id' => $this->zone->id,
            'rack' => 'R1',
            'shelf' => 'S1',
            'bin' => 'B1',
            'barcode' => 'LOC-001',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Test Supplier',
            'contact_person' => 'John',
            'phone' => '0123456789',
            'email' => 'supplier@test.com',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'sku' => 'SKU-TEST-001',
            'barcode' => '1234567890123',
            'cost_price' => 5.00,
            'selling_price' => 10.00,
            'unit' => 'piece',
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------
    // POST /api/warehouse/receive
    // ---------------------------------------------------------------

    public function test_receive_creates_batches_and_updates_po_status_to_received(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 0);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 100,
                        'batch_number' => 'BATCH-001',
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                        'cost_price' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Stock received successfully']);

        $this->assertDatabaseHas('batches', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-001',
            'quantity' => 100,
            'available_quantity' => 100,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 100,
        ]);
    }

    public function test_receive_sets_po_status_to_partial_when_not_all_received(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 0);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 50,
                        'batch_number' => 'BATCH-PARTIAL',
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'partial',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $poItem->id,
            'quantity_received' => 50,
        ]);
    }

    public function test_receive_rejects_quantity_exceeding_ordered(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 50, 0);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 60,
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Quantity received (60) exceeds ordered quantity (50.00) for item #'.$poItem->id]);
    }

    public function test_receive_validates_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id', 'items']);
    }

    public function test_receive_validates_nonexistent_purchase_order(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => 9999,
                'items' => [
                    [
                        'purchase_order_item_id' => 1,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_receive_validates_nonexistent_purchase_order_item(): void
    {
        $po = $this->createPurchaseOrder('pending');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => 9999,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.purchase_order_item_id']);
    }

    public function test_receive_generates_batch_number_when_not_provided(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 10, 0);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/receive', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $batch = Batch::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('BATCH-', $batch->batch_number);
    }

    // ---------------------------------------------------------------
    // GET /api/warehouse/inventory
    // ---------------------------------------------------------------

    public function test_inventory_lists_available_batches(): void
    {
        $this->createBatch('BATCH-A', $this->product->id, $this->warehouse->id, 50);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Inventory retrieved'])
            ->assertJsonPath('data.data', fn ($data) => count($data) >= 1);
    }

    public function test_inventory_filters_by_warehouse_id(): void
    {
        $warehouse2 = Warehouse::create(['name' => 'Warehouse 2', 'code' => 'WH02', 'is_active' => true]);
        $this->createBatch('BATCH-WH1', $this->product->id, $this->warehouse->id, 30);
        $this->createBatch('BATCH-WH2', $this->product->id, $warehouse2->id, 40);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory?warehouse_id='.$this->warehouse->id);

        $response->assertStatus(200);

        $batches = $response->json('data.data');
        foreach ($batches as $batch) {
            $this->assertEquals($this->warehouse->id, $batch['warehouse_id']);
        }
    }

    public function test_inventory_filters_by_product_id(): void
    {
        $product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product B',
            'sku' => 'SKU-002',
            'cost_price' => 3.00,
            'selling_price' => 7.00,
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $this->createBatch('BATCH-P1', $this->product->id, $this->warehouse->id, 25);
        $this->createBatch('BATCH-P2', $product2->id, $this->warehouse->id, 35);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory?product_id='.$this->product->id);

        $response->assertStatus(200);

        $batches = $response->json('data.data');
        foreach ($batches as $batch) {
            $this->assertEquals($this->product->id, $batch['product_id']);
        }
    }

    public function test_inventory_searches_by_product_name(): void
    {
        $this->createBatch('BATCH-SRCH', $this->product->id, $this->warehouse->id, 10);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory?search=Test+Product');

        $response->assertStatus(200);

        $batches = $response->json('data.data');
        $this->assertNotEmpty($batches);
    }

    public function test_inventory_filters_by_expiry_before(): void
    {
        $nearExpiry = $this->createBatch('BATCH-NEAR', $this->product->id, $this->warehouse->id, 10, now()->addDays(5));
        $farExpiry = $this->createBatch('BATCH-FAR', $this->product->id, $this->warehouse->id, 10, now()->addDays(60));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory?expiry_before='.now()->addDays(30)->format('Y-m-d'));

        $response->assertStatus(200);

        $batches = $response->json('data.data');
        foreach ($batches as $batch) {
            $this->assertNotNull($batch['expiry_date']);
        }
    }

    public function test_inventory_excludes_depleted_batches(): void
    {
        $this->createBatch('BATCH-AVAIL', $this->product->id, $this->warehouse->id, 10);
        $this->createBatch('BATCH-DEPL', $this->product->id, $this->warehouse->id, 0, null, 'depleted');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/inventory');

        $response->assertStatus(200);

        $batches = $response->json('data.data');
        foreach ($batches as $batch) {
            $this->assertGreaterThan(0, (float) $batch['available_quantity']);
        }
    }

    // ---------------------------------------------------------------
    // POST /api/warehouse/pick
    // ---------------------------------------------------------------

    public function test_pick_creates_pick_list_with_fefo_items(): void
    {
        $batch = $this->createBatch('BATCH-PICK', $this->product->id, $this->warehouse->id, 50);
        $route = $this->createRoute();
        $salesOrder = $this->createSalesOrder($route->id);
        $orderItem = $this->createOrderItem($salesOrder->id, $this->product->id, 10);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/pick', [
                'warehouse_id' => $this->warehouse->id,
                'route_id' => $route->id,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Pick list generated']);

        $this->assertDatabaseHas('pick_lists', [
            'warehouse_id' => $this->warehouse->id,
            'route_id' => $route->id,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('pick_list_items', [
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'quantity_picked' => 10,
            'status' => 'picked',
        ]);
    }

    public function test_pick_uses_fefo_order_earliest_expiry_first(): void
    {
        $batchEarly = $this->createBatch('BATCH-EARLY', $this->product->id, $this->warehouse->id, 30, now()->addDays(10));
        $batchLate = $this->createBatch('BATCH-LATE', $this->product->id, $this->warehouse->id, 30, now()->addDays(60));

        $route = $this->createRoute();
        $salesOrder = $this->createSalesOrder($route->id);
        $orderItem = $this->createOrderItem($salesOrder->id, $this->product->id, 20);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/pick', [
                'warehouse_id' => $this->warehouse->id,
                'route_id' => $route->id,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        $pickListItem = PickListItem::where('batch_id', $batchEarly->id)->first();
        $this->assertNotNull($pickListItem);
        $this->assertEquals(20, (float) $pickListItem->quantity);
    }

    public function test_pick_validates_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/pick', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id', 'route_id', 'items']);
    }

    public function test_pick_validates_nonexistent_warehouse(): void
    {
        $route = $this->createRoute();
        $salesOrder = $this->createSalesOrder($route->id);
        $orderItem = $this->createOrderItem($salesOrder->id, $this->product->id, 5);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/pick', [
                'warehouse_id' => 9999,
                'route_id' => $route->id,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id']);
    }

    public function test_pick_fails_when_insufficient_stock(): void
    {
        $this->createBatch('BATCH-LOW', $this->product->id, $this->warehouse->id, 2);
        $route = $this->createRoute();
        $salesOrder = $this->createSalesOrder($route->id);
        $orderItem = $this->createOrderItem($salesOrder->id, $this->product->id, 10);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/pick', [
                'warehouse_id' => $this->warehouse->id,
                'route_id' => $route->id,
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Insufficient stock for product Test Product (SKU: SKU-TEST-001). Needed: 10, Available: 2']);
    }

    // ---------------------------------------------------------------
    // POST /api/warehouse/load
    // ---------------------------------------------------------------

    public function test_load_creates_load_list_and_consumes_batches(): void
    {
        $batch = $this->createBatch('BATCH-LOAD', $this->product->id, $this->warehouse->id, 50);
        $route = $this->createRoute();
        $truck = $this->createTruck();
        $salesOrder = $this->createSalesOrder($route->id);
        $orderItem = $this->createOrderItem($salesOrder->id, $this->product->id, 10);

        $pickList = PickList::create([
            'pick_list_number' => 'PICK-TEST-001',
            'warehouse_id' => $this->warehouse->id,
            'route_id' => $route->id,
            'status' => 'open',
        ]);

        $pickListItem = PickListItem::create([
            'pick_list_id' => $pickList->id,
            'sales_order_id' => $salesOrder->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'batch_id' => $batch->id,
            'quantity' => 10,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/load', [
                'route_id' => $route->id,
                'truck_id' => $truck->id,
                'warehouse_id' => $this->warehouse->id,
                'pick_list_id' => $pickList->id,
                'items' => [
                    [
                        'pick_list_item_id' => $pickListItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $batch->id,
                        'quantity' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Truck loaded successfully']);

        $this->assertDatabaseHas('load_lists', [
            'route_id' => $route->id,
            'truck_id' => $truck->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'loaded',
        ]);

        $this->assertDatabaseHas('load_list_items', [
            'batch_id' => $batch->id,
            'quantity' => 10,
            'status' => 'loaded',
        ]);

        $batch->refresh();
        $this->assertEquals(40, (float) $batch->available_quantity);

        $this->assertDatabaseHas('pick_lists', [
            'id' => $pickList->id,
            'status' => 'loaded',
        ]);
    }

    public function test_load_validates_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/load', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['route_id', 'truck_id', 'warehouse_id', 'pick_list_id', 'items']);
    }

    public function test_load_validates_nonexistent_truck(): void
    {
        $route = $this->createRoute();
        $pickList = PickList::create([
            'pick_list_number' => 'PICK-TEST-002',
            'warehouse_id' => $this->warehouse->id,
            'route_id' => $route->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/warehouse/load', [
                'route_id' => $route->id,
                'truck_id' => 9999,
                'warehouse_id' => $this->warehouse->id,
                'pick_list_id' => $pickList->id,
                'items' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['truck_id']);
    }

    // ---------------------------------------------------------------
    // POST /api/inventory/batches/{id}/adjust
    // ---------------------------------------------------------------

    public function test_adjust_adds_quantity_and_creates_stock_movement(): void
    {
        $batch = $this->createBatch('BATCH-ADJ', $this->product->id, $this->warehouse->id, 50);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", [
                'quantity' => 10,
                'reason' => 'Found extra stock',
                'type' => 'add',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Stock adjusted successfully']);

        $batch->refresh();
        $this->assertEquals(60, (float) $batch->available_quantity);
        $this->assertEquals('available', $batch->status);

        $this->assertDatabaseHas('warehouse_stock_movements', [
            'batch_id' => $batch->id,
            'movement_type' => 'addition',
            'quantity' => 10,
            'quantity_before' => 50,
            'quantity_after' => 60,
        ]);
    }

    public function test_adjust_removes_quantity(): void
    {
        $batch = $this->createBatch('BATCH-RM', $this->product->id, $this->warehouse->id, 50);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", [
                'quantity' => 15,
                'reason' => 'Damaged goods',
                'type' => 'remove',
            ]);

        $response->assertStatus(200);

        $batch->refresh();
        $this->assertEquals(35, (float) $batch->available_quantity);

        $this->assertDatabaseHas('warehouse_stock_movements', [
            'batch_id' => $batch->id,
            'movement_type' => 'removal',
            'quantity' => 15,
        ]);
    }

    public function test_adjust_handles_damage_type(): void
    {
        $batch = $this->createBatch('BATCH-DMG', $this->product->id, $this->warehouse->id, 50);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", [
                'quantity' => 5,
                'reason' => 'Spoiled',
                'type' => 'damage',
            ]);

        $response->assertStatus(200);

        $batch->refresh();
        $this->assertEquals(45, (float) $batch->available_quantity);

        $this->assertDatabaseHas('warehouse_stock_movements', [
            'batch_id' => $batch->id,
            'movement_type' => 'damage',
            'quantity' => 5,
        ]);
    }

    public function test_adjust_caps_at_zero_when_removing_more_than_available(): void
    {
        $batch = $this->createBatch('BATCH-CAP', $this->product->id, $this->warehouse->id, 5);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", [
                'quantity' => 20,
                'reason' => 'Over removal',
                'type' => 'remove',
            ]);

        $response->assertStatus(200);

        $batch->refresh();
        $this->assertEquals(0, (float) $batch->available_quantity);
        $this->assertEquals('depleted', $batch->status);
    }

    public function test_adjust_validates_missing_required_fields(): void
    {
        $batch = $this->createBatch('BATCH-VAL', $this->product->id, $this->warehouse->id, 10);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity', 'reason', 'type']);
    }

    public function test_adjust_validates_invalid_type(): void
    {
        $batch = $this->createBatch('BATCH-TYP', $this->product->id, $this->warehouse->id, 10);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/inventory/batches/{$batch->id}/adjust", [
                'quantity' => 5,
                'reason' => 'Test',
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_adjust_returns_404_for_nonexistent_batch(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/inventory/batches/9999/adjust', [
                'quantity' => 5,
                'reason' => 'Test',
                'type' => 'add',
            ]);

        $response->assertStatus(500);
    }

    // ---------------------------------------------------------------
    // GET /api/warehouse/expiring
    // ---------------------------------------------------------------

    public function test_expiring_returns_batches_within_default_30_days(): void
    {
        $nearExpiry = $this->createBatch('BATCH-EXP-NEAR', $this->product->id, $this->warehouse->id, 20, now()->addDays(10));
        $farExpiry = $this->createBatch('BATCH-EXP-FAR', $this->product->id, $this->warehouse->id, 20, now()->addDays(60));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/expiring');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Expiring products retrieved'])
            ->assertJsonPath('meta.days', 30);

        $data = $response->json('data');
        $batchIds = array_column($data, 'id');
        $this->assertContains($nearExpiry->id, $batchIds);
        $this->assertNotContains($farExpiry->id, $batchIds);
    }

    public function test_expiring_respects_custom_days_parameter(): void
    {
        $batch5 = $this->createBatch('BATCH-5D', $this->product->id, $this->warehouse->id, 10, now()->addDays(5));
        $batch20 = $this->createBatch('BATCH-20D', $this->product->id, $this->warehouse->id, 10, now()->addDays(20));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/expiring?days=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.days', 10);

        $data = $response->json('data');
        $batchIds = array_column($data, 'id');
        $this->assertContains($batch5->id, $batchIds);
        $this->assertNotContains($batch20->id, $batchIds);
    }

    public function test_expiring_excludes_already_depleted_batches(): void
    {
        $this->createBatch('BATCH-EXP-DEPL', $this->product->id, $this->warehouse->id, 0, now()->addDays(5), 'depleted');
        $this->createBatch('BATCH-EXP-AVAIL', $this->product->id, $this->warehouse->id, 10, now()->addDays(5));

        $response = $this->actingAs($this->admin)
            ->getJson('/api/warehouse/expiring');

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertGreaterThan(0, (float) $item['available_quantity']);
        }
    }

    // ---------------------------------------------------------------
    // Unauthenticated access
    // ---------------------------------------------------------------

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/warehouse/inventory')->assertStatus(401);
        $this->postJson('/api/warehouse/receive', [])->assertStatus(401);
        $this->postJson('/api/warehouse/pick', [])->assertStatus(401);
        $this->postJson('/api/warehouse/load', [])->assertStatus(401);
        $this->getJson('/api/warehouse/expiring')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------

    private function createPurchaseOrder(string $status = 'pending'): PurchaseOrder
    {
        return PurchaseOrder::create([
            'order_number' => 'PO-' . strtoupper(uniqid()),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => $status,
            'subtotal' => 100.00,
            'tax' => 0,
            'total' => 100.00,
        ]);
    }

    private function createPurchaseOrderItem(int $poId, int $productId, float $ordered, float $received): PurchaseOrderItem
    {
        return PurchaseOrderItem::create([
            'purchase_order_id' => $poId,
            'product_id' => $productId,
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
            'unit_cost' => 5.00,
            'total_cost' => $ordered * 5.00,
        ]);
    }

    private function createBatch(
        string $batchNumber,
        int $productId,
        int $warehouseId,
        float $quantity,
        ?string $expiryDate = null,
        string $status = 'available'
    ): Batch {
        return Batch::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate ?? now()->addDays(60)->format('Y-m-d'),
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'cost_price' => 5.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now(),
            'status' => $status,
        ]);
    }

    private function createRoute(): Route
    {
        return Route::create([
            'code' => 'RTE-' . strtoupper(uniqid()),
            'name' => 'Test Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);
    }

    private function createTruck(): \App\Models\Truck
    {
        return \App\Models\Truck::create([
            'code' => 'TRK-' . strtoupper(uniqid()),
            'plate_number' => 'TEST-1234',
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    private function createSalesOrder(int $routeId): SalesOrder
    {
        return SalesOrder::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'route_id' => $routeId,
            'order_date' => now(),
            'status' => 'approved',
            'subtotal' => 100.00,
            'total' => 100.00,
            'balance_due' => 100.00,
        ]);
    }

    private function createOrderItem(int $salesOrderId, int $productId, float $quantity): OrderItem
    {
        return OrderItem::create([
            'sales_order_id' => $salesOrderId,
            'product_id' => $productId,
            'quantity_ordered' => $quantity,
            'unit_price' => 10.00,
            'total_price' => $quantity * 10.00,
            'status' => 'pending',
        ]);
    }
}
