<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
    }

    protected function mockReportService(): void
    {
        $mock = \Mockery::mock(ReportService::class);
        $mock->shouldReceive('generateSalesReport')->andReturn([
            'total_revenue' => 1000,
            'total_orders' => 10,
            'average_order_value' => 100,
            'by_status' => collect(),
            'daily_breakdown' => collect(),
        ]);
        $mock->shouldReceive('generateInventoryReport')->andReturn([
            'total_products' => 50,
            'active_products' => 45,
            'low_stock' => 3,
            'out_of_stock' => 2,
            'total_value' => 5000,
            'expiring_soon' => 5,
        ]);
        $mock->shouldReceive('generateDriverPerformanceReport')->andReturn([
            'total_drivers' => 5,
            'total_deliveries' => 100,
            'completed_deliveries' => 90,
            'on_time_rate' => 90.0,
            'drivers' => collect(),
        ]);
        $mock->shouldReceive('generateStoreAnalysisReport')->andReturn([
            'total_stores' => 20,
            'active_stores' => 15,
            'avg_orders_per_store' => 5.0,
            'avg_value_per_store' => 250.0,
            'stores' => collect(),
        ]);
        $mock->shouldReceive('generateProfitabilityReport')->andReturn([
            'revenue' => 10000,
            'cogs' => 6000,
            'delivery_costs' => 1000,
            'gross_profit' => 4000,
            'net_profit' => 3000,
            'profit_margin' => 30.0,
        ]);
        $mock->shouldReceive('generateForecastReport')->andReturn([
            'average_daily_revenue' => 333.33,
            'trend' => 10.5,
            'forecast_30_days' => [],
            'projected_monthly' => 10000,
        ]);

        App::instance(ReportService::class, $mock);
    }

    public function test_sales_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/sales');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_revenue',
                    'total_orders',
                    'average_order_value',
                    'by_status',
                    'daily_breakdown',
                ],
            ]);
    }

    public function test_inventory_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/inventory');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'active_products',
                    'low_stock',
                    'out_of_stock',
                    'total_value',
                    'expiring_soon',
                ],
            ]);
    }

    public function test_driver_performance_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/driver-performance');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_drivers',
                    'total_deliveries',
                    'completed_deliveries',
                    'on_time_rate',
                    'drivers',
                ],
            ]);
    }

    public function test_store_analysis_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/store-analysis');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_stores',
                    'active_stores',
                    'avg_orders_per_store',
                    'avg_value_per_store',
                    'stores',
                ],
            ]);
    }

    public function test_profitability_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/profitability');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'revenue',
                    'cogs',
                    'delivery_costs',
                    'gross_profit',
                    'net_profit',
                    'profit_margin',
                ],
            ]);
    }

    public function test_forecast_report_returns_data(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/forecast');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'average_daily_revenue',
                    'trend',
                    'forecast_30_days',
                    'projected_monthly',
                ],
            ]);
    }

    public function test_date_range_defaults_to_current_month(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $response = $this->getJson('/api/reports/sales');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('total_revenue', $data);
    }

    public function test_date_range_custom_dates(): void
    {
        Sanctum::actingAs($this->admin);
        $this->mockReportService();

        $startDate = now()->subMonth()->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/reports/sales?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
    }

    public function test_unknown_type_throws_error(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/reports/unknown-type');

        $response->assertStatus(500);
    }

    public function test_unauthenticated_user_cannot_access_reports(): void
    {
        $response = $this->getJson('/api/reports/sales');
        $response->assertStatus(401);
    }
}
