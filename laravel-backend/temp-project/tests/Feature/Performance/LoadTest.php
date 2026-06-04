<?php

namespace Tests\Feature\Performance;

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
use App\Models\RouteAssignment;
use App\Models\Truck;
use App\Models\Delivery;
use App\Services\StoreCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LoadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $storeUser;
    private Warehouse $warehouse;

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

        $this->storeUser = User::create([
            'name' => 'Store User',
            'email' => 'store@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'store',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->seedTestData();
    }

    private function seedTestData(): void
    {
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

        $stores = [];
        for ($i = 0; $i < 10; $i++) {
            $stores[] = RetailStore::create([
                'code' => 'STR-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'business_name' => "Store {$i}",
                'store_type' => 'grocery',
                'credit_limit' => 100000,
                'current_balance' => 0,
                'payment_terms' => 'net_30',
                'status' => 'active',
                'warehouse_id' => $this->warehouse->id,
            ]);
        }

        $products = [];
        for ($i = 0; $i < 100; $i++) {
            $products[] = Product::create([
                'name' => "Product {$i}",
                'sku' => 'PRD-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'unit' => 'piece',
                'cost_price' => rand(100, 500) / 100,
                'selling_price' => rand(200, 1000) / 100,
                'category_id' => $category->id,
                'is_active' => true,
            ]);

            Batch::create([
                'product_id' => $products[$i]->id,
                'warehouse_id' => $this->warehouse->id,
                'batch_number' => 'BAT-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'expiry_date' => now()->addMonths(rand(1, 12)),
                'quantity' => 100,
                'available_quantity' => 100,
                'cost_price' => $products[$i]->cost_price,
                'supplier_id' => $supplier->id,
                'received_date' => now(),
                'status' => 'available',
            ]);
        }

        for ($i = 0; $i < 50; $i++) {
            $store = $stores[array_rand($stores)];
            $subtotal = 0;
            $items = [];

            $itemCount = rand(2, 8);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(1, 20);
                $price = $product->selling_price;
                $subtotal += $qty * $price;
                $items[] = ['product_id' => $product->id, 'qty' => $qty, 'price' => $price];
            }

            $order = SalesOrder::create([
                'order_number' => 'ORD-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'retail_store_id' => $store->id,
                'warehouse_id' => $this->warehouse->id,
                'created_by' => $this->admin->id,
                'order_date' => now()->subDays(rand(0, 30)),
                'status' => 'pending',
                'source' => 'phone',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'balance_due' => $subtotal,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'sales_order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['qty'],
                    'unit_price' => $item['price'],
                    'total_price' => $item['qty'] * $item['price'],
                    'status' => 'pending',
                ]);
            }
        }
    }

    public function test_dashboard_stats_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)->getJson('/api/dashboard/stats');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(2.0, $elapsed, "Dashboard stats took {$elapsed}s, expected < 2s");
    }

    public function test_product_listing_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)->getJson('/api/products');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Product listing took {$elapsed}s, expected < 1s");
    }

    public function test_product_listing_with_search_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)->getJson('/api/products?search=Product');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Product search took {$elapsed}s, expected < 1s");
    }

    public function test_order_listing_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)->getJson('/api/orders');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Order listing took {$elapsed}s, expected < 1s");
    }

    public function test_order_listing_with_filter_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)->getJson('/api/orders?status=pending');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Filtered order listing took {$elapsed}s, expected < 1s");
    }

    public function test_cart_operations_response_time(): void
    {
        $product = Product::first();

        $start = microtime(true);
        $response = $this->actingAs($this->storeUser)
            ->postJson('/api/store/cart/update', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5],
                ],
            ]);
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(0.5, $elapsed, "Cart update took {$elapsed}s, expected < 0.5s");
    }

    public function test_cart_get_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/cart');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(0.5, $elapsed, "Cart get took {$elapsed}s, expected < 0.5s");
    }

    public function test_store_products_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->storeUser)
            ->getJson('/api/store/products');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Store products took {$elapsed}s, expected < 1s");
    }

    public function test_status_breakdown_response_time(): void
    {
        $start = microtime(true);
        $response = $this->actingAs($this->admin)
            ->getJson('/api/orders/status-breakdown');
        $elapsed = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(1.0, $elapsed, "Status breakdown took {$elapsed}s, expected < 1s");
    }

    public function test_multiple_sequential_requests_performance(): void
    {
        $endpoints = [
            ['GET', '/api/dashboard/stats'],
            ['GET', '/api/products'],
            ['GET', '/api/orders'],
            ['GET', '/api/orders/status-breakdown'],
            ['GET', '/api/stores'],
        ];

        $totalStart = microtime(true);

        foreach ($endpoints as [$method, $url]) {
            $start = microtime(true);
            $this->actingAs($this->admin)->{strtolower($method) . 'Json'}($url);
            $elapsed = microtime(true) - $start;
            $this->assertLessThan(2.0, $elapsed, "{$method} {$url} took {$elapsed}s");
        }

        $totalElapsed = microtime(true) - $totalStart;
        $this->assertLessThan(5.0, $totalElapsed, "All endpoints together took {$totalElapsed}s, expected < 5s");
    }
}
