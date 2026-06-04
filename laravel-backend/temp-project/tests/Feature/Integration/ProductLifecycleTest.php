<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RetailStore;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Services\FEFOService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private ProductCategory $category;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        User::resolveRelationUsing('retailStore', function ($user) {
            return $user->belongsTo(RetailStore::class, 'warehouse_id', 'warehouse_id');
        });

        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@distroflow.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->category = ProductCategory::create([
            'name' => 'Dairy',
            'slug' => 'dairy',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-001',
            'business_name' => 'Fresh Dairy Co',
            'status' => 'active',
        ]);
    }

    public function test_product_lifecycle_from_creation_to_depletion(): void
    {
        // STEP 1: Create product
        $product = Product::create([
            'name' => 'Fresh Milk 1L',
            'sku' => 'MLK-1000',
            'unit' => 'case',
            'cost_price' => 5.00,
            'selling_price' => 10.00,
            'category_id' => $this->category->id,
            'is_expiry_tracked' => true,
            'shelf_life_days' => 7,
            'min_stock_level' => 50,
            'max_stock_level' => 500,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'MLK-1000',
            'is_active' => true,
        ]);

        $availableStock = $product->available_stock;
        $this->assertEquals(0, $availableStock);

        // STEP 2: Receive stock via purchase order (creates batch)
        $purchaseOrder = PurchaseOrder::create([
            'order_number' => 'PO-001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 500.00,
            'total' => 500.00,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
            'unit_cost' => 5.00,
            'total_cost' => 500.00,
            'expiry_date' => now()->addWeek(),
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-MLK-001',
            'manufacturing_date' => now()->subDays(2),
            'expiry_date' => now()->addDays(5),
            'quantity' => 100,
            'available_quantity' => 100,
            'cost_price' => 5.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now(),
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'available',
        ]);

        $poItem->update(['quantity_received' => 100]);
        $purchaseOrder->update(['status' => 'received']);

        $product->refresh();
        $this->assertEqualsWithDelta(100, (float) $product->available_stock, 0.01);
        $this->assertEquals('received', $purchaseOrder->refresh()->status);

        // STEP 3: Pick product for order
        $store = RetailStore::create([
            'code' => 'STR-001',
            'business_name' => 'Test Store',
            'store_type' => 'grocery',
            'credit_limit' => 50000,
            'current_balance' => 0,
            'payment_terms' => 'cod',
            'status' => 'active',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $order = SalesOrder::create([
            'order_number' => 'ORD-001',
            'retail_store_id' => $store->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'approved',
            'source' => 'phone',
            'subtotal' => 500.00,
            'total' => 500.00,
            'balance_due' => 500.00,
        ]);

        $orderItem = OrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_ordered' => 50,
            'unit_price' => 10.00,
            'total_price' => 500.00,
            'status' => 'pending',
        ]);

        $fefo = app(FEFOService::class);
        $pickingBatches = $fefo->getPickingBatches($product->id, 50);

        $this->assertNotEmpty($pickingBatches);
        $this->assertEquals($batch->id, $pickingBatches->first()->batch_id);
        $this->assertEqualsWithDelta(50, (float) $pickingBatches->first()->quantity, 0.01);

        $orderItem->update([
            'quantity_picked' => 50,
            'status' => 'picked',
        ]);

        // STEP 4: Deliver product
        $batch->decrement('available_quantity', 50);
        if ($batch->available_quantity <= 0) {
            $batch->update(['status' => 'depleted']);
        }

        $orderItem->update([
            'quantity_delivered' => 50,
            'status' => 'delivered',
        ]);

        $product->refresh();
        $this->assertEqualsWithDelta(50, (float) $product->available_stock, 0.01);

        $batch->refresh();
        $this->assertEqualsWithDelta(50, (float) $batch->available_quantity, 0.01);
        $this->assertEquals('available', $batch->status);

        // STEP 5: Return damaged product
        $fefo->returnToBatch($batch->id, 5);

        $batch->refresh();
        $this->assertEqualsWithDelta(55, (float) $batch->available_quantity, 0.01);
        $this->assertEquals('available', $batch->status);

        $product->refresh();
        $this->assertEqualsWithDelta(55, (float) $product->available_stock, 0.01);

        // STEP 6: Verify depletion
        $orderItem2 = OrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity_ordered' => 55,
            'unit_price' => 10.00,
            'total_price' => 550.00,
            'status' => 'pending',
        ]);

        $pickingBatches2 = $fefo->getPickingBatches($product->id, 55);
        $totalPicked = $pickingBatches2->sum('quantity');
        $this->assertEqualsWithDelta(55, (float) $totalPicked, 0.01);

        foreach ($pickingBatches2 as $pick) {
            $fefo->consumeBatch($pick->batch_id, $pick->quantity);
        }

        $batch->refresh();
        $this->assertEqualsWithDelta(0, (float) $batch->available_quantity, 0.01);
        $this->assertEquals('depleted', $batch->status);

        $product->refresh();
        $this->assertEqualsWithDelta(0, (float) $product->available_stock, 0.01);
    }

    public function test_fefo_picks_from_correct_batch(): void
    {
        $product = Product::create([
            'name' => 'Yogurt',
            'sku' => 'YOG-001',
            'unit' => 'piece',
            'cost_price' => 2.00,
            'selling_price' => 4.00,
            'category_id' => $this->category->id,
            'is_expiry_tracked' => true,
            'shelf_life_days' => 14,
            'is_active' => true,
        ]);

        $batch1 = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-OLD',
            'expiry_date' => now()->addDays(3),
            'quantity' => 30,
            'available_quantity' => 30,
            'cost_price' => 2.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now()->subDays(10),
            'status' => 'available',
        ]);

        $batch2 = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-NEW',
            'expiry_date' => now()->addDays(14),
            'quantity' => 50,
            'available_quantity' => 50,
            'cost_price' => 2.50,
            'supplier_id' => $this->supplier->id,
            'received_date' => now()->subDays(2),
            'status' => 'available',
        ]);

        $fefo = app(FEFOService::class);
        $picks = $fefo->getPickingBatches($product->id, 40);

        $this->assertEquals($batch1->id, $picks->first()->batch_id);
        $this->assertEqualsWithDelta(30, (float) $picks->first()->quantity, 0.01);

        $this->assertEquals($batch2->id, $picks->values()->get(1)->batch_id);
        $this->assertEqualsWithDelta(10, (float) $picks->values()->get(1)->quantity, 0.01);
    }

    public function test_fefo_throws_on_insufficient_stock(): void
    {
        $product = Product::create([
            'name' => 'Cheese',
            'sku' => 'CHZ-001',
            'unit' => 'piece',
            'cost_price' => 8.00,
            'selling_price' => 15.00,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-LOW',
            'expiry_date' => now()->addMonth(),
            'quantity' => 5,
            'available_quantity' => 5,
            'cost_price' => 8.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $fefo = app(FEFOService::class);

        $this->expectException(\RuntimeException::class);
        $fefo->getPickingBatches($product->id, 10);
    }

    public function test_product_stock_quantities_through_purchase_order_receive(): void
    {
        $product = Product::create([
            'name' => 'Orange Juice',
            'sku' => 'OJ-001',
            'unit' => 'case',
            'cost_price' => 12.00,
            'selling_price' => 22.00,
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'order_number' => 'PO-002',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'order_date' => now(),
            'status' => 'pending',
            'subtotal' => 600.00,
            'total' => 600.00,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity_ordered' => 50,
            'quantity_received' => 0,
            'unit_cost' => 12.00,
            'total_cost' => 600.00,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BAT-OJ-001',
            'expiry_date' => now()->addMonths(2),
            'quantity' => 50,
            'available_quantity' => 50,
            'cost_price' => 12.00,
            'supplier_id' => $this->supplier->id,
            'received_date' => now(),
            'purchase_order_id' => $po->id,
            'status' => 'available',
        ]);

        $poItem->update(['quantity_received' => 50]);
        $po->update(['status' => 'received']);

        $product->refresh();
        $this->assertEqualsWithDelta(50, (float) $product->available_stock, 0.01);

        $batch->decrement('available_quantity', 30);
        $product->refresh();
        $this->assertEqualsWithDelta(20, (float) $product->available_stock, 0.01);

        $batch->increment('available_quantity', 5);
        $product->refresh();
        $this->assertEqualsWithDelta(25, (float) $product->available_stock, 0.01);
    }

    public function test_product_deactivation_prevents_stock(): void
    {
        $product = Product::create([
            'name' => 'Old Product',
            'sku' => 'OLD-001',
            'unit' => 'piece',
            'cost_price' => 1.00,
            'selling_price' => 2.00,
            'category_id' => $this->category->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?is_active=false');

        $response->assertStatus(200);
    }
}
