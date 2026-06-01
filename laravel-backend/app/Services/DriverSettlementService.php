<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DriverSettlement;
use App\Models\TruckInventory;
use Illuminate\Support\Facades\DB;

class DriverSettlementService
{
    public float $cashVarianceThreshold = 10.00;
    public float $inventoryVarianceThreshold = 0.01;

    public function calculateSettlement(int $deliveryId): DriverSettlement
    {
        $delivery = Delivery::with(['items', 'payments', 'returns', 'routeAssignment'])
            ->findOrFail($deliveryId);

        return DB::transaction(function () use ($delivery) {
            $totalSales = $delivery->items->sum(function ($item) {
                return $item->quantity_delivered * $item->unit_price;
            });

            $totalReturns = $delivery->returns->sum(function ($return) {
                $batch = $return->batch;
                return $return->quantity * ($batch?->cost_price ?? 0);
            });

            $expectedCash = $totalSales - $totalReturns;
            $actualCash = $delivery->payments->sum('amount');
            $cashVariance = $expectedCash - $actualCash;

            $truckInventory = TruckInventory::where('truck_id', $delivery->truck_id)->get();

            $startingValue = $truckInventory->sum(function ($inv) {
                return $inv->starting_quantity * ($inv->batch?->cost_price ?? 0);
            });

            $loadedValue = $delivery->items->sum(function ($item) {
                return $item->quantity_loaded * $item->unit_price;
            });

            $salesValue = $delivery->items->sum(function ($item) {
                return $item->quantity_delivered * $item->unit_price;
            });

            $returnsValue = $delivery->returns->sum(function ($return) {
                $batch = $return->batch;
                return $return->quantity * ($batch?->cost_price ?? 0);
            });

            $expectedEndValue = $startingValue + $loadedValue - $salesValue - $returnsValue;

            $actualEndValue = $truckInventory->sum(function ($inv) {
                return $inv->quantity * ($inv->batch?->cost_price ?? 0);
            });

            $inventoryVariance = $expectedEndValue - $actualEndValue;

            $settlement = DriverSettlement::updateOrCreate(
                [
                    'delivery_id' => $deliveryId,
                    'driver_id' => $delivery->driver_id,
                ],
                [
                    'route_assignment_id' => $delivery->route_assignment_id,
                    'settlement_date' => $delivery->delivery_date,
                    'status' => $this->determineStatus($cashVariance, $inventoryVariance),
                    'total_sales' => $totalSales,
                    'total_returns' => $totalReturns,
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $actualCash,
                    'cash_variance' => $cashVariance,
                    'starting_inventory_value' => $startingValue,
                    'loaded_value' => $loadedValue,
                    'sales_value' => $salesValue,
                    'returns_value' => $returnsValue,
                    'expected_end_inventory_value' => $expectedEndValue,
                    'actual_end_inventory_value' => $actualEndValue,
                    'inventory_variance' => $inventoryVariance,
                ]
            );

            return $settlement;
        });
    }

    private function determineStatus(float $cashVariance, float $inventoryVariance): string
    {
        if (abs($cashVariance) > $this->cashVarianceThreshold
            || abs($inventoryVariance) > $this->inventoryVarianceThreshold) {
            return 'flagged';
        }
        return 'pending';
    }

    public function approveSettlement(int $settlementId, int $approvedBy): DriverSettlement
    {
        $settlement = DriverSettlement::findOrFail($settlementId);
        $settlement->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
        return $settlement;
    }

    public function needsManagerReview(DriverSettlement $settlement): bool
    {
        return abs($settlement->cash_variance) > $this->cashVarianceThreshold
            || abs($settlement->inventory_variance) > $this->inventoryVarianceThreshold;
    }
}
