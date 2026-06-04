<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\DriverProfile;
use App\Models\DriverTrip;
use App\Models\DriverLocation;
use App\Models\Delivery;
use App\Models\Truck;
use App\Models\Route;
use App\Models\RouteAssignment;
use Database\Factories\UserFactory;

class UserTest extends TestCase
{
    public function test_is_driver_returns_true_when_role_is_driver(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);

        $this->assertTrue($user->isDriver());
    }

    public function test_is_driver_returns_false_for_non_driver_roles(): void
    {
        $admin = UserFactory::new()->create(['role' => 'admin']);
        $manager = UserFactory::new()->create(['role' => 'manager']);
        $viewer = UserFactory::new()->create(['role' => 'viewer']);

        $this->assertFalse($admin->isDriver());
        $this->assertFalse($manager->isDriver());
        $this->assertFalse($viewer->isDriver());
    }

    public function test_has_role_returns_true_for_single_matching_role(): void
    {
        $user = UserFactory::new()->create(['role' => 'admin']);

        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_has_role_returns_false_for_non_matching_role(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);

        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_has_role_returns_true_when_role_is_in_array(): void
    {
        $user = UserFactory::new()->create(['role' => 'manager']);

        $this->assertTrue($user->hasRole(['admin', 'manager', 'supervisor']));
    }

    public function test_has_role_returns_false_when_role_is_not_in_array(): void
    {
        $user = UserFactory::new()->create(['role' => 'viewer']);

        $this->assertFalse($user->hasRole(['admin', 'manager']));
    }

    public function test_scope_drivers_filters_only_driver_users(): void
    {
        UserFactory::new()->create(['role' => 'admin']);
        UserFactory::new()->create(['role' => 'manager']);
        $driver = UserFactory::new()->create(['role' => 'driver']);
        UserFactory::new()->create(['role' => 'viewer']);

        $drivers = User::drivers()->get();

        $this->assertCount(1, $drivers);
        $this->assertEquals($driver->id, $drivers->first()->id);
    }

    public function test_warehouse_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'Main WH', 'code' => 'WH-01']);
        $user = UserFactory::new()->create(['warehouse_id' => $warehouse->id]);

        $this->assertInstanceOf(Warehouse::class, $user->warehouse);
        $this->assertEquals($warehouse->id, $user->warehouse->id);
    }

    public function test_driver_profile_relationship(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);
        $profile = DriverProfile::create([
            'user_id' => $user->id,
            'license_number' => 'LIC-001',
            'status' => 'available',
        ]);

        $this->assertInstanceOf(DriverProfile::class, $user->driverProfile);
        $this->assertEquals($profile->id, $user->driverProfile->id);
    }

    public function test_driver_trips_relationship(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);

        DriverTrip::create(['driver_id' => $user->id, 'status' => 'completed', 'started_at' => now()]);
        DriverTrip::create(['driver_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->assertCount(2, $user->driverTrips);
    }

    public function test_active_trip_relationship(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);

        DriverTrip::create(['driver_id' => $user->id, 'status' => 'completed', 'started_at' => now()]);
        $activeTrip = DriverTrip::create(['driver_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        $this->assertInstanceOf(DriverTrip::class, $user->activeTrip);
        $this->assertEquals($activeTrip->id, $user->activeTrip->id);
    }

    public function test_driver_locations_relationship(): void
    {
        $user = UserFactory::new()->create(['role' => 'driver']);
        $trip = DriverTrip::create(['driver_id' => $user->id, 'status' => 'active', 'started_at' => now()]);

        DriverLocation::create(['driver_id' => $user->id, 'trip_id' => $trip->id, 'latitude' => 30.0, 'longitude' => 31.0, 'recorded_at' => now()]);
        DriverLocation::create(['driver_id' => $user->id, 'trip_id' => $trip->id, 'latitude' => 30.1, 'longitude' => 31.1, 'recorded_at' => now()]);

        $this->assertCount(2, $user->driverLocations);
    }

    public function test_deliveries_relationship(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01']);
        $user = UserFactory::new()->create(['role' => 'driver']);
        $truck = Truck::create(['code' => 'TRK-001', 'plate_number' => 'ABC-123', 'status' => 'available']);
        $route = Route::create(['code' => 'R-001', 'name' => 'Route 1', 'warehouse_id' => $warehouse->id]);
        $routeAssignment = RouteAssignment::create(['route_id' => $route->id, 'driver_id' => $user->id, 'truck_id' => $truck->id, 'assignment_date' => today()]);

        Delivery::create([
            'delivery_number' => 'DEL-001',
            'route_assignment_id' => $routeAssignment->id,
            'driver_id' => $user->id,
            'truck_id' => $truck->id,
            'delivery_date' => today(),
        ]);

        $this->assertCount(1, $user->deliveries);
    }
}
