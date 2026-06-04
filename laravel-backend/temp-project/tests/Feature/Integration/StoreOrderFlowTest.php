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
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class StoreOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $storeUser;
    private RetailStore $store;
    private User $admin;
    private User $driver;
    private Warehouse $warehouse;
    private Product $product;
    private Product $product2;
    private ProductCategory $category;

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
            'name' => 'Driver',
            'email' => 'driver@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'driver',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Quick Mart',
            'store_type' => 'convenience',
            'contact_person' => 'Sara',
            'phone' => '+201987654321',
            'email' => 'quickmart@test.com',
            'address' => '78 Shop St',
            'city' => 'Alexandria',
            'latitude' => 31.2001,
            'longitude' => 29.9187,
            'credit_limit' => 50000,
            'current_balance' => 0,
            'payment_terms' => 'net_30',
            'status' => 'active',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->storeUser = User::create([
            'name' => 'Store Manager',
            'email' => 'storeuser@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'store',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Snacks',
            'slug' => 'snacks',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Snack Distributors',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'Chips Classic',
            'sku' => 'CHP-001',
            'unit' => 'piece',
            'cost_price' => 3.00,
            'selling_price' => 6.00,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $this->product2 = Product::create([
            'name' => 'Chocolate Bar',
            'sku' => 'CHC-001',
            'unit' => 'piece',
            'cost_price' => 2.00,
            'selling_price' => 4.50,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-001',
            'expiry_date' => now()->addMonths(6),
            'quantity' => 500,
            'available_quantity' => 500,
            'cost_price' => 3.00,
            'supplier_id' => $supplier->id,
            'received_date' => now()->subDays(5),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-002',
            'expiry_date' => now()->addMonths(3),
            'quantity' => 300,
            'available_quantity' => 300,
            'cost_price' => 2.00,
            'supplier_id' => $supplier->id,
            'received_date' => now()->subDays(3),
            'status' => 'available',
        ]);
    }

    public function test_store_browses_catalog(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'name', 'sku', 'selling_price']],
                    'current_page',
                    'total',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_store_searches_products(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/products?search=Chips');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    public function test_store_filters_by_category(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->getJson("/api/store/products?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_store_views_categories(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'products_count']],
            ]);
    }

    public function test_store_adds_to_cart(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                    ['product_id' => $this->product2->id, 'quantity' => 5],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['items', 'total', 'count'],
            ]);

        $this->assertEquals(15, $response->json('data.count'));
        $this->assertEqualsWithDelta(82.50, (float) $response->json('data.total'), 0.01);
    }

    public function test_store_updates_cart_quantity(): void
    {
        $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5],
                ],
            ]);

        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 15],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(90.00, (float) $response->json('data.total'), 0.01);
    }

    public function test_store_removes_from_cart(): void
    {
        $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5],
                    ['product_id' => $this->product2->id, 'quantity' => 3],
                ],
            ]);

        $response = $this->actingAs($this->storeUser)
            ->deleteJson("/api/store/cart/item/{$this->product->id}");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.count'));
    }

    public function test_store_places_order_from_cart(): void
    {
        $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 10],
                    ['product_id' => $this->product2->id, 'quantity' => 20],
                ],
            ]);

        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/store/orders/place', [
                'notes' => 'Please deliver before noon',
            ]);

        $response->assertStatus(201);

        $order = SalesOrder::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals($this->store->id, $order->retail_store_id);

        $cartResponse = $this->actingAs($this->storeUser)
            ->getJson('/api/store/cart');
        $this->assertEmpty($cartResponse->json('data.items'));
    }

    public function test_store_cannot_place_empty_cart_order(): void
    {
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/store/orders/place');

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cart is empty']);
    }

    public function test_store_views_order_history(): void
    {
        SalesOrder::create([
            'order_number' => 'ORD-HIST-001',
            'retail_store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'source' => 'app',
            'subtotal' => 100.00,
            'total' => 100.00,
            'balance_due' => 100.00,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [['id', 'order_number', 'status']],
                ],
            ]);
    }

    public function test_complete_store_to_delivery_flow(): void
    {
        // Store browses and adds to cart
        $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 20],
                ],
            ]);

        // Store places order
        $placeResponse = $this->actingAs($this->storeUser)
            ->postJson('/api/store/orders/place', [
                'notes' => 'Urgent order',
            ]);

        $placeResponse->assertStatus(201);

        $order = SalesOrder::latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);

        // Admin approves
        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/approve")
            ->assertStatus(200);

        $order->refresh();
        $this->assertEquals('approved', $order->status);

        // Create route, assign driver, create delivery
        $route = Route::create([
            'code' => 'RTE-STORE',
            'name' => 'Store Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/orders/{$order->id}/assign-route", [
                'route_id' => $route->id,
            ]);

        $truck = Truck::create([
            'code' => 'TRK-001',
            'plate_number' => 'DEF-5678',
            'status' => 'available',
            'is_active' => true,
        ]);

        $assignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => now(),
            'status' => 'active',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-STORE-001',
            'route_assignment_id' => $assignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $truck->id,
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

        $batch = Batch::where('product_id', $this->product->id)->first();
        $orderItem = $order->items()->first();

        // Admin completes delivery
        $this->actingAs($this->admin)
            ->postJson("/api/deliveries/{$delivery->id}/complete", [
                'items' => [
                    [
                        'order_item_id' => $orderItem->id,
                        'product_id' => $this->product->id,
                        'batch_id' => $batch->id,
                        'quantity_delivered' => 20,
                    ],
                ],
                'payments' => [
                    [
                        'amount' => 120.00,
                        'payment_method' => 'cash',
                    ],
                ],
            ])->assertStatus(200);

        // Verify delivery is completed
        $delivery->refresh();
        $this->assertEquals('completed', $delivery->status);

        // Store views order detail
        $this->actingAs($this->storeUser)
            ->getJson("/api/store/orders/{$order->id}")
            ->assertStatus(200);

        // Generate invoice
        $invoiceService = app(\App\Services\InvoiceService::class);
        $invoice = $invoiceService->generateInvoice($order->id);

        $this->assertNotNull($invoice);
        $this->assertEquals($order->id, $invoice->sales_order_id);
        $this->assertEquals($this->store->id, $invoice->retail_store_id);
    }

    public function test_store_tracks_delivery(): void
    {
        $route = Route::create([
            'code' => 'RTE-TRACK',
            'name' => 'Track Route',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $truck = Truck::create([
            'code' => 'TRK-T01',
            'plate_number' => 'GHI-9012',
            'status' => 'available',
            'is_active' => true,
        ]);

        $assignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => now(),
            'status' => 'active',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-TRACK-001',
            'route_assignment_id' => $assignment->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $truck->id,
            'delivery_date' => now(),
            'status' => 'in_transit',
            'tracking_token' => 'track_' . uniqid(),
        ]);

        $routeStop = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        DeliveryStop::create([
            'delivery_id' => $delivery->id,
            'route_stop_id' => $routeStop->id,
            'retail_store_id' => $this->store->id,
            'stop_order' => 1,
            'status' => 'arrived',
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson("/api/store/track/{$delivery->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'delivery_number', 'status'],
            ]);
    }

    public function test_store_non_active_products_not_shown(): void
    {
        Product::create([
            'name' => 'Discontinued Item',
            'sku' => 'DSC-001',
            'unit' => 'piece',
            'cost_price' => 1.00,
            'selling_price' => 2.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/products');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('data.total'));
    }
}
