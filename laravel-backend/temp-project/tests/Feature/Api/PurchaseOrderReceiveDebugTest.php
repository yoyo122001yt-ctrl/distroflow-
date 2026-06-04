<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReceiveDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug_receive(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $category = ProductCategory::create([
            'name' => 'Beverages',
            'slug' => 'beverages',
            'is_active' => true,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH01',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Test Supplier',
            'contact_person' => 'John',
            'phone' => '0123456789',
            'email' => 'supplier@test.com',
            'status' => 'active',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'sku' => 'SKU-TEST-001',
            'barcode' => '1234567890123',
            'cost_price' => 5.00,
            'selling_price' => 10.00,
            'unit' => 'piece',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'order_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'created_by' => $admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax' => 0,
            'total' => 100.00,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
            'unit_cost' => 5.00,
            'total_cost' => 500.00,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity' => 40,
                    ],
                ],
            ]);

        $this->assertEquals(200, $response->status(), $response->content());

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'partial',
        ]);
    }
}
