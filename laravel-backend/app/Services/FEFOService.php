<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Support\Collection;

class FEFOService
{
    const int HIGH = 5;

    public function getPickingBatches(int $productId, float $quantityNeeded): Collection
    {
        $batches = Batch::where('product_id', $productId)
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->orderBy('expiry_date', 'asc')
            ->get();

        $picked = collect();
        $remaining = $quantityNeeded;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $pickQty = min($batch->available_quantity, $remaining);

            $picked->push((object) [
                'batch_id' => $batch->id,
                'batch' => $batch,
                'quantity' => $pickQty,
                'expiry_date' => $batch->expiry_date,
            ]);

            $remaining -= $pickQty;
        }

        if ($remaining > 0) {
            $product = Product::find($productId);
            throw new \RuntimeException(
                "Insufficient stock for product {$product?->name} (SKU: {$product?->sku}). "
                . "Needed: {$quantityNeeded}, Available: " . ($quantityNeeded - $remaining)
            );
        }

        return $picked;
    }

    public function getExpiringBatches(int $days = 30): Collection
    {
        return Batch::with('product')
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now())
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    public function getExpiredBatches(): Collection
    {
        return Batch::with('product')
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where('expiry_date', '<', now())
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    public function consumeBatch(int $batchId, float $quantity): void
    {
        $batch = Batch::findOrFail($batchId);

        if ($batch->available_quantity < $quantity) {
            throw new \RuntimeException("Insufficient quantity in batch {$batch->batch_number}");
        }

        $batch->decrement('available_quantity', $quantity);

        if ($batch->available_quantity <= 0) {
            $batch->update(['status' => 'depleted']);
        }
    }

    public function returnToBatch(int $batchId, float $quantity): void
    {
        $batch = Batch::findOrFail($batchId);
        $batch->increment('available_quantity', $quantity);

        if ($batch->status === 'depleted') {
            $batch->update(['status' => 'available']);
        }
    }
}
