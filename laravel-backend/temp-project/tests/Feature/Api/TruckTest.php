<?php

namespace Tests\Feature\Api;

use App\Models\Truck;
use App\Models\TruckInventory;
use App\Models\Product;
use App\Models\Batch;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TruckTest extends TestCase
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

        Sanctum::actingAs($this->admin);
    }

    protected function createTruck(array $overrides = []): Truck
    {
        return Truck::create(array_merge([
            'code' => 'TRK-' . strtoupper(uniqid()),
            'plate_number' => strtoupper(uniqid()),
            'model' => 'Isuzu NPR',
            'year' => 2023,
            'capacity_weight' => 5000,
            'capacity_volume' => 20,
            'status' => 'available',
            'is_active' => true,
        ], $overrides));
    }

    public function test_index_returns_paginated_trucks(): void
    {
        $this->createTruck(['code' => 'TRK-001']);
        $this->createTruck(['code' => 'TRK-002']);

        $response = $this->getJson('/api/trucks');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'code', 'plate_number', 'status'],
                    ],
                ],
                'message',
            ]);
    }

    public function test_store_creates_truck(): void
    {
        $response = $this->postJson('/api/trucks', [
            'code' => 'TRK-NEW',
            'plate_number' => 'NEW-PLATE',
            'model' => 'Ford Transit',
            'year' => 2024,
            'capacity_weight' => 3000,
            'capacity_volume' => 15,
            'status' => 'available',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'code' => 'TRK-NEW',
                'plate_number' => 'NEW-PLATE',
            ]);

        $this->assertDatabaseHas('trucks', ['code' => 'TRK-NEW']);
    }

    public function test_show_returns_truck(): void
    {
        $truck = $this->createTruck();

        $response = $this->getJson("/api/trucks/{$truck->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $truck->id]);
    }

    public function test_update_modifies_truck(): void
    {
        $truck = $this->createTruck();

        $response = $this->putJson("/api/trucks/{$truck->id}", [
            'model' => 'Updated Model',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['model' => 'Updated Model']);

        $this->assertDatabaseHas('trucks', [
            'id' => $truck->id,
            'model' => 'Updated Model',
        ]);
    }

    public function test_destroy_deactivates_truck(): void
    {
        $truck = $this->createTruck();

        $response = $this->deleteJson("/api/trucks/{$truck->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Truck deactivated']);

        $this->assertDatabaseHas('trucks', [
            'id' => $truck->id,
            'is_active' => false,
        ]);
    }

    public function test_unique_code_validation(): void
    {
        $this->createTruck(['code' => 'TRK-DUP']);

        $response = $this->postJson('/api/trucks', [
            'code' => 'TRK-DUP',
            'plate_number' => 'UNIQUE-PLATE',
            'model' => 'Test',
            'status' => 'available',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_unique_plate_number_validation(): void
    {
        $this->createTruck(['plate_number' => 'DUP-PLATE']);

        $response = $this->postJson('/api/trucks', [
            'code' => 'TRK-UNIQUE',
            'plate_number' => 'DUP-PLATE',
            'model' => 'Test',
            'status' => 'available',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    public function test_delete_blocks_with_inventory_greater_than_zero(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01', 'is_active' => true]);
        $truck = $this->createTruck();
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'SKU-INV',
            'cost_price' => 10,
            'selling_price' => 15,
            'is_active' => true,
        ]);
        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-001',
            'expiry_date' => now()->addYear(),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 10,
            'received_date' => now(),
            'status' => 'available',
        ]);

        TruckInventory::create([
            'truck_id' => $truck->id,
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => 50,
            'starting_quantity' => 50,
        ]);

        $response = $this->deleteJson("/api/trucks/{$truck->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cannot delete truck with inventory']);
    }

    public function test_search_by_code(): void
    {
        $this->createTruck(['code' => 'TRK-ALPHA', 'plate_number' => 'X001']);
        $this->createTruck(['code' => 'TRK-BETA', 'plate_number' => 'X002']);

        $response = $this->getJson('/api/trucks?search=ALPHA');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('TRK-ALPHA', $data[0]['code']);
    }

    public function test_search_by_plate_number(): void
    {
        $this->createTruck(['code' => 'TRK-01', 'plate_number' => 'ABC-1234']);
        $this->createTruck(['code' => 'TRK-02', 'plate_number' => 'XYZ-5678']);

        $response = $this->getJson('/api/trucks?search=ABC-1234');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_search_by_model(): void
    {
        $this->createTruck(['code' => 'TRK-03', 'plate_number' => 'M001', 'model' => 'Isuzu NPR']);
        $this->createTruck(['code' => 'TRK-04', 'plate_number' => 'M002', 'model' => 'Ford Transit']);

        $response = $this->getJson('/api/trucks?search=Isuzu');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_status_filter(): void
    {
        $this->createTruck(['code' => 'TRK-S1', 'plate_number' => 'S001', 'status' => 'available']);
        $this->createTruck(['code' => 'TRK-S2', 'plate_number' => 'S002', 'status' => 'maintenance']);

        $response = $this->getJson('/api/trucks?status=maintenance');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('maintenance', $data[0]['status']);
    }

    public function test_validation_errors_on_create(): void
    {
        $response = $this->postJson('/api/trucks', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'plate_number', 'model', 'status']);
    }

    public function test_404_for_missing_truck(): void
    {
        $this->getJson('/api/trucks/99999')->assertStatus(404);
    }

    public function test_is_active_filter(): void
    {
        $this->createTruck(['code' => 'TRK-A1', 'plate_number' => 'A001', 'is_active' => true]);
        $this->createTruck(['code' => 'TRK-A2', 'plate_number' => 'A002', 'is_active' => false]);

        $response = $this->getJson('/api/trucks?is_active=false');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }
}
