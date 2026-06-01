<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RouteAssignment;
use App\Models\User;
use App\Models\Truck;
use App\Models\RetailStore;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoRoutesSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $driver1 = User::where('email', 'driver1@distroflow.com')->first();
        $driver2 = User::where('email', 'driver2@distroflow.com')->first();
        $truck1 = Truck::where('code', 'TRK-001')->first();
        $truck2 = Truck::where('code', 'TRK-002')->first();

        // Route 1: North Side
        $route1 = Route::create([
            'code' => 'RTE-NORTH',
            'name' => 'North Side Route',
            'description' => 'Serves northern Chicago suburbs',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $northStores = RetailStore::whereIn('code', ['STR-003', 'STR-006', 'STR-007', 'STR-010'])->get();
        $order = 1;
        foreach ($northStores as $store) {
            RouteStop::create([
                'route_id' => $route1->id,
                'retail_store_id' => $store->id,
                'stop_order' => $order++,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'is_active' => true,
            ]);
        }

        // Route 2: South Side
        $route2 = Route::create([
            'code' => 'RTE-SOUTH',
            'name' => 'South Side Route',
            'description' => 'Serves southern Chicago suburbs',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $southStores = RetailStore::whereIn('code', ['STR-001', 'STR-004', 'STR-008', 'STR-009'])->get();
        $order = 1;
        foreach ($southStores as $store) {
            RouteStop::create([
                'route_id' => $route2->id,
                'retail_store_id' => $store->id,
                'stop_order' => $order++,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'is_active' => true,
            ]);
        }

        // Route 3: Downtown
        $route3 = Route::create([
            'code' => 'RTE-DOWNTOWN',
            'name' => 'Downtown Route',
            'description' => 'Serves downtown Chicago',
            'warehouse_id' => $warehouse->id,
            'status' => 'active',
        ]);

        $downtownStores = RetailStore::whereIn('code', ['STR-002', 'STR-005'])->get();
        $order = 1;
        foreach ($downtownStores as $store) {
            RouteStop::create([
                'route_id' => $route3->id,
                'retail_store_id' => $store->id,
                'stop_order' => $order++,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'is_active' => true,
            ]);
        }

        // Create today's assignments
        $truck1->update(['status' => 'assigned']);
        $truck2->update(['status' => 'assigned']);

        RouteAssignment::create([
            'route_id' => $route1->id,
            'driver_id' => $driver1->id,
            'truck_id' => $truck1->id,
            'assignment_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        RouteAssignment::create([
            'route_id' => $route2->id,
            'driver_id' => $driver2->id,
            'truck_id' => $truck2->id,
            'assignment_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->command->info('3 routes created with stops and assignments.');
    }
}
