<?php

namespace Tests\Unit\Services;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Warehouse;
use App\Services\RouteOptimizationService;
use Database\Factories\RetailStoreFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteOptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RouteOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RouteOptimizationService::class);
    }

    public function test_haversine_returns_zero_for_same_point()
    {
        $distance = $this->service->haversine(41.8781, -87.6298, 41.8781, -87.6298);

        $this->assertEqualsWithDelta(0.0, $distance, 0.001);
    }

    public function test_haversine_returns_correct_distance_between_chicago_coordinates()
    {
        $lat1 = 41.8826;
        $lng1 = -87.6246;
        $lat2 = 41.8919;
        $lng2 = -87.6062;

        $distance = $this->service->haversine($lat1, $lng1, $lat2, $lng2);

        $this->assertEqualsWithDelta(1.8, $distance, 0.3);
    }

    public function test_haversine_is_commutative()
    {
        $a = $this->service->haversine(41.8781, -87.6298, 40.7128, -74.0060);
        $b = $this->service->haversine(40.7128, -74.0060, 41.8781, -87.6298);

        $this->assertEqualsWithDelta($a, $b, 0.001);
    }

    public function test_optimize_returns_stops_ordered_by_nearest_neighbor()
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Route 1',
            'code' => 'R-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $storeFar = RetailStoreFactory::new()->create([
            'latitude' => 42.3601,
            'longitude' => -87.6298,
        ]);

        $storeMedium = RetailStoreFactory::new()->create([
            'latitude' => 42.0000,
            'longitude' => -87.7000,
        ]);

        $storeNear = RetailStoreFactory::new()->create([
            'latitude' => 41.9000,
            'longitude' => -87.6500,
        ]);

        $stopFar = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $storeFar->id,
            'stop_order' => 1,
            'latitude' => $storeFar->latitude,
            'longitude' => $storeFar->longitude,
            'is_active' => true,
        ]);

        $stopMedium = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $storeMedium->id,
            'stop_order' => 2,
            'latitude' => $storeMedium->latitude,
            'longitude' => $storeMedium->longitude,
            'is_active' => true,
        ]);

        $stopNear = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $storeNear->id,
            'stop_order' => 3,
            'latitude' => $storeNear->latitude,
            'longitude' => $storeNear->longitude,
            'is_active' => true,
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertCount(3, $result);

        $orderedStopIds = $result->pluck('stop_id')->toArray();
        $this->assertEquals($stopNear->id, $orderedStopIds[0]);
        $this->assertEquals(1, $result[0]['stop_order']);
        $this->assertEquals(2, $result[1]['stop_order']);
        $this->assertEquals(3, $result[2]['stop_order']);
    }

    public function test_optimize_returns_empty_collection_for_no_stops()
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Empty Route',
            'code' => 'R-002',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_optimize_ignores_inactive_stops()
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Route 1',
            'code' => 'R-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $store = RetailStoreFactory::new()->create([
            'latitude' => 41.9000,
            'longitude' => -87.6500,
        ]);

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'is_active' => false,
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertTrue($result->isEmpty());
    }

    public function test_optimize_uses_stop_coordinates_when_available()
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Route 1',
            'code' => 'R-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $store = RetailStoreFactory::new()->create([
            'latitude' => 42.5000,
            'longitude' => -87.0000,
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'latitude' => 41.9000,
            'longitude' => -87.6500,
            'is_active' => true,
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertCount(1, $result);
        $this->assertEquals($stop->id, $result[0]['stop_id']);
    }

    public function test_optimize_returns_empty_for_nonexistent_route()
    {
        $result = $this->service->optimize(99999);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_calculate_total_distance_returns_zero_for_single_stop()
    {
        $stops = [
            ['lat' => 41.8781, 'lng' => -87.6298],
        ];

        $distance = $this->service->calculateTotalDistance($stops);

        $this->assertEquals(0.0, $distance);
    }

    public function test_calculate_total_distance_returns_zero_for_empty_array()
    {
        $stops = [];

        $distance = $this->service->calculateTotalDistance($stops);

        $this->assertEquals(0.0, $distance);
    }

    public function test_calculate_total_distance_sums_distances_between_consecutive_stops()
    {
        $stops = [
            ['lat' => 41.8781, 'lng' => -87.6298],
            ['lat' => 41.9000, 'lng' => -87.6500],
            ['lat' => 42.0000, 'lng' => -87.7000],
        ];

        $distance = $this->service->calculateTotalDistance($stops);

        $this->assertGreaterThan(0, $distance);

        $leg1 = $this->service->haversine(41.8781, -87.6298, 41.9000, -87.6500);
        $leg2 = $this->service->haversine(41.9000, -87.6500, 42.0000, -87.7000);

        $this->assertEqualsWithDelta($leg1 + $leg2, $distance, 0.001);
    }

    public function test_optimize_with_single_stop_returns_it()
    {
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'latitude' => 41.8781,
            'longitude' => -87.6298,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Route 1',
            'code' => 'R-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $store = RetailStoreFactory::new()->create([
            'latitude' => 41.9000,
            'longitude' => -87.6500,
        ]);

        $stop = RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'is_active' => true,
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertCount(1, $result);
        $this->assertEquals($stop->id, $result[0]['stop_id']);
    }

    public function test_optimize_with_warehouse_at_origin_zero_zero()
    {
        $warehouse = Warehouse::create([
            'name' => 'Origin',
            'code' => 'WH-000',
            'latitude' => 0,
            'longitude' => 0,
            'is_active' => true,
        ]);

        $route = Route::create([
            'name' => 'Route Zero',
            'code' => 'R-000',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $store = RetailStoreFactory::new()->create([
            'latitude' => 10,
            'longitude' => 10,
        ]);

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'is_active' => true,
        ]);

        $result = $this->service->optimize($route->id);

        $this->assertCount(1, $result);
    }
}
