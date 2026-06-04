<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Truck;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\RouteAssignment;
use App\Models\RetailStore;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
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

    public function test_index_lists_routes(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH1', 'code' => 'WH01', 'is_active' => true,
        ]);

        Route::create(['code' => 'RTE-A', 'name' => 'Route A', 'warehouse_id' => $warehouse->id, 'status' => 'active']);
        Route::create(['code' => 'RTE-B', 'name' => 'Route B', 'warehouse_id' => $warehouse->id, 'status' => 'active']);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/routes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['data' => [['id', 'code', 'name', 'warehouse']], 'current_page', 'total'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Routes retrieved']);
    }

    public function test_store_creates_route(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Create', 'code' => 'WH02', 'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/routes', [
                'code' => 'RTE-NEW',
                'name' => 'New Route',
                'description' => 'Test description',
                'warehouse_id' => $warehouse->id,
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'code', 'name', 'warehouse'], 'message'])
            ->assertJsonFragment(['message' => 'Route created']);

        $this->assertDatabaseHas('routes', ['code' => 'RTE-NEW']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/routes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'warehouse_id', 'status']);
    }

    public function test_show_returns_route_with_stops(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Show', 'code' => 'WH03', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-SHOW', 'name' => 'Show Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $store = $this->createStore();

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/routes/{$route->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'code', 'name', 'warehouse', 'stops', 'assignments'], 'message'])
            ->assertJsonFragment(['message' => 'Route retrieved']);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/routes/9999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_route(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Update', 'code' => 'WH04', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-UPDATE', 'name' => 'Update Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/routes/{$route->id}", [
                'name' => 'Updated Route Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Route updated']);

        $this->assertDatabaseHas('routes', [
            'id' => $route->id,
            'name' => 'Updated Route Name',
        ]);
    }

    public function test_destroy_deletes_route_without_stops(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Delete', 'code' => 'WH05', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-DEL', 'name' => 'Delete Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/routes/{$route->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Route deleted']);

        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_destroy_rejects_route_with_stops(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Conflict', 'code' => 'WH06', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-CONFLICT', 'name' => 'Conflict Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $store = $this->createStore();

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/routes/{$route->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cannot delete route with existing stops']);
    }

    public function test_stops_lists_route_stops(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Stops', 'code' => 'WH07', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-STOPS', 'name' => 'Stops Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $store = $this->createStore();

        RouteStop::create([
            'route_id' => $route->id,
            'retail_store_id' => $store->id,
            'stop_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/routes/{$route->id}/stops");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'route_id', 'retail_store', 'stop_order']], 'message'])
            ->assertJsonFragment(['message' => 'Route stops retrieved']);
    }

    public function test_optimize_reorders_stops(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Opt', 'code' => 'WH08', 'is_active' => true,
            'latitude' => 30.0444,
            'longitude' => 31.2357,
        ]);

        $route = Route::create([
            'code' => 'RTE-OPT', 'name' => 'Opt Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $store1 = $this->createStore(['latitude' => 30.0500, 'longitude' => 31.2400]);
        $store2 = $this->createStore(['latitude' => 30.0600, 'longitude' => 31.2500]);

        RouteStop::create([
            'route_id' => $route->id, 'retail_store_id' => $store1->id, 'stop_order' => 2, 'is_active' => true,
        ]);
        RouteStop::create([
            'route_id' => $route->id, 'retail_store_id' => $store2->id, 'stop_order' => 1, 'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/routes/{$route->id}/optimize");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'route_id', 'stop_order']], 'message'])
            ->assertJsonFragment(['message' => 'Route optimized successfully']);
    }

    public function test_assign_assigns_driver_and_truck(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Assign', 'code' => 'WH09', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-ASGN', 'name' => 'Assign Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $driver = $this->createUser([
            'name' => 'Test Driver', 'email' => 'testdriver@test.com', 'role' => 'driver',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'status' => 'available',
        ]);

        $truck = Truck::create([
            'code' => 'TRK-ASGN',
            'plate_number' => 'ASGN-123',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/routes/{$route->id}/assign", [
                'driver_id' => $driver->id,
                'truck_id' => $truck->id,
                'assignment_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'route', 'driver', 'truck'], 'message'])
            ->assertJsonFragment(['message' => 'Driver and truck assigned to route']);

        $this->assertDatabaseHas('route_assignments', [
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
        ]);
    }

    public function test_assign_rejects_non_driver_user(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Reject', 'code' => 'WH10', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-REJ', 'name' => 'Reject Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $nonDriver = $this->createUser([
            'name' => 'Not Driver', 'email' => 'notdriver@test.com', 'role' => 'admin',
        ]);

        $truck = Truck::create([
            'code' => 'TRK-REJ',
            'plate_number' => 'REJ-123',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/routes/{$route->id}/assign", [
                'driver_id' => $nonDriver->id,
                'truck_id' => $truck->id,
                'assignment_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Selected user is not a driver']);
    }

    public function test_manifest_generates_route_manifest(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'WH Manifest', 'code' => 'WH11', 'is_active' => true,
        ]);

        $route = Route::create([
            'code' => 'RTE-MANF', 'name' => 'Manifest Route', 'warehouse_id' => $warehouse->id, 'status' => 'active',
        ]);

        $store = $this->createStore();
        RouteStop::create([
            'route_id' => $route->id, 'retail_store_id' => $store->id, 'stop_order' => 1, 'is_active' => true,
        ]);

        $driver = $this->createUser([
            'name' => 'Manifest Driver', 'email' => 'manifestdriver@test.com', 'role' => 'driver',
        ]);

        $truck = Truck::create([
            'code' => 'TRK-MANF',
            'plate_number' => 'MANF-123',
            'status' => 'available',
            'is_active' => true,
        ]);

        RouteAssignment::create([
            'route_id' => $route->id,
            'driver_id' => $driver->id,
            'truck_id' => $truck->id,
            'assignment_date' => now(),
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/routes/{$route->id}/manifest");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['route', 'driver', 'truck', 'warehouse', 'stops', 'total_stops', 'generated_at'],
                'message',
            ])
            ->assertJsonFragment(['message' => 'Route manifest generated']);
    }
}
