<?php

namespace Tests\Feature\Api;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    protected function createSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'code' => 'SUP-' . strtoupper(uniqid()),
            'business_name' => 'Test Supplier',
            'phone' => '1234567890',
            'email' => 'supplier@test.com',
            'status' => 'active',
        ], $overrides));
    }

    public function test_index_returns_paginated_suppliers(): void
    {
        $this->createSupplier(['business_name' => 'Supplier A']);
        $this->createSupplier(['business_name' => 'Supplier B']);

        $response = $this->getJson('/api/suppliers');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'business_name', 'code', 'status'],
                    ],
                    'current_page',
                    'last_page',
                ],
                'message',
            ]);
    }

    public function test_store_creates_supplier(): void
    {
        $response = $this->postJson('/api/suppliers', [
            'code' => 'SUP-NEW',
            'business_name' => 'New Supplier',
            'phone' => '9876543210',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'code' => 'SUP-NEW',
                'business_name' => 'New Supplier',
            ]);

        $this->assertDatabaseHas('suppliers', ['code' => 'SUP-NEW']);
    }

    public function test_show_returns_supplier(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $supplier->id,
                'business_name' => $supplier->business_name,
            ]);
    }

    public function test_update_modifies_supplier(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->putJson("/api/suppliers/{$supplier->id}", [
            'business_name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['business_name' => 'Updated Name']);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'business_name' => 'Updated Name',
        ]);
    }

    public function test_destroy_deactivates_supplier(): void
    {
        $supplier = $this->createSupplier();

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Supplier deactivated']);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => 'inactive',
        ]);
    }

    public function test_search_by_name(): void
    {
        $this->createSupplier(['business_name' => 'Acme Corp']);
        $this->createSupplier(['business_name' => 'Beta Inc']);

        $response = $this->getJson('/api/suppliers?search=Acme');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Acme Corp', $data[0]['business_name']);
    }

    public function test_search_by_code(): void
    {
        $this->createSupplier(['code' => 'SUP-XYZ', 'business_name' => 'XYZ Supplier']);
        $this->createSupplier(['code' => 'SUP-ABC', 'business_name' => 'ABC Supplier']);

        $response = $this->getJson('/api/suppliers?search=XYZ');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_search_by_phone(): void
    {
        $this->createSupplier(['phone' => '555-0101', 'business_name' => 'Phone Supplier']);
        $this->createSupplier(['phone' => '555-0202', 'business_name' => 'Other Supplier']);

        $response = $this->getJson('/api/suppliers?search=555-0101');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_delete_blocks_if_pending_pos_exist(): void
    {
        $warehouse = Warehouse::create(['name' => 'WH', 'code' => 'WH-01', 'is_active' => true]);
        $supplier = $this->createSupplier();

        PurchaseOrder::create([
            'order_number' => 'PO-001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'total' => 500,
        ]);

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'Cannot delete supplier with active purchase orders']);
    }

    public function test_404_for_missing_supplier(): void
    {
        $response = $this->getJson('/api/suppliers/99999');

        $response->assertStatus(404);
    }

    public function test_validation_errors_on_create(): void
    {
        $response = $this->postJson('/api/suppliers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'business_name', 'phone', 'status']);
    }

    public function test_unique_code_validation(): void
    {
        $this->createSupplier(['code' => 'SUP-DUP']);

        $response = $this->postJson('/api/suppliers', [
            'code' => 'SUP-DUP',
            'business_name' => 'Duplicate',
            'phone' => '1234567890',
            'status' => 'active',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_status_filter(): void
    {
        $this->createSupplier(['business_name' => 'Active Supplier', 'status' => 'active']);
        $this->createSupplier(['business_name' => 'Inactive Supplier', 'status' => 'inactive']);

        $response = $this->getJson('/api/suppliers?status=active');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('Active Supplier', $data[0]['business_name']);
    }
}
