<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Warehouse $warehouse;
    private RetailStore $store;
    private Product $product;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = new User;
        $this->adminUser->name = 'Admin';
        $this->adminUser->email = 'admin@test.com';
        $this->adminUser->password = bcrypt('password');
        $this->adminUser->role = 'admin';
        $this->adminUser->save();

        $this->warehouse = new Warehouse;
        $this->warehouse->name = 'Main Warehouse';
        $this->warehouse->code = 'WH-MAIN';
        $this->warehouse->is_active = true;
        $this->warehouse->save();

        $this->store = new RetailStore;
        $this->store->code = 'STR-TEST';
        $this->store->business_name = 'Test Store';
        $this->store->store_type = 'grocery';
        $this->store->credit_limit = 100000;
        $this->store->current_balance = 0;
        $this->store->payment_terms = 'cod';
        $this->store->status = 'active';
        $this->store->warehouse_id = $this->warehouse->id;
        $this->store->save();

        $category = new ProductCategory;
        $category->name = 'General';
        $category->slug = 'general';
        $category->is_active = true;
        $category->save();

        $this->product = new Product;
        $this->product->name = 'Test Product A';
        $this->product->sku = 'PRD-A';
        $this->product->unit = 'piece';
        $this->product->cost_price = 10.00;
        $this->product->selling_price = 25.00;
        $this->product->category_id = $category->id;
        $this->product->is_active = true;
        $this->product->save();

        $this->product2 = new Product;
        $this->product2->name = 'Test Product B';
        $this->product2->sku = 'PRD-B';
        $this->product2->unit = 'piece';
        $this->product2->cost_price = 20.00;
        $this->product2->selling_price = 50.00;
        $this->product2->category_id = $category->id;
        $this->product2->is_active = true;
        $this->product2->save();
    }

    private function createOrder(array $overrides = []): SalesOrder
    {
        $order = new SalesOrder;
        $order->order_number = $overrides['order_number'] ?? 'ORD-' . uniqid();
        $order->retail_store_id = $overrides['retail_store_id'] ?? $this->store->id;
        $order->warehouse_id = $overrides['warehouse_id'] ?? $this->warehouse->id;
        $order->created_by = $overrides['created_by'] ?? $this->adminUser->id;
        $order->order_date = $overrides['order_date'] ?? now();
        $order->status = $overrides['status'] ?? 'pending';
        $order->source = $overrides['source'] ?? 'phone';
        $order->subtotal = $overrides['subtotal'] ?? 100.00;
        $order->discount = $overrides['discount'] ?? 0;
        $order->tax = $overrides['tax'] ?? 0;
        $order->total = $overrides['total'] ?? 100.00;
        $order->balance_due = $overrides['balance_due'] ?? 100.00;
        $order->notes = $overrides['notes'] ?? null;
        $order->save();
        return $order;
    }

    public function test_index_lists_orders(): void
    {
        $this->createOrder(['order_number' => 'ORD-TEST-001', 'status' => 'pending']);
        $this->createOrder(['order_number' => 'ORD-TEST-002', 'status' => 'approved']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'order_number', 'status', 'retail_store', 'items']],
                    'current_page',
                    'total',
                ],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales orders retrieved'])
            ->assertJsonFragment(['total' => 2]);
    }

    public function test_index_can_filter_by_status(): void
    {
        $this->createOrder(['order_number' => 'ORD-FILTER-001', 'status' => 'pending']);
        $this->createOrder(['order_number' => 'ORD-FILTER-002', 'status' => 'delivered']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/orders?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_store_creates_order_with_items(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_price' => 25.00,
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'quantity_ordered' => 5,
                        'unit_price' => 50.00,
                    ],
                ],
                'notes' => 'Test order notes',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status', 'items', 'retail_store', 'created_by'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales order created'])
            ->assertJsonFragment(['status' => 'pending']);

        $this->assertDatabaseHas('sales_orders', [
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'retail_store_id',
                'warehouse_id',
                'order_date',
                'source',
                'items',
            ]);
    }

    public function test_store_validates_items_required(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_store_validates_item_product_exists(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => 9999,
                        'quantity_ordered' => 1,
                        'unit_price' => 10.00,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_show_returns_order(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-SHOW-001']);

        $item = new OrderItem;
        $item->sales_order_id = $order->id;
        $item->product_id = $this->product->id;
        $item->quantity_ordered = 4;
        $item->unit_price = 25.00;
        $item->total_price = 100.00;
        $item->status = 'pending';
        $item->save();

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status', 'retail_store', 'items', 'created_by'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales order retrieved'])
            ->assertJsonFragment(['order_number' => 'ORD-SHOW-001']);
    }

    public function test_show_returns_404_for_non_existent_order(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/orders/9999');

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Sales order not found']);
    }

    public function test_update_modifies_pending_order(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-UPDATE-001']);

        $item = new OrderItem;
        $item->sales_order_id = $order->id;
        $item->product_id = $this->product->id;
        $item->quantity_ordered = 2;
        $item->unit_price = 25.00;
        $item->total_price = 50.00;
        $item->status = 'pending';
        $item->save();

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/orders/{$order->id}", [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'app',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 5,
                        'unit_price' => 30.00,
                    ],
                ],
                'notes' => 'Updated via test',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status', 'items', 'retail_store'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales order updated']);

        $this->assertDatabaseHas('order_items', [
            'sales_order_id' => $order->id,
            'quantity_ordered' => 5,
            'unit_price' => 30.00,
        ]);
    }

    public function test_update_fails_for_non_pending_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-NOUPDATE-001',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/orders/{$order->id}", [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'app',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 3,
                        'unit_price' => 25.00,
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot modify order after it has been processed']);
    }

    public function test_destroy_deletes_pending_order(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-DELETE-001']);

        $item = new OrderItem;
        $item->sales_order_id = $order->id;
        $item->product_id = $this->product->id;
        $item->quantity_ordered = 1;
        $item->unit_price = 30.00;
        $item->total_price = 30.00;
        $item->status = 'pending';
        $item->save();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Sales order deleted']);

        $this->assertDatabaseMissing('sales_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('order_items', ['sales_order_id' => $order->id]);
    }

    public function test_destroy_fails_for_non_pending_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-NODELETE-001',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot delete order in current status']);
    }

    public function test_destroy_allows_deleting_cancelled_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-DELCANCEL-001',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Sales order deleted']);
    }

    public function test_status_breakdown_returns_counts(): void
    {
        $this->createOrder(['order_number' => 'ORD-SB-001', 'status' => 'pending']);
        $this->createOrder(['order_number' => 'ORD-SB-002', 'status' => 'pending']);
        $this->createOrder(['order_number' => 'ORD-SB-003', 'status' => 'delivered']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/orders/status-breakdown');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['status', 'count']],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Status breakdown retrieved'])
            ->assertJsonFragment(['status' => 'pending', 'count' => 2])
            ->assertJsonFragment(['status' => 'delivered', 'count' => 1]);
    }

    public function test_approve_approves_pending_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-APPROVE-001',
            'status' => 'pending',
            'total' => 100.00,
            'subtotal' => 100.00,
            'balance_due' => 100.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/approve", [
                'notes' => 'Approved by admin',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status', 'approved_by'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales order approved'])
            ->assertJsonFragment(['status' => 'approved']);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'approved',
            'approval_notes' => 'Approved by admin',
        ]);

        $this->store->refresh();
        $this->assertEquals(100.00, $this->store->current_balance);
    }

    public function test_approve_fails_for_non_pending_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-APPROVE-FAIL-001',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/approve");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Order is not in pending status']);
    }

    public function test_approve_fails_when_credit_limit_exceeded(): void
    {
        $storeWithLowCredit = new RetailStore;
        $storeWithLowCredit->code = 'STR-LOW';
        $storeWithLowCredit->business_name = 'Low Credit Store';
        $storeWithLowCredit->store_type = 'grocery';
        $storeWithLowCredit->credit_limit = 100;
        $storeWithLowCredit->current_balance = 90;
        $storeWithLowCredit->payment_terms = 'cod';
        $storeWithLowCredit->status = 'active';
        $storeWithLowCredit->warehouse_id = $this->warehouse->id;
        $storeWithLowCredit->save();

        $order = $this->createOrder([
            'order_number' => 'ORD-CREDIT-FAIL-001',
            'retail_store_id' => $storeWithLowCredit->id,
            'total' => 50.00,
            'subtotal' => 50.00,
            'balance_due' => 50.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/approve");

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Order exceeds store credit limit']);
    }

    public function test_assign_route_assigns_route_to_approved_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-ROUTE-001',
            'status' => 'approved',
        ]);

        $route = new Route;
        $route->code = 'RTE-TEST';
        $route->name = 'Test Delivery Route';
        $route->warehouse_id = $this->warehouse->id;
        $route->status = 'active';
        $route->save();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/assign-route", [
                'route_id' => $route->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status', 'route'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Route assigned to order'])
            ->assertJsonFragment(['status' => 'assigned']);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'route_id' => $route->id,
            'status' => 'assigned',
        ]);
    }

    public function test_assign_route_validates_route_exists(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-ROUTE-FAIL-001',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/assign-route", [
                'route_id' => 9999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['route_id']);
    }

    public function test_assign_route_fails_for_pending_order(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-ROUTE-FAIL-002']);

        $route = new Route;
        $route->code = 'RTE-FAIL';
        $route->name = 'Fail Route';
        $route->warehouse_id = $this->warehouse->id;
        $route->status = 'active';
        $route->save();

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/assign-route", [
                'route_id' => $route->id,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Order must be approved before assigning a route']);
    }

    public function test_cancel_cancels_pending_order(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-CANCEL-001']);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Customer changed their mind',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'order_number', 'status'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Sales order cancelled'])
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cancel_reverses_balance_for_approved_order(): void
    {
        $this->store->current_balance = 200;
        $this->store->save();

        $order = $this->createOrder([
            'order_number' => 'ORD-CANCEL-BAL-001',
            'status' => 'approved',
            'total' => 150.00,
            'subtotal' => 150.00,
            'balance_due' => 150.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Out of stock',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'cancelled']);

        $this->store->refresh();
        $this->assertEquals(50.00, (float) $this->store->current_balance);
    }

    public function test_cancel_validates_reason_required(): void
    {
        $order = $this->createOrder(['order_number' => 'ORD-CANCEL-FAIL-001']);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/cancel", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_cancel_fails_for_delivered_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-CANCEL-DEL-001',
            'status' => 'delivered',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Too late',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot cancel order in current status']);
    }

    public function test_cancel_fails_for_cancelled_order(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-CANCEL-AGAIN-001',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/orders/{$order->id}/cancel", [
                'reason' => 'Double cancel',
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cannot cancel order in current status']);
    }

    public function test_unauthenticated_user_cannot_access_orders(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        $this->postJson('/api/orders', [])->assertStatus(401);
        $this->getJson('/api/orders/1')->assertStatus(401);
        $this->putJson('/api/orders/1', [])->assertStatus(401);
        $this->deleteJson('/api/orders/1')->assertStatus(401);
        $this->getJson('/api/orders/status-breakdown')->assertStatus(401);
        $this->postJson('/api/orders/1/approve')->assertStatus(401);
        $this->postJson('/api/orders/1/assign-route', ['route_id' => 1])->assertStatus(401);
        $this->postJson('/api/orders/1/cancel', ['reason' => 'test'])->assertStatus(401);
    }
}
