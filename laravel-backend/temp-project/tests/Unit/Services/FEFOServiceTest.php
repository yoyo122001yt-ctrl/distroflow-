<?php

namespace Tests\Unit\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\FEFOService;
use Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FEFOServiceTest extends TestCase
{
    use RefreshDatabase;

    private FEFOService $service;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FEFOService::class);
        $this->warehouse = Warehouse::create([
            'name' => 'Main WH', 'code' => 'WH-001',
            'latitude' => 0, 'longitude' => 0, 'is_active' => true,
        ]);
        $this->product = ProductFactory::new()->create();
    }

    public function test_get_picking_batches_returns_earliest_expiry_first()
    {
        $batch1 = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 50,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $batch2 = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-002',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 30,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $batch3 = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-003',
            'expiry_date' => now()->addDays(20),
            'quantity' => 100,
            'available_quantity' => 20,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getPickingBatches($this->product->id, 40);

        $this->assertCount(2, $result);
        $this->assertEquals($batch2->id, $result[0]->batch_id);
        $this->assertEquals($batch3->id, $result[1]->batch_id);
        $this->assertEquals(30, $result[0]->quantity);
        $this->assertEquals(10, $result[1]->quantity);
    }

    public function test_get_picking_batches_handles_partial_batch_consumption()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 20,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getPickingBatches($this->product->id, 15);

        $this->assertCount(1, $result);
        $this->assertEquals(15, $result[0]->quantity);
    }

    public function test_get_picking_batches_throws_runtime_exception_when_insufficient_stock()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 5,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->getPickingBatches($this->product->id, 50);
    }

    public function test_get_picking_batches_skips_depleted_and_expired_batches()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-depleted',
            'expiry_date' => now()->addDays(5),
            'quantity' => 100,
            'available_quantity' => 0,
            'received_date' => now(),
            'status' => 'depleted',
        ]);

        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-expired',
            'expiry_date' => now()->subDays(1),
            'quantity' => 100,
            'available_quantity' => 10,
            'received_date' => now(),
            'status' => 'expired',
        ]);

        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-good',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 10,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getPickingBatches($this->product->id, 5);

        $this->assertCount(1, $result);
        $this->assertEquals($batch->id, $result[0]->batch_id);
    }

    public function test_get_expiring_batches_returns_batches_expiring_within_days()
    {
        $expiring = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-expiring',
            'expiry_date' => now()->addDays(5),
            'quantity' => 100,
            'available_quantity' => 50,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-far',
            'expiry_date' => now()->addDays(60),
            'quantity' => 100,
            'available_quantity' => 50,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getExpiringBatches(10);

        $this->assertCount(1, $result);
        $this->assertEquals($expiring->id, $result[0]->id);
    }

    public function test_get_expiring_batches_excludes_expired_batches()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-expired',
            'expiry_date' => now()->subDays(1),
            'quantity' => 100,
            'available_quantity' => 10,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getExpiringBatches(30);

        $this->assertCount(0, $result);
    }

    public function test_get_expiring_batches_returns_empty_when_none_expiring()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-far',
            'expiry_date' => now()->addDays(100),
            'quantity' => 100,
            'available_quantity' => 50,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getExpiringBatches(5);

        $this->assertCount(0, $result);
    }

    public function test_get_expired_batches_returns_only_past_expiry_batches()
    {
        $expired1 = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-expired-1',
            'expiry_date' => now()->subDays(5),
            'quantity' => 100,
            'available_quantity' => 20,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $expired2 = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-expired-2',
            'expiry_date' => now()->subDays(1),
            'quantity' => 100,
            'available_quantity' => 10,
            'received_date' => now(),
            'status' => 'available',
        ]);

        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-future',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 30,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getExpiredBatches();

        $this->assertCount(2, $result);
        $this->assertTrue($result->pluck('id')->contains($expired1->id));
        $this->assertTrue($result->pluck('id')->contains($expired2->id));
    }

    public function test_get_expired_batches_returns_empty_when_none_expired()
    {
        Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-future',
            'expiry_date' => now()->addDays(10),
            'quantity' => 100,
            'available_quantity' => 30,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $result = $this->service->getExpiredBatches();

        $this->assertCount(0, $result);
    }

    public function test_consume_batch_decrements_available_quantity()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 50,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->service->consumeBatch($batch->id, 20);

        $batch->refresh();
        $this->assertEquals(30, $batch->available_quantity);
        $this->assertEquals('available', $batch->status);
    }

    public function test_consume_batch_marks_as_depleted_when_quantity_reaches_zero()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 20,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->service->consumeBatch($batch->id, 20);

        $batch->refresh();
        $this->assertEquals(0, $batch->available_quantity);
        $this->assertEquals('depleted', $batch->status);
    }

    public function test_consume_batch_throws_when_insufficient_quantity()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 10,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient quantity in batch');

        $this->service->consumeBatch($batch->id, 20);
    }

    public function test_consume_batch_throws_when_batch_not_found()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->consumeBatch(99999, 10);
    }

    public function test_return_to_batch_increments_available_quantity()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 30,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->service->returnToBatch($batch->id, 10);

        $batch->refresh();
        $this->assertEquals(40, $batch->available_quantity);
        $this->assertEquals('available', $batch->status);
    }

    public function test_return_to_batch_restores_status_from_depleted_to_available()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 0,
            'received_date' => now(),
            'status' => 'depleted',
        ]);

        $this->service->returnToBatch($batch->id, 15);

        $batch->refresh();
        $this->assertEquals(15, $batch->available_quantity);
        $this->assertEquals('available', $batch->status);
    }

    public function test_return_to_batch_does_not_change_status_when_already_available()
    {
        $batch = Batch::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'B-001',
            'expiry_date' => now()->addDays(30),
            'quantity' => 100,
            'available_quantity' => 20,
            'received_date' => now(),
            'status' => 'available',
        ]);

        $this->service->returnToBatch($batch->id, 5);

        $batch->refresh();
        $this->assertEquals(25, $batch->available_quantity);
        $this->assertEquals('available', $batch->status);
    }

    public function test_return_to_batch_throws_when_batch_not_found()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->returnToBatch(99999, 10);
    }
}
