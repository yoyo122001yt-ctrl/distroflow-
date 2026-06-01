<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Delivery;
use App\Models\DeliveryStop;
use App\Models\RetailStore;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteStop;
use App\Models\Truck;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DriverDemoSeeder extends Seeder
{
    public function run(): void
    {
        $drivers = [
            ['name' => 'Ahmed', 'email' => 'driver1@distroflow.com', 'phone' => '0555000001'],
            ['name' => 'Karim', 'email' => 'driver2@distroflow.com', 'phone' => '0555000002'],
        ];

        $trucks = Truck::all();
        $routes = Route::all();

        foreach ($drivers as $i => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'),
                    'role' => 'driver',
                    'phone' => $data['phone'],
                    'is_active' => true,
                    'warehouse_id' => 1,
                ]
            );

            DriverProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_number' => 'LIC-' . strtoupper(Str::random(8)),
                    'license_expiry' => now()->addYear(),
                    'emergency_contact' => '0555000000',
                    'emergency_phone' => '0555000000',
                    'status' => 'available',
                ]
            );

            $existingDelivery = Delivery::forDriver($user->id)->today()->first();
            if ($existingDelivery) continue;

            $truck = $trucks->get($i % max($trucks->count(), 1));
            $route = $routes->get($i % max($routes->count(), 1));

            if (!$truck || !$route) continue;

            $routeStops = RouteStop::where('route_id', $route->id)->orderBy('stop_order')->take(3)->get();

            if ($routeStops->isEmpty()) {
                $stores = RetailStore::inRandomOrder()->limit(3)->get();
                foreach ($stores as $j => $s) {
                    $rs = RouteStop::create([
                        'route_id' => $route->id,
                        'retail_store_id' => $s->id,
                        'stop_order' => $j + 1,
                    ]);
                    $routeStops->push($rs);
                }
            }

            $routeAssignment = RouteAssignment::updateOrCreate(
                ['route_id' => $route->id, 'assignment_date' => today()],
                ['driver_id' => $user->id, 'truck_id' => $truck->id, 'status' => 'active']
            );

            $delivery = Delivery::create([
                'delivery_number' => 'DEL-DEMO-' . strtoupper(Str::random(6)),
                'route_assignment_id' => $routeAssignment->id,
                'driver_id' => $user->id,
                'truck_id' => $truck->id,
                'delivery_date' => today(),
                'status' => 'pending',
                'tracking_token' => Str::random(32),
            ]);

            foreach ($routeStops as $rs) {
                DeliveryStop::create([
                    'delivery_id' => $delivery->id,
                    'route_stop_id' => $rs->id,
                    'retail_store_id' => $rs->retail_store_id,
                    'stop_order' => $rs->stop_order,
                    'status' => 'pending',
                ]);
            }
        }

        $this->command->info('Demo drivers and deliveries created successfully.');
    }
}
