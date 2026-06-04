<?php

namespace Tests\Feature\BusinessLogic;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\FEFOService;
use Tests\TestCase;

class FEFOTest extends TestCase
{
    private FEFOService $fefoService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fefoService = app(FEFOService::class);
    }

    public function test_picks_from_earliest_expiry_batch_first(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TST-FEFO-001',
            'selling_price' => 10.00,
            'cost_price' => 5.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-FEFO',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-A-EARLY',
            'expiry_date' => now()->addDays(10),
            'quantity' => 50,
            'available_quantity' => 50,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-B-LATE',
            'expiry_date' => now()->addDays(30),
            'quantity' => 30,
            'available_quantity' => 30,
            'cost_price' => 6.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $picked = $this->fefoService->getPickingBatches($product->id, 30);

        $this->assertCount(1, $picked);
        $this->assertEquals('BATCH-A-EARLY', $picked->first()->batch->batch_number);
        $this->assertEquals(30, $picked->first()->quantity);
    }

    public function test_consumes_remaining_from_next_batch_when_first_is_partially_depleted(): void
    {
        $product = Product::create([
            'name' => 'Test Product',
            'sku' => 'TST-FEFO-002',
            'selling_price' => 10.00,
            'cost_price' => 5.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-FEFO-2',
        ]);

        $batchA = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-A',
            'expiry_date' => now()->addDays(10),
            'quantity' => 50,
            'available_quantity' => 50,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-B',
            'expiry_date' => now()->addDays(30),
            'quantity' => 30,
            'available_quantity' => 30,
            'cost_price' => 6.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->fefoService->consumeBatch($batchA->id, 20);

        $picked = $this->fefoService->getPickingBatches($product->id, 40);

        $this->assertCount(2, $picked);
        $this->assertEquals('BATCH-A', $picked[0]->batch->batch_number);
        $this->assertEquals(30, $picked[0]->quantity);
        $this->assertEquals('BATCH-B', $picked[1]->batch->batch_number);
        $this->assertEquals(10, $picked[1]->quantity);
    }

    public function test_consume_batch_decrements_and_sets_depleted(): void
    {
        $product = Product::create([
            'name' => 'Deplete Test',
            'sku' => 'TST-DEPLETE',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-DEPLETE',
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-DEPLETE',
            'expiry_date' => now()->addDays(5),
            'quantity' => 10,
            'available_quantity' => 10,
            'cost_price' => 4.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->fefoService->consumeBatch($batch->id, 10);

        $batch->refresh();

        $this->assertEquals(0, (float) $batch->available_quantity);
        $this->assertEquals('depleted', $batch->status);
    }

    public function test_return_to_batch_restores_quantity_and_status(): void
    {
        $product = Product::create([
            'name' => 'Return Test',
            'sku' => 'TST-RETURN',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-RETURN',
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-RETURN',
            'expiry_date' => now()->addDays(5),
            'quantity' => 10,
            'available_quantity' => 10,
            'cost_price' => 4.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->fefoService->consumeBatch($batch->id, 10);
        $this->fefoService->returnToBatch($batch->id, 5);

        $batch->refresh();

        $this->assertEquals(5, (float) $batch->available_quantity);
        $this->assertEquals('available', $batch->status);
    }

    public function test_insufficient_stock_throws_runtime_exception_with_product_info(): void
    {
        $product = Product::create([
            'name' => 'Low Stock Item',
            'sku' => 'LOW-SKU-001',
            'selling_price' => 10.00,
            'cost_price' => 3.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-LOW',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-LOW',
            'expiry_date' => now()->addDays(10),
            'quantity' => 5,
            'available_quantity' => 5,
            'cost_price' => 3.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Low Stock Item');
        $this->expectExceptionMessage('LOW-SKU-001');

        $this->fefoService->getPickingBatches($product->id, 100);
    }

    public function test_consume_batch_throws_on_insufficient_quantity(): void
    {
        $product = Product::create([
            'name' => 'Insufficient Test',
            'sku' => 'TST-INSUFF',
            'selling_price' => 10.00,
            'cost_price' => 3.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-INSUFF',
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-INSUFF',
            'expiry_date' => now()->addDays(10),
            'quantity' => 5,
            'available_quantity' => 5,
            'cost_price' => 3.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient quantity in batch');

        $this->fefoService->consumeBatch($batch->id, 99);
    }

    public function test_get_expiring_batches_returns_only_batches_within_day_range(): void
    {
        $product = Product::create([
            'name' => 'Expiry Range Test',
            'sku' => 'TST-EXP-RNG',
            'selling_price' => 10.00,
            'cost_price' => 5.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-EXP-RNG',
        ]);

        $nearExpiry = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-NEAR',
            'expiry_date' => now()->addDays(5),
            'quantity' => 20,
            'available_quantity' => 20,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-FAR',
            'expiry_date' => now()->addDays(60),
            'quantity' => 20,
            'available_quantity' => 20,
            'cost_price' => 5.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $within30 = $this->fefoService->getExpiringBatches(30);

        $this->assertTrue($within30->contains('id', $nearExpiry->id));
        $this->assertCount(1, $within30);

        $within90 = $this->fefoService->getExpiringBatches(90);

        $this->assertCount(2, $within90);
    }

    public function test_get_expired_batches_returns_only_expired(): void
    {
        $product = Product::create([
            'name' => 'Expired Test',
            'sku' => 'TST-EXPIRED',
            'selling_price' => 10.00,
            'cost_price' => 4.00,
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH-EXPD',
        ]);

        $expired = Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-EXPIRED',
            'expiry_date' => now()->subDay(),
            'quantity' => 15,
            'available_quantity' => 15,
            'cost_price' => 4.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-FRESH',
            'expiry_date' => now()->addDay(),
            'quantity' => 15,
            'available_quantity' => 15,
            'cost_price' => 4.00,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $expiredBatches = $this->fefoService->getExpiredBatches();

        $this->assertTrue($expiredBatches->contains('id', $expired->id));
        $this->assertCount(1, $expiredBatches);
    }
}
