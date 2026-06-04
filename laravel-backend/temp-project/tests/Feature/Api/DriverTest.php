<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\DriverSettlement;
use App\Models\Warehouse;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\Delivery;
use App\Models\Truck;
use App\Models\RetailStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = new User;
        $this->adminUser->name = 'Admin';
        $this->adminUser->email = 'admin@test.com';
        $this->adminUser->password = bcrypt('password');
        $this->adminUser->role = 'admin';
        $this->adminUser->save();
    }

    private function createUser(array $attrs): User
    {
        $user = new User;
        $user->name = $attrs['name'];
        $user->email = $attrs['email'];
        $user->password = bcrypt('password');
        $user->role = $attrs['role'];
        $user->is_active = $attrs['is_active'] ?? true;
        $user->save();
        return $user;
    }

    private function createStore(array $overrides = []): RetailStore
    {
        $store = new RetailStore;
        $store->code = $overrides['code'] ?? 'STR-' . mt_rand(1000, 9999);
        $store->business_name = $overrides['business_name'] ?? 'Test Store';
        $store->store_type = $overrides['store_type'] ?? 'grocery';
        $store->contact_person = $overrides['contact_person'] ?? 'John';
        $store->phone = $overrides['phone'] ?? '0123456789';
        $store->email = $overrides['email'] ?? 'store@test.com';
        $store->address = $overrides['address'] ?? '123 Test St';
        $store->city = $overrides['city'] ?? 'Test City';
        $store->state = $overrides['state'] ?? 'Test State';
        $store->latitude = $overrides['latitude'] ?? 30.0444;
        $store->longitude = $overrides['longitude'] ?? 31.2357;
        $store->credit_limit = $overrides['credit_limit'] ?? 50000;
        $store->current_balance = $overrides['current_balance'] ?? 0;
        $store->payment_terms = $overrides['payment_terms'] ?? 'net_30';
        $store->status = $overrides['status'] ?? 'active';
        $store->save();
        return $store;
    }

    public function test_index_lists_drivers(): void
    {
        $this->createUser(['name' => 'Driver One', 'email' => 'driver1@test.com', 'role' => 'driver']);
        $this->createUser(['name' => 'Driver Two', 'email' => 'driver2@test.com', 'role' => 'driver']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/drivers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'name', 'email', 'role', 'driver_profile']], 'current_page', 'total'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Drivers retrieved']);
    }

    public function test_index_excludes_non_drivers(): void
    {
        $this->createUser(['name' => 'Admin User', 'email' => 'admin2@test.com', 'role' => 'admin']);
        $this->createUser(['name' => 'Driver Only', 'email' => 'driveronly@test.com', 'role' => 'driver']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/drivers');

        $response->assertStatus(200);
        $driverNames = collect($response->json('data.data'))->pluck('name');
        $this->assertContains('Driver Only', $driverNames);
        $this->assertNotContains('Admin User', $driverNames);
    }

    public function test_store_creates_driver_with_profile(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/drivers', [
                'name' => 'New Driver',
                'email' => 'newdriver@test.com',
                'phone' => '0123456789',
                'license_number' => 'LIC-12345',
                'vehicle_plate' => 'ABC-5678',
                'pay_rate' => 150.00,
                'pay_type' => 'per_delivery',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'driver_profile'], 'message'])
            ->assertJsonFragment(['message' => 'Driver created']);

        $this->assertDatabaseHas('users', [
            'email' => 'newdriver@test.com',
            'role' => 'driver',
        ]);

        $user = User::where('email', 'newdriver@test.com')->first();
        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $user->id,
            'license_number' => 'LIC-12345',
            'vehicle_plate' => 'ABC-5678',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/drivers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'status']);
    }

    public function test_show_returns_driver(): void
    {
        $driver = $this->createUser([
            'name' => 'Show Driver', 'email' => 'showdriver@test.com', 'role' => 'driver',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'license_number' => 'LIC-SHOW',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'driver_profile'], 'message'])
            ->assertJsonFragment(['message' => 'Driver retrieved']);
    }

    public function test_show_returns_404_for_non_driver(): void
    {
        $nonDriver = $this->createUser([
            'name' => 'Not Driver', 'email' => 'notdriver@test.com', 'role' => 'admin',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/drivers/{$nonDriver->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_driver_and_profile(): void
    {
        $driver = $this->createUser([
            'name' => 'Update Driver', 'email' => 'updatedriver@test.com', 'role' => 'driver',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'license_number' => 'LIC-OLD',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/drivers/{$driver->id}", [
                'name' => 'Updated Driver Name',
                'phone' => '0999999999',
                'license_number' => 'LIC-NEW',
                'pay_rate' => 200.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Driver updated']);

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'name' => 'Updated Driver Name',
            'phone' => '0999999999',
        ]);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $driver->id,
            'license_number' => 'LIC-NEW',
            'pay_rate' => 200.00,
        ]);
    }

    public function test_destroy_deactivates_driver(): void
    {
        $driver = $this->createUser([
            'name' => 'Delete Driver', 'email' => 'deletedriver@test.com', 'role' => 'driver',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/drivers/{$driver->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Driver deactivated']);

        $this->assertDatabaseHas('users', [
            'id' => $driver->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('driver_profiles', [
            'user_id' => $driver->id,
            'status' => 'terminated',
        ]);
    }

    public function test_settlements_returns_driver_settlements(): void
    {
        $driver = $this->createUser([
            'name' => 'Settlement Driver', 'email' => 'settlementdriver@test.com', 'role' => 'driver',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'WH Settlement', 'code' => 'WH-SET', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-SET', 'name' => 'Settlement Route',
            'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $truck = Truck::create([
            'code' => 'TRK-SET', 'plate_number' => 'SET-123',
            'status' => 'available', 'is_active' => true,
        ]);

        $routeAssignment = RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => now(),
            'status' => 'scheduled',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-SET-001',
            'route_assignment_id' => $routeAssignment->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'delivery_date' => now(),
            'status' => 'completed',
        ]);

        DriverSettlement::create([
            'driver_id' => $driver->id,
            'route_assignment_id' => $routeAssignment->id,
            'delivery_id' => $delivery->id,
            'settlement_date' => now(),
            'status' => 'pending',
            'total_sales' => 500.00,
            'expected_cash' => 500.00,
            'actual_cash' => 500.00,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/drivers/{$driver->id}/settlements");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['data' => [['id', 'driver_id', 'status', 'total_sales']], 'current_page', 'total'], 'message'])
            ->assertJsonFragment(['message' => 'Driver settlements retrieved']);
    }
}
