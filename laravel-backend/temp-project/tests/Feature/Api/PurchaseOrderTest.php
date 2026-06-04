<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Supplier $supplier;
    private Product $product;
    private ProductCategory $category;

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
    // GET /api/purchase-orders
    // ---------------------------------------------------------------

    public function test_index_lists_purchase_orders(): void
    {
        $this->createPurchaseOrder('pending');
        $this->createPurchaseOrder('partial');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase orders retrieved'])
            ->assertJsonPath('data.data', fn ($data) => count($data) >= 2);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createPurchaseOrder('pending');
        $this->createPurchaseOrder('received');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders?status=pending');

        $response->assertStatus(200);

        $orders = $response->json('data.data');
        foreach ($orders as $order) {
            $this->assertEquals('pending', $order['status']);
        }
    }

    public function test_index_filters_by_supplier_id(): void
    {
        $supplier2 = Supplier::create([
            'code' => 'SUP-002',
            'business_name' => 'Supplier 2',
            'status' => 'active',
        ]);

        $this->createPurchaseOrder('pending');
        PurchaseOrder::create([
            'order_number' => 'PO-SUP2',
            'supplier_id' => $supplier2->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders?supplier_id='.$this->supplier->id);

        $response->assertStatus(200);

        $orders = $response->json('data.data');
        foreach ($orders as $order) {
            $this->assertEquals($this->supplier->id, $order['supplier_id']);
        }
    }

    public function test_index_filters_by_date_range(): void
    {
        PurchaseOrder::create([
            'order_number' => 'PO-OLD',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now()->subDays(10),
            'status' => 'pending',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);

        PurchaseOrder::create([
            'order_number' => 'PO-NEW',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders?date_from='.now()->subDay()->format('Y-m-d'));

        $response->assertStatus(200);

        $orders = $response->json('data.data');
        foreach ($orders as $order) {
            $this->assertGreaterThanOrEqual(
                now()->subDay()->format('Y-m-d'),
                $order['order_date']
            );
        }
    }

    public function test_index_searches_by_order_number(): void
    {
        PurchaseOrder::create([
            'order_number' => 'PO-SEARCHABLE-XYZ',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders?search=SEARCHABLE');

        $response->assertStatus(200);

        $orders = $response->json('data.data');
        $this->assertNotEmpty($orders);
    }

    // ---------------------------------------------------------------
    // POST /api/purchase-orders
    // ---------------------------------------------------------------

    public function test_store_creates_purchase_order_with_items(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 500.00,
                'total' => 500.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 100,
                        'unit_cost' => 5.00,
                    ],
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 50,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Purchase order created']);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
            'total' => 500.00,
        ]);

        $this->assertDatabaseCount('purchase_order_items', 2);
    }

    public function test_store_generates_unique_order_number(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 100.00,
                'total' => 100.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 100.00,
                'total' => 100.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $orders = PurchaseOrder::all();
        $orderNumbers = $orders->pluck('order_number')->toArray();
        $this->assertCount(2, $orderNumbers);
        $this->assertCount(2, array_unique($orderNumbers));
    }

    public function test_store_validates_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'supplier_id',
                'warehouse_id',
                'order_date',
                'items',
                'subtotal',
                'total',
            ]);
    }

    public function test_store_validates_nonexistent_supplier(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => 9999,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 100.00,
                'total' => 100.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_store_validates_nonexistent_product(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 100.00,
                'total' => 100.00,
                'items' => [
                    [
                        'product_id' => 9999,
                        'quantity_ordered' => 10,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_store_sets_status_to_pending(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'subtotal' => 100.00,
                'total' => 100.00,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_cost' => 5.00,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', ['status' => 'pending']);
    }

    // ---------------------------------------------------------------
    // GET /api/purchase-orders/{id}
    // ---------------------------------------------------------------

    public function test_show_returns_purchase_order_with_relationships(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 0);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase order retrieved'])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'supplier',
                    'warehouse',
                    'items',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/purchase-orders/9999');

        $response->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // PUT /api/purchase-orders/{id}
    // ---------------------------------------------------------------

    public function test_update_modifies_pending_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('pending');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/purchase-orders/{$po->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase order updated']);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_update_modifies_partial_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('partial');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/purchase-orders/{$po->id}", [
                'notes' => 'Partial update',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase order updated']);
    }

    public function test_update_blocks_received_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('received');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/purchase-orders/{$po->id}", [
                'notes' => 'Should not work',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot modify purchase order in current status']);
    }

    public function test_update_validates_invalid_supplier(): void
    {
        $po = $this->createPurchaseOrder('pending');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/purchase-orders/{$po->id}", [
                'supplier_id' => 9999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/purchase-orders/{id}
    // ---------------------------------------------------------------

    public function test_destroy_deletes_pending_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $itemId = $this->createPurchaseOrderItem($po->id, $this->product->id, 10, 0)->id;

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase order deleted']);

        $this->assertDatabaseMissing('purchase_orders', ['id' => $po->id]);
        $this->assertDatabaseMissing('purchase_order_items', ['id' => $itemId]);
    }

    public function test_destroy_blocks_partial_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('partial');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot delete purchase order that has been processed']);
    }

    public function test_destroy_blocks_received_purchase_order(): void
    {
        $po = $this->createPurchaseOrder('received');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/purchase-orders/{$po->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot delete purchase order that has been processed']);
    }

    // ---------------------------------------------------------------
    // POST /api/purchase-orders/{id}/receive
    // ---------------------------------------------------------------

    public function test_receive_creates_batches_and_updates_po_status(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 0);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 100,
                        'batch_number' => 'RECV-BATCH-001',
                        'expiry_date' => now()->addDays(90)->format('Y-m-d'),
                        'cost_price' => 5.00,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Purchase order received successfully']);

        $this->assertDatabaseHas('batches', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'RECV-BATCH-001',
            'quantity' => 100,
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

    public function test_receive_partial_sets_status_to_partial(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 0);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 40,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'partial',
        ]);
    }

    public function test_receive_multiple_items_full_receive(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $product2 = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Product B',
            'sku' => 'SKU-002',
            'cost_price' => 3.00,
            'selling_price' => 7.00,
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $poItem1 = $this->createPurchaseOrderItem($po->id, $this->product->id, 50, 0);
        $poItem2 = $this->createPurchaseOrderItem($po->id, $product2->id, 30, 0);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem1->id,
                        'quantity' => 50,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                    [
                        'purchase_order_item_id' => $poItem2->id,
                        'quantity' => 30,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'received',
        ]);

        $this->assertDatabaseCount('batches', 2);
    }

    public function test_receive_rejects_quantity_exceeding_ordered(): void
    {
        $po = $this->createPurchaseOrder('pending');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 50, 0);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 60,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
            ]);

        $response->assertStatus(400);

        $message = $response->json('message');
        $this->assertStringContainsString('Quantity received (60) exceeds ordered quantity', $message);
    }

    public function test_receive_rejects_already_fully_received_po(): void
    {
        $po = $this->createPurchaseOrder('received');
        $poItem = $this->createPurchaseOrderItem($po->id, $this->product->id, 100, 100);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 10,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Purchase order is already fully received']);
    }

    public function test_receive_validates_missing_items(): void
    {
        $po = $this->createPurchaseOrder('pending');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_receive_validates_nonexistent_purchase_order_item(): void
    {
        $po = $this->createPurchaseOrder('pending');

        $response = $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => 9999,
                        'quantity' => 10,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
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

        $this->actingAs($this->admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 10,
                        'expiry_date' => now()->addDays(60)->format('Y-m-d'),
                    ],
                ],
            ]);

        $batch = Batch::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($batch);
        $this->assertStringStartsWith('BATCH-', $batch->batch_number);
    }

    // ---------------------------------------------------------------
    // Unauthenticated access
    // ---------------------------------------------------------------

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/purchase-orders')->assertStatus(401);
        $this->postJson('/api/purchase-orders', [])->assertStatus(401);
        $this->getJson('/api/purchase-orders/1')->assertStatus(401);
        $this->putJson('/api/purchase-orders/1', [])->assertStatus(401);
        $this->deleteJson('/api/purchase-orders/1')->assertStatus(401);
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
}
