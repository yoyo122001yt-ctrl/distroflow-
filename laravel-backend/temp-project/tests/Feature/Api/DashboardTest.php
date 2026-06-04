<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\RetailStore;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_stats_returns_all_kpis(): void
    {
        Sanctum::actingAs($this->admin);

        RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Store One',
            'store_type' => 'grocery',
            'phone' => '1234567890',
            'credit_limit' => 50000,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        RetailStore::create([
            'code' => 'STR-002',
            'business_name' => 'Store Two',
            'store_type' => 'convenience',
            'phone' => '1234567891',
            'credit_limit' => 30000,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Product A',
            'sku' => 'SKU-001',
            'cost_price' => 10,
            'selling_price' => 15,
            'is_active' => true,
        ]);

        SalesOrder::create([
            'order_number' => 'SO-001',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'delivered',
            'total' => 500,
        ]);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_stores',
                    'total_products',
                    'active_products',
                    'orders_today',
                    'pending_orders',
                    'monthly_revenue',
                ],
                'message',
            ]);

        $response->assertJsonFragment([
            'total_stores' => 2,
            'total_products' => 1,
            'active_products' => 1,
        ]);
    }

    public function test_stats_with_no_data_returns_zeros(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonFragment([
                'total_stores' => 0,
                'total_products' => 0,
                'active_products' => 0,
                'orders_today' => 0,
                'pending_orders' => 0,
                'monthly_revenue' => 0,
            ]);
    }

    public function test_sales_trend_returns_daily_revenue(): void
    {
        Sanctum::actingAs($this->admin);

        RetailStore::create([
            'code' => 'STR-003',
            'business_name' => 'Store Three',
            'store_type' => 'grocery',
            'phone' => '1234567892',
            'credit_limit' => 50000,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        SalesOrder::create([
            'order_number' => 'SO-002',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now()->subDays(5),
            'status' => 'delivered',
            'total' => 250,
        ]);

        SalesOrder::create([
            'order_number' => 'SO-003',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now()->subDays(2),
            'status' => 'delivered',
            'total' => 350,
        ]);

        SalesOrder::create([
            'order_number' => 'SO-004',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'total' => 100,
        ]);

        $response = $this->getJson('/api/dashboard/sales-trend');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'message',
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_sales_trend_only_includes_delivered_orders(): void
    {
        Sanctum::actingAs($this->admin);

        RetailStore::create([
            'code' => 'STR-004',
            'business_name' => 'Store Four',
            'store_type' => 'grocery',
            'phone' => '1234567893',
            'credit_limit' => 50000,
            'current_balance' => 0,
            'status' => 'active',
        ]);

        SalesOrder::create([
            'order_number' => 'SO-005',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'delivered',
            'total' => 200,
        ]);

        SalesOrder::create([
            'order_number' => 'SO-006',
            'retail_store_id' => 1,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'total' => 300,
        ]);

        $response = $this->getJson('/api/dashboard/sales-trend');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $this->getJson('/api/dashboard/stats')->assertStatus(401);
        $this->getJson('/api/dashboard/sales-trend')->assertStatus(401);
    }
}
