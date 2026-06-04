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
use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\DeliveryItem;
use App\Models\DeliveryPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderToDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $driver;
    private Warehouse $warehouse;
    private RetailStore $store;
    private Product $product;
    private Product $product2;
    private ProductCategory $category;
    private Supplier $supplier;
    private Truck $truck;

    protected function setUp(): void
    {
        parent::setUp();

        User::resolveRelationUsing('retailStore', function ($user) {
            return $user->belongsTo(RetailStore::class, 'warehouse_id', 'warehouse_id');
        });

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'address' => '100 Warehouse St',
            'city' => 'Cairo',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->driver = User::create([
            'name' => 'Driver User',
            'email' => 'driver@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'driver',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Test Grocery Store',
            'store_type' => 'grocery',
            'contact_person' => 'Ahmed',
            'phone' => '+201234567890',
            'email' => 'store@test.com',
            'address' => '45 Market St',
            'city' => 'Cairo',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'credit_limit' => 100000,
            'current_balance' => 0,
            'payment_terms' => 'net_30',
            'status' => 'active',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Best Drinks Co',
            'contact_person' => 'Ali',
            'phone' => '+201112223333',
            'email' => 'supplier@test.com',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Cola 330ml',
            'sku' => 'COLA-330',
            'unit' => 'case',
            'cost_price' => 8.00,
            'selling_price' => 15.00,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'name' => 'Water 1L',
            'sku' => 'WTR-1000',
            'unit' => 'case',
            'cost_price' => 3.00,
            'selling_price' => 7.00,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-001',
            'expiry_date' => now()->addMonths(6),
            'quantity' => 500,
            'available_quantity' => 500,
            'cost_price' => 8.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now()->subDays(10),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-002',
            'expiry_date' => now()->addMonths(3),
            'quantity' => 1000,
            'available_quantity' => 1000,
            'cost_price' => 3.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now()->subDays(5),
            'status' => 'available',
        ]);

        $this->truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'ABC-1234',
            'model' => 'Isuzu NPR',
            'capacity_weight' => 5000,
            'capacity_volume' => 20,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    public function test_complete_order_to_delivery_workflow(): void
    {
        // STEP 1: Create sales order via API
        $orderResponse = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 20,
                        'unit_price' => 15.00,
                    ],
                    [
                        'product_id' => $this->product2->id,
                        'quantity_ordered' => 30,
                        'unit_price' => 7.00,
                    ],
                ],
                'notes' => 'Rush delivery needed',
            ]);

        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        $order = SalesOrder::with('items')->find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(2, $order->items->count());
        $this->assertEquals(510.00, (float) $order->total);
        $this->assertEquals($this->store->id, $order->retail_store_id);

        // STEP 2: Approve the order
        $this->store->update(['current_balance' => 0]);
        $approveResponse = $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/approve", [
                'notes' => 'Approved for same-day delivery',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonFragment(['status' => 'approved']);

        $order->refresh();
        $this->assertEquals('approved', $order->status);
        $this->assertNotNull($order->approved_at);
        $this->assertEquals($this->admin->id, $order->approved_by);
        $this->store->refresh();
        $this->assertEqualsWithDelta(510.00, (float) $this->store->current_balance, 0.01);

        // STEP 3: Create a route with stops
        $route = Route::create([
            'code' => 'RTE-001',
            'name' => 'Downtown Delivery Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $routeStop = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'is_active' => true,
        ]);

        $createRouteResponse = $this->actingAs($this->admin)
            ->getJson("/api/routes/{$route->id}");

        $createRouteResponse->assertStatus(200);

        // STEP 4: Assign route to order
        $assignRouteResponse = $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/assign-route", [
                'route_id' => $route->id,
            ]);

        $assignRouteResponse->assertStatus(200)
            ->assertJsonFragment(['status' => 'assigned']);

        $order->refresh();
        $this->assertEquals('assigned', $order->status);
        $this->assertEquals($route->id, $order->route_id);

        // STEP 5: Assign driver and truck to route
        $assignmentResponse = $this->actingAs($this->admin)
            ->postJson("/api/routes/{$route->id}/assign", [
                'driver_id' => $this->driver->id,
                'truck_id' => $this->truck->id,
                'assignment_date' => now()->format('Y-m-d'),
            ]);

        $assignmentResponse->assertStatus(201);
        $assignmentId = $assignmentResponse->json('data.id');

        $assignment = RouteAssignment::find($assignmentId);
        $this->assertNotNull($assignment);
        $this->assertEquals('scheduled', $assignment->status);

        // STEP 6: Create delivery
        $deliveryResponse = $this->actingAs($this->admin)
            ->postJson('/api/deliveries', [
                'route_assignment_id' => $assignmentId,
                'delivery_date' => now()->format('Y-m-d'),
            ]);

        $deliveryResponse->assertStatus(201);
        $deliveryId = $deliveryResponse->json('data.id');

        $delivery = Delivery::find($deliveryId);
        $this->assertNotNull($delivery);
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals($this->driver->id, $delivery->driver_id);
        $this->assertEquals($this->truck->id, $delivery->truck_id);

        // STEP 7: Create delivery stops from route stops
        $deliveryStop = DeliveryStop::create([
            'delivery_id' => $delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'status' => 'pending',
        ]);

        $orderItemIds = $order->items->pluck('id')->toArray();

        // STEP 8: Complete delivery with items and payments
        $completeResponse = $this->actingAs($this->admin)
            ->postJson("/api/deliveries/{$deliveryId}/complete", [
                'items' => [
                    [
                        'order_item_id' => $orderItemIds[0],
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 20,
                    ],
                    [
                        'order_item_id' => $orderItemIds[1],
                        'product_id' => $this->product2->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 30,
                    ],
                ],
                'payments' => [
                    [
                        'amount' => 510.00,
                        'payment_method' => 'cash',
                    ],
                ],
                'notes' => 'All items delivered successfully',
            ]);

        $completeResponse->assertStatus(200);

        // STEP 9: Verify all statuses are correct
        $delivery->refresh();
        $this->assertEquals('completed', $delivery->status);
        $this->assertNotNull($delivery->completed_at);
        $this->assertEqualsWithDelta(510.00, (float) $delivery->total_sales, 0.01);
        $this->assertEqualsWithDelta(510.00, (float) $delivery->total_collected, 0.01);

        $order->refresh();
        $this->assertEquals('delivered', $order->status);

        $orderItem0 = OrderItem::find($orderItemIds[0]);
        $this->assertEquals(20, (float) $orderItem0->quantity_delivered);
        $this->assertEquals('delivered', $orderItem0->status);

        $orderItem1 = OrderItem::find($orderItemIds[1]);
        $this->assertEquals(30, (float) $orderItem1->quantity_delivered);
        $this->assertEquals('delivered', $orderItem1->status);

        $this->assertDatabaseHas('delivery_payments', [
            'delivery_id' => $delivery->id,
            'amount' => 510.00,
            'payment_method' => 'cash',
        ]);

        // STEP 10: Generate invoice via service
        $invoiceService = app(\App\Services\InvoiceService::class);
        $invoice = $invoiceService->generateInvoice($orderId);

        $this->assertNotNull($invoice);
        $this->assertEquals('unpaid', $invoice->status);
        $this->assertEqualsWithDelta(510.00, (float) $invoice->total, 0.01);
        $this->assertEquals($this->store->id, $invoice->retail_store_id);
        $this->assertEquals($orderId, $invoice->sales_order_id);

        // STEP 11: Record invoice payment
        $payment = $invoiceService->recordPayment($invoice->id, 300.00, 'bank_transfer', [
            'reference_number' => 'TXN-12345',
        ]);

        $this->assertNotNull($payment);
        $this->assertEquals(300.00, (float) $payment->amount);

        $invoice->refresh();
        $this->assertEquals('partial', $invoice->status);
        $this->assertEqualsWithDelta(300.00, (float) $invoice->amount_paid, 0.01);
        $this->assertEqualsWithDelta(210.00, (float) $invoice->balance_due, 0.01);

        // STEP 12: Record remaining payment
        $payment2 = $invoiceService->recordPayment($invoice->id, 210.00, 'cash');

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEqualsWithDelta(510.00, (float) $invoice->amount_paid, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $invoice->balance_due, 0.01);
    }

    public function test_order_status_lifecycle_through_all_stages(): void
    {
        $orderResponse = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'app',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_price' => 15.00,
                    ],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');

        // pending
        $order = SalesOrder::find($orderId);
        $this->assertEquals('pending', $order->status);

        // approved
        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/approve");
        $order->refresh();
        $this->assertEquals('approved', $order->status);

        // assigned
        $route = Route::create([
            'code' => 'RTE-LC',
            'name' => 'Lifecycle Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/assign-route", [
                'route_id' => $route->id,
            ]);
        $order->refresh();
        $this->assertEquals('assigned', $order->status);

        // Verify the complete status chain
        $this->assertContains($order->status, ['pending', 'approved', 'assigned', 'in_transit', 'delivered', 'cancelled']);
    }

    public function test_order_cancellation_reverses_credit(): void
    {
        $this->store->update(['current_balance' => 1000]);

        $orderResponse = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_price' => 15.00,
                    ],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/approve");

        $this->store->refresh();
        $this->assertEqualsWithDelta(1150.00, (float) $this->store->current_balance, 0.01);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/cancel", [
                'reason' => 'Customer cancelled',
            ]);

        $order = SalesOrder::find($orderId);
        $this->assertEquals('cancelled', $order->status);

        $this->store->refresh();
        $this->assertEqualsWithDelta(1000.00, (float) $this->store->current_balance, 0.01);
    }

    public function test_delivery_with_partial_delivery_and_returns(): void
    {
        $orderResponse = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 50,
                        'unit_price' => 15.00,
                    ],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/approve");
        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/assign-route", [
                'route_id' => $this->createRoute()->id,
            ]);

        $order = SalesOrder::find($orderId);
        $orderItem = $order->items->first();

        $route = Route::first();
        $assignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'assignment_date' => now(),
            'status' => 'active',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-PARTIAL-001',
            'route_assignment_id' => $assignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
            'delivery_date' => now(),
            'status' => 'in_progress',
        ]);

        $routeStop = RouteStop::where('route_id', $route->id)->first();
        DeliveryStop::create([
            'delivery_id' => $delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/deliveries/{$delivery->id}/complete", [
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $this->batch->id,
                        'quantity_delivered' => 45,
                        'quantity_returned' => 2,
                        'return_reason' => 'damaged',
                    ],
                ],
                'payments' => [
                    [
                        'amount' => 45 * 15.00,
                        'payment_method' => 'cash',
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertEquals('completed', $delivery->status);
        $this->assertEqualsWithDelta(45 * 15.00, (float) $delivery->total_sales, 0.01);
        $this->assertEqualsWithDelta(2 * 8.00, (float) $delivery->total_returns, 0.01);

        $orderItem->refresh();
        $this->assertEquals('partial', $orderItem->status);
        $this->assertEqualsWithDelta(45, (float) $orderItem->quantity_delivered, 0.01);
        $this->assertEqualsWithDelta(2, (float) $orderItem->quantity_returned, 0.01);

        $this->assertDatabaseHas('delivery_returns', [
            'delivery_id' => $delivery->id,
            'product_id' => $this->product->id,
            'return_reason' => 'damaged',
        ]);
    }

    public function test_full_workflow_verifies_store_balance_after_invoice_payment(): void
    {
        $this->store->update(['current_balance' => 0, 'credit_limit' => 50000]);

        $orderResponse = $this->actingAs($this->admin)
            ->postJson('/api/orders', [
                'retail_store_id' => $this->store->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->format('Y-m-d'),
                'source' => 'phone',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 10,
                        'unit_price' => 15.00,
                    ],
                ],
            ]);

        $orderId = $orderResponse->json('data.id');

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$orderId}/approve");

        $this->store->refresh();
        $this->assertEqualsWithDelta(150.00, (float) $this->store->current_balance, 0.01);

        $invoiceService = app(\App\Services\InvoiceService::class);
        $invoice = $invoiceService->generateInvoice($orderId);

        $invoiceService->recordPayment($invoice->id, 150.00, 'cash');

        $this->store->refresh();
        $this->assertEqualsWithDelta(0.00, (float) $this->store->current_balance, 0.01);
    }

    private function createRoute(): Route
    {
        $route = Route::create([
            'code' => 'RTE-AUTO-' . strtoupper(uniqid()),
            'name' => 'Auto Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        return $route;
    }
}
