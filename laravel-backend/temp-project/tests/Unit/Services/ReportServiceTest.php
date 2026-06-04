<?php

namespace Tests\Unit\Services;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\RetailStore;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\Truck;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportService;
use Carbon\Carbon;
use Database\Factories\ProductFactory;
use Database\Factories\RetailStoreFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Warehouse $warehouse;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportService::class);
        $this->warehouse = Warehouse::create([
            'name' => 'Main WH', 'code' => 'WH-001',
            'latitude' => 0, 'longitude' => 0, 'is_active' => true,
        ]);
        $this->user = UserFactory::new()->create();

        if (!Schema::hasColumn('deliveries', 'delivery_cost')) {
            Schema::table('deliveries', function ($table) {
                $table->decimal('delivery_cost', 12, 2)->default(0);
            });
        }
        if (!Schema::hasColumn('products', 'stock_quantity')) {
            Schema::table('products', function ($table) {
                $table->integer('stock_quantity')->default(0);
            });
        }
        if (!Schema::hasColumn('sales_orders', 'total_amount')) {
            Schema::table('sales_orders', function ($table) {
                $table->decimal('total_amount', 12, 2)->default(0);
            });
        }
        if (!Schema::hasColumn('warehouse_stock_movements', 'unit_cost')) {
            Schema::table('warehouse_stock_movements', function ($table) {
                $table->decimal('unit_cost', 12, 2)->default(0);
            });
        }

        \App\Models\RetailStore::resolveRelationUsing('salesOrders', function ($model) {
            return $model->hasMany(\App\Models\SalesOrder::class, 'retail_store_id');
        });

        Carbon::setTestNow(Carbon::parse('2025-06-15'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createOrder(array $overrides = []): SalesOrder
    {
        $store = RetailStoreFactory::new()->create();
        $data = array_merge([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-' . uniqid(),
            'order_date' => '2025-06-10',
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 500,
            'discount' => 0,
            'tax' => 41.25,
            'total' => 541.25,
        ], $overrides);

        $totalAmount = $data['total'] ?? 541.25;
        $order = SalesOrder::create($data);
        $order->total_amount = $totalAmount;
        $order->save();

        return $order;
    }

    private function createDelivery(array $overrides = []): Delivery
    {
        $truck = Truck::create([
            'code' => 'TRK-' . uniqid(),
            'plate_number' => 'PLT-' . uniqid(),
            'status' => 'available',
            'is_active' => true,
        ]);
        $route = Route::create([
            'name' => 'Route', 'code' => 'R-' . uniqid(),
            'warehouse_id' => $this->warehouse->id, 'status' => 'active',
        ]);
        $driver = UserFactory::new()->create(['role' => 'driver']);
        $assignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => now(),
            'status' => 'active',
        ]);

        $delivery = Delivery::create(array_merge([
            'delivery_number' => 'DEL-' . uniqid(),
            'route_assignment_id' => $assignment->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'delivery_date' => '2025-06-10',
            'status' => 'completed',
            'created_at' => '2025-06-10',
        ], $overrides));
        $delivery->delivery_cost = $overrides['delivery_cost'] ?? 75.00;
        $delivery->save();

        return $delivery;
    }

    public function test_generate_sales_report_returns_correct_totals()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15'));

        $this->createOrder(['order_number' => 'ORD-001', 'order_date' => '2025-06-10', 'subtotal' => 500, 'tax' => 41.25, 'total' => 541.25, 'status' => 'delivered']);
        $this->createOrder(['order_number' => 'ORD-002', 'order_date' => '2025-06-12', 'subtotal' => 300, 'tax' => 24.75, 'total' => 324.75, 'status' => 'delivered']);
        $this->createOrder(['order_number' => 'ORD-003', 'order_date' => '2025-06-14', 'subtotal' => 200, 'tax' => 16.50, 'total' => 216.50, 'status' => 'pending']);

        $report = $this->service->generateSalesReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertEquals(1082.50, $report['total_revenue']);
        $this->assertEquals(3, $report['total_orders']);
        $this->assertEquals(360.83, $report['average_order_value']);
        $this->assertEquals(2, $report['by_status']['delivered']);
        $this->assertEquals(1, $report['by_status']['pending']);
    }

    public function test_generate_sales_report_daily_breakdown()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15'));

        $this->createOrder(['order_number' => 'ORD-001', 'order_date' => '2025-06-10', 'total' => 541.25]);
        $this->createOrder(['order_number' => 'ORD-002', 'order_date' => '2025-06-10', 'total' => 324.75]);
        $this->createOrder(['order_number' => 'ORD-003', 'order_date' => '2025-06-14', 'total' => 216.50]);

        $report = $this->service->generateSalesReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $daily = $report['daily_breakdown'];
        $this->assertCount(2, $daily);
        $this->assertEquals('2025-06-10', $daily[0]->date);
        $this->assertEquals(2, $daily[0]->count);
        $this->assertEquals(866.00, (float) $daily[0]->revenue);
        $this->assertEquals('2025-06-14', $daily[1]->date);
        $this->assertEquals(1, $daily[1]->count);
    }

    public function test_generate_sales_report_returns_zero_values_when_no_orders()
    {
        $report = $this->service->generateSalesReport(
            new \DateTime('2025-01-01'),
            new \DateTime('2025-01-31')
        );

        $this->assertEquals(0, $report['total_revenue']);
        $this->assertEquals(0, $report['total_orders']);
        $this->assertEquals(0, $report['average_order_value']);
        $this->assertTrue($report['by_status']->isEmpty());
        $this->assertTrue($report['daily_breakdown']->isEmpty());
    }

    public function test_generate_sales_report_respects_date_range()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15'));

        $store = RetailStoreFactory::new()->create();
        $order = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => '2025-05-01',
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 500,
            'discount' => 0,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $order->total_amount = 541.25;
        $order->save();

        $this->createOrder(['order_number' => 'ORD-002', 'order_date' => '2025-06-10']);

        $report = $this->service->generateSalesReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertEquals(541.25, $report['total_revenue']);
        $this->assertEquals(1, $report['total_orders']);
    }

    public function test_generate_inventory_report_counts_products()
    {
        foreach ([
            ['name' => 'P1', 'sku' => 'SKU-001', 'stock_qty' => 50, 'is_active' => true],
            ['name' => 'P2', 'sku' => 'SKU-002', 'stock_qty' => 3, 'is_active' => true],
            ['name' => 'P3', 'sku' => 'SKU-003', 'stock_qty' => 0, 'is_active' => true],
            ['name' => 'P4', 'sku' => 'SKU-004', 'stock_qty' => 100, 'is_active' => false],
        ] as $prod) {
            $p = Product::create(['name' => $prod['name'], 'sku' => $prod['sku'], 'is_active' => $prod['is_active'], 'cost_price' => 10, 'selling_price' => 20]);
            $p->stock_quantity = $prod['stock_qty'];
            $p->save();
        }

        $report = $this->service->generateInventoryReport();

        $this->assertEquals(4, $report['total_products']);
        $this->assertEquals(3, $report['active_products']);
        $this->assertEquals(2, $report['low_stock']);
        $this->assertEquals(1, $report['out_of_stock']);
    }

    public function test_generate_inventory_report_calculates_total_value()
    {
        foreach ([
            ['name' => 'P1', 'sku' => 'SKU-001', 'stock_qty' => 10, 'is_active' => true, 'cost' => 5.00],
            ['name' => 'P2', 'sku' => 'SKU-002', 'stock_qty' => 20, 'is_active' => true, 'cost' => 3.50],
            ['name' => 'P3', 'sku' => 'SKU-003', 'stock_qty' => 100, 'is_active' => false, 'cost' => 10.00],
        ] as $prod) {
            $p = Product::create(['name' => $prod['name'], 'sku' => $prod['sku'], 'is_active' => $prod['is_active'], 'cost_price' => $prod['cost'], 'selling_price' => 10]);
            $p->stock_quantity = $prod['stock_qty'];
            $p->save();
        }

        $report = $this->service->generateInventoryReport();

        $this->assertEquals(120.00, $report['total_value']);
    }

    public function test_generate_inventory_report_returns_zero_value_when_no_products()
    {
        $report = $this->service->generateInventoryReport();

        $this->assertEquals(0, $report['total_products']);
        $this->assertEquals(0, $report['active_products']);
        $this->assertEquals(0, $report['low_stock']);
        $this->assertEquals(0, $report['out_of_stock']);
        $this->assertEquals(0, $report['total_value']);
    }

    public function test_generate_driver_performance_report_returns_correct_counts()
    {
        $driver = UserFactory::new()->create(['name' => 'John Driver', 'role' => 'driver']);

        $this->createDelivery(['driver_id' => $driver->id, 'delivery_number' => 'DEL-001', 'status' => 'completed', 'created_at' => '2025-06-10']);
        $this->createDelivery(['driver_id' => $driver->id, 'delivery_number' => 'DEL-002', 'status' => 'completed', 'created_at' => '2025-06-12']);
        $this->createDelivery(['driver_id' => $driver->id, 'delivery_number' => 'DEL-003', 'status' => 'cancelled', 'created_at' => '2025-06-14']);

        $report = $this->service->generateDriverPerformanceReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertGreaterThanOrEqual(1, $report['total_drivers']);
        $this->assertGreaterThanOrEqual(3, $report['total_deliveries']);
        $this->assertGreaterThanOrEqual(2, $report['completed_deliveries']);
    }

    public function test_generate_driver_performance_report_multiple_drivers()
    {
        $driver1 = UserFactory::new()->create(['name' => 'Driver One', 'role' => 'driver']);
        $driver2 = UserFactory::new()->create(['name' => 'Driver Two', 'role' => 'driver']);

        $this->createDelivery(['driver_id' => $driver1->id, 'delivery_number' => 'DEL-001', 'status' => 'completed', 'created_at' => '2025-06-10']);
        $this->createDelivery(['driver_id' => $driver2->id, 'delivery_number' => 'DEL-002', 'status' => 'completed', 'created_at' => '2025-06-11']);

        $report = $this->service->generateDriverPerformanceReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertGreaterThanOrEqual(2, $report['total_drivers']);
        $this->assertGreaterThanOrEqual(2, $report['total_deliveries']);
        $this->assertGreaterThanOrEqual(2, $report['completed_deliveries']);
    }

    public function test_generate_driver_performance_report_returns_zero_when_no_deliveries()
    {
        UserFactory::new()->create(['name' => 'Lonely Driver', 'role' => 'driver']);

        $report = $this->service->generateDriverPerformanceReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertGreaterThanOrEqual(1, $report['total_drivers']);
        $this->assertEquals(0, $report['total_deliveries']);
        $this->assertEquals(0, $report['completed_deliveries']);
        $this->assertEquals(0, $report['on_time_rate']);
    }

    public function test_generate_store_analysis_report_returns_correct_data()
    {
        $store1 = RetailStoreFactory::new()->create(['business_name' => 'Store Alpha']);
        $store2 = RetailStoreFactory::new()->create(['business_name' => 'Store Beta']);

        $o1 = SalesOrder::create([
            'retail_store_id' => $store1->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => '2025-06-10',
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 500,
            'discount' => 0,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $o1->total_amount = 541.25;
        $o1->save();

        $o2 = SalesOrder::create([
            'retail_store_id' => $store1->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-002',
            'order_date' => '2025-06-12',
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 300,
            'discount' => 0,
            'tax' => 24.75,
            'total' => 324.75,
        ]);
        $o2->total_amount = 324.75;
        $o2->save();

        $o3 = SalesOrder::create([
            'retail_store_id' => $store2->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-003',
            'order_date' => '2025-06-14',
            'status' => 'pending',
            'source' => 'phone',
            'subtotal' => 200,
            'discount' => 0,
            'tax' => 16.50,
            'total' => 216.50,
        ]);
        $o3->total_amount = 216.50;
        $o3->save();

        $report = $this->service->generateStoreAnalysisReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertEquals(2, $report['total_stores']);
        $this->assertEquals(2, $report['active_stores']);
        $this->assertEquals(1.5, $report['avg_orders_per_store']);
        $this->assertEquals(541.25, $report['avg_value_per_store']);

        $this->assertCount(2, $report['stores']);
        $this->assertEquals('Store Alpha', $report['stores'][0]['name']);
        $this->assertEquals(2, $report['stores'][0]['orders']);
    }

    public function test_generate_store_analysis_report_handles_inactive_stores()
    {
        RetailStoreFactory::new()->create(['business_name' => 'Inactive Store']);

        $report = $this->service->generateStoreAnalysisReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertEquals(1, $report['total_stores']);
        $this->assertEquals(0, $report['active_stores']);
        $this->assertEquals(0, $report['avg_orders_per_store']);
        $this->assertEquals(0, $report['avg_value_per_store']);
    }

    public function test_generate_profitability_report_calculates_all_metrics()
    {
        $this->createOrder(['order_number' => 'ORD-PROFIT', 'total' => 1082.50]);
        $this->createDelivery(['delivery_number' => 'DEL-PROFIT', 'delivery_cost' => 75.00, 'created_at' => '2025-06-10']);

        $product = Product::create(['name' => 'Profit Product', 'sku' => 'SKU-PROFIT', 'is_active' => true, 'cost_price' => 5, 'selling_price' => 10]);

        $batch = \App\Models\Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 80,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'out',
            'quantity' => 50,
            'quantity_before' => 100,
            'quantity_after' => 50,
            'unit_cost' => 5.00,
            'reference_type' => 'sales_order',
            'reference_id' => 1,
            'created_by' => $this->user->id,
        ]);

        $report = $this->service->generateProfitabilityReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertGreaterThan(0, $report['revenue']);
        $this->assertGreaterThanOrEqual(0, $report['cogs']);
        $this->assertEquals(75.00, $report['delivery_costs']);
        $this->assertEquals($report['revenue'] - $report['cogs'], $report['gross_profit']);
        $this->assertEquals($report['gross_profit'] - $report['delivery_costs'], $report['net_profit']);
        $this->assertIsFloat($report['profit_margin']);
    }

    public function test_generate_profitability_report_returns_zeros_when_no_data()
    {
        $report = $this->service->generateProfitabilityReport(
            new \DateTime('2025-01-01'),
            new \DateTime('2025-01-31')
        );

        $this->assertEquals(0, $report['revenue']);
        $this->assertEquals(0, $report['cogs']);
        $this->assertEquals(0, $report['delivery_costs']);
        $this->assertEquals(0, $report['gross_profit']);
        $this->assertEquals(0, $report['net_profit']);
        $this->assertEquals(0, $report['profit_margin']);
    }

    public function test_generate_forecast_report_returns_averages_and_trend()
    {
        $today = Carbon::now();
        $store = RetailStoreFactory::new()->create();

        $f1 = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-001',
            'order_date' => $today->copy()->subDays(5),
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 500,
            'discount' => 0,
            'tax' => 41.25,
            'total' => 541.25,
        ]);
        $f1->total_amount = 541.25;
        $f1->save();

        $f2 = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-002',
            'order_date' => $today->copy()->subDays(3),
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 300,
            'discount' => 0,
            'tax' => 24.75,
            'total' => 324.75,
        ]);
        $f2->total_amount = 324.75;
        $f2->save();

        $f3 = SalesOrder::create([
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'order_number' => 'ORD-003',
            'order_date' => $today->copy()->subDay(),
            'status' => 'delivered',
            'source' => 'phone',
            'subtotal' => 200,
            'discount' => 0,
            'tax' => 16.50,
            'total' => 216.50,
        ]);
        $f3->total_amount = 216.50;
        $f3->save();

        $report = $this->service->generateForecastReport();

        $this->assertGreaterThan(0, $report['average_daily_revenue']);
        $this->assertCount(30, $report['forecast_30_days']);
        $this->assertGreaterThan(0, $report['projected_monthly']);
    }

    public function test_generate_forecast_report_returns_zeros_when_no_orders()
    {
        $report = $this->service->generateForecastReport();

        $this->assertEquals(0, $report['average_daily_revenue']);
        $this->assertEquals(0, $report['trend']);
        $this->assertCount(30, $report['forecast_30_days']);
        $this->assertEquals(0, $report['projected_monthly']);
    }

    public function test_generate_forecast_report_forecast_values_are_non_negative()
    {
        $report = $this->service->generateForecastReport();

        foreach ($report['forecast_30_days'] as $value) {
            $this->assertGreaterThanOrEqual(0, $value);
        }
    }

    public function test_generate_sales_report_handles_empty_status_breakdown()
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15'));

        $report = $this->service->generateSalesReport(
            new \DateTime('2025-06-01'),
            new \DateTime('2025-06-30')
        );

        $this->assertTrue($report['by_status']->isEmpty());
    }

    public function test_generate_inventory_report_with_low_stock_threshold()
    {
        foreach ([
            ['name' => 'Low1', 'sku' => 'SKU-LOW1', 'stock_qty' => 5],
            ['name' => 'Low2', 'sku' => 'SKU-LOW2', 'stock_qty' => 1],
        ] as $prod) {
            $p = Product::create(['name' => $prod['name'], 'sku' => $prod['sku'], 'is_active' => true, 'cost_price' => 10, 'selling_price' => 15]);
            $p->stock_quantity = $prod['stock_qty'];
            $p->save();
        }

        $report = $this->service->generateInventoryReport();

        $this->assertEquals(2, $report['low_stock']);
    }
}
