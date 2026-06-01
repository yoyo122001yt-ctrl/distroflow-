<?php

namespace App\Services;

use App\Models\RetailStore;
use App\Models\Product;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Batch;
use App\Models\DriverSettlement;
use App\Models\Invoice;
use App\Models\TruckInventory;
use App\Models\Route;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function routeSettlementReport(array $filters = [])
    {
        $query = DriverSettlement::with(['driver', 'routeAssignment.route', 'delivery']);

        if (!empty($filters['date_from'])) {
            $query->where('settlement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('settlement_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        $settlements = $query->orderBy('settlement_date', 'desc')->get();

        return [
            'settlements' => $settlements,
            'total_sales' => $settlements->sum('total_sales'),
            'total_collected' => $settlements->sum('actual_cash'),
            'total_variance' => $settlements->sum('cash_variance'),
            'flagged_count' => $settlements->where('status', 'flagged')->count(),
        ];
    }

    public function salesByStoreReport(array $filters = [])
    {
        $query = DeliveryItem::select(
            'retail_store_id',
            DB::raw('SUM(quantity_delivered * unit_price) as total_sales'),
            DB::raw('SUM(quantity_delivered) as total_units'),
            DB::raw('COUNT(DISTINCT delivery_id) as delivery_count')
        )
            ->join('deliveries', 'delivery_items.delivery_id', '=', 'deliveries.id')
            ->join('retail_stores', 'delivery_items.sales_order_id', '=', 'retail_stores.id')
            ->groupBy('retail_store_id');

        if (!empty($filters['date_from'])) {
            $query->where('deliveries.delivery_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('deliveries.delivery_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['store_id'])) {
            $query->where('retail_store_id', $filters['store_id']);
        }

        return $query->get();
    }

    public function salesByProductReport(array $filters = [])
    {
        $query = DeliveryItem::select(
            'product_id',
            DB::raw('SUM(quantity_delivered) as total_quantity'),
            DB::raw('SUM(quantity_delivered * unit_price) as total_sales'),
            DB::raw('COUNT(DISTINCT delivery_id) as delivery_count')
        )
            ->groupBy('product_id');

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->with('product')->get();
    }

    public function expiryReport(int $days = 30)
    {
        $fefo = app(FEFOService::class);
        $expiring = $fefo->getExpiringBatches($days);
        $expired = $fefo->getExpiredBatches();

        return [
            'expiring_soon' => $expiring,
            'expired' => $expired,
            'total_expiring_quantity' => $expiring->sum('available_quantity'),
            'total_expired_quantity' => $expired->sum('available_quantity'),
            'expiring_value' => $expiring->sum(fn($b) => $b->available_quantity * $b->cost_price),
            'expired_value' => $expired->sum(fn($b) => $b->available_quantity * $b->cost_price),
        ];
    }

    public function driverPerformanceReport(array $filters = [])
    {
        $query = DriverSettlement::select(
            'driver_id',
            DB::raw('COUNT(*) as route_count'),
            DB::raw('SUM(total_sales) as total_sales'),
            DB::raw('SUM(actual_cash) as total_collected'),
            DB::raw('SUM(cash_variance) as total_cash_variance'),
            DB::raw('SUM(inventory_variance) as total_inventory_variance'),
            DB::raw('AVG(total_sales) as avg_sales_per_route')
        )
            ->groupBy('driver_id');

        if (!empty($filters['date_from'])) {
            $query->where('settlement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('settlement_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        return $query->with('driver')->get();
    }

    public function creditAgingReport()
    {
        $service = app(InvoiceService::class);
        return $service->getAgingReport();
    }

    public function profitByRouteReport(array $filters = [])
    {
        $query = DriverSettlement::select(
            'route_assignments.route_id',
            DB::raw('SUM(total_sales) as total_revenue'),
            DB::raw('SUM(sales_value) as total_cost'),
            DB::raw('SUM(total_sales) - SUM(sales_value) as gross_profit'),
            DB::raw('COUNT(*) as delivery_count')
        )
            ->join('route_assignments', 'driver_settlements.route_assignment_id', '=', 'route_assignments.id')
            ->groupBy('route_assignments.route_id');

        if (!empty($filters['date_from'])) {
            $query->where('settlement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('settlement_date', '<=', $filters['date_to']);
        }

        return $query->with('route')->get();
    }

    public function truckInventoryReport()
    {
        $inventory = TruckInventory::with(['truck', 'product', 'batch'])
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('truck_id');

        $report = [];
        foreach ($inventory as $truckId => $items) {
            $truck = $items->first()->truck;
            $report[] = [
                'truck' => $truck,
                'items' => $items,
                'total_items' => $items->sum('quantity'),
                'total_value' => $items->sum(fn($i) => $i->quantity * ($i->batch?->cost_price ?? 0)),
            ];
        }

        return $report;
    }

    public function warehouseStockValuation(string $method = 'average')
    {
        $batches = Batch::with('product')
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->get()
            ->groupBy('product_id');

        $valuation = [];
        foreach ($batches as $productId => $productBatches) {
            $product = $productBatches->first()->product;
            $totalQty = $productBatches->sum('available_quantity');

            $value = match ($method) {
                'fifo' => $this->fifoValuation($productBatches),
                'average' => $productBatches->avg('cost_price') * $totalQty,
                default => $productBatches->sum(fn($b) => $b->available_quantity * $b->cost_price),
            };

            $valuation[] = [
                'product' => $product,
                'total_quantity' => $totalQty,
                'total_value' => round($value, 2),
                'avg_cost' => $totalQty > 0 ? round($productBatches->avg('cost_price'), 2) : 0,
                'batches' => $productBatches,
            ];
        }

        return [
            'items' => $valuation,
            'total_value' => round(array_sum(array_column($valuation, 'total_value')), 2),
            'method' => $method,
        ];
    }

    private function fifoValuation($batches): float
    {
        $sorted = $batches->sortBy('received_date');
        $value = 0;
        foreach ($sorted as $batch) {
            $value += $batch->available_quantity * $batch->cost_price;
        }
        return $value;
    }
}
