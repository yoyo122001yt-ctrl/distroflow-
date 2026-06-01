<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Product;
use App\Models\RetailStore;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function generateSalesReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $orders = SalesOrder::whereBetween('order_date', [$startDate, $endDate])->get();

        $totalRevenue = $orders->sum('total_amount');
        $totalOrders = $orders->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        $byStatus = $orders->groupBy('status')->map(fn($g) => $g->count());

        $dailyBreakdown = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(order_date) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_revenue' => $totalRevenue,
            'total_orders' => $totalOrders,
            'average_order_value' => round($avgOrderValue, 2),
            'by_status' => $byStatus,
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    public function generateInventoryReport(): array
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $lowStock = Product::where('stock_quantity', '<=', 5)->where('is_active', true)->count();
        $outOfStock = Product::where('stock_quantity', '<=', 0)->where('is_active', true)->count();

        $totalValue = Product::where('is_active', true)
            ->select(DB::raw('SUM(stock_quantity * cost_price) as total_value'))
            ->value('total_value') ?? 0;

        $expiringSoon = DB::table('batches')
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>=', now())
            ->where('quantity', '>', 0)
            ->count();

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_value' => round($totalValue, 2),
            'expiring_soon' => $expiringSoon,
        ];
    }

    public function generateDriverPerformanceReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $drivers = Driver::withCount(['deliveries as total_deliveries' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->withCount(['deliveries as completed_deliveries' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])->where('status', 'completed');
            }])
            ->get();

        $totalDeliveries = $drivers->sum('total_deliveries');
        $completedDeliveries = $drivers->sum('completed_deliveries');
        $onTimeRate = $totalDeliveries > 0 ? round(($completedDeliveries / $totalDeliveries) * 100, 1) : 0;

        return [
            'total_drivers' => $drivers->count(),
            'total_deliveries' => $totalDeliveries,
            'completed_deliveries' => $completedDeliveries,
            'on_time_rate' => $onTimeRate,
            'drivers' => $drivers->map(fn($d) => [
                'name' => $d->name,
                'total' => $d->total_deliveries,
                'completed' => $d->completed_deliveries,
            ]),
        ];
    }

    public function generateStoreAnalysisReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $stores = RetailStore::withCount(['salesOrders as order_count' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('order_date', [$startDate, $endDate]);
            }])
            ->withSum(['salesOrders as total_spent' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('order_date', [$startDate, $endDate]);
            }], 'total_amount')
            ->get();

        $totalStores = $stores->count();
        $activeStores = $stores->where('order_count', '>', 0)->count();
        $avgOrdersPerStore = $totalStores > 0 ? round($stores->sum('order_count') / $totalStores, 1) : 0;
        $avgValuePerStore = $totalStores > 0 ? round($stores->sum('total_spent') / $totalStores, 2) : 0;

        return [
            'total_stores' => $totalStores,
            'active_stores' => $activeStores,
            'avg_orders_per_store' => $avgOrdersPerStore,
            'avg_value_per_store' => $avgValuePerStore,
            'stores' => $stores->map(fn($s) => [
                'name' => $s->business_name,
                'orders' => $s->order_count,
                'total' => $s->total_spent ?? 0,
            ]),
        ];
    }

    public function generateProfitabilityReport(\DateTime $startDate, \DateTime $endDate): array
    {
        $revenue = SalesOrder::whereBetween('order_date', [$startDate, $endDate])
            ->sum('total_amount') ?? 0;

        $cogs = StockMovement::whereBetween('created_at', [$startDate, $endDate])
            ->where('movement_type', 'out')
            ->sum(DB::raw('quantity * unit_cost')) ?? 0;

        $deliveryCosts = Delivery::whereBetween('created_at', [$startDate, $endDate])
            ->sum('delivery_cost') ?? 0;

        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $deliveryCosts;
        $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : 0;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'delivery_costs' => $deliveryCosts,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_margin' => $margin,
        ];
    }

    public function generateForecastReport(): array
    {
        $dailyRevenue = SalesOrder::where('order_date', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(order_date) as date'), DB::raw('SUM(total_amount) as revenue'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('revenue');

        $avgDailyRevenue = $dailyRevenue->count() > 0 ? $dailyRevenue->sum() / $dailyRevenue->count() : 0;
        $trend = $dailyRevenue->count() > 1 ? ($dailyRevenue->last() - $dailyRevenue->first()) / $dailyRevenue->count() : 0;

        $forecastDays = 30;
        $forecast = [];
        for ($i = 1; $i <= $forecastDays; $i++) {
            $forecast[] = round(max(0, $avgDailyRevenue + ($trend * $i)), 2);
        }

        return [
            'average_daily_revenue' => round($avgDailyRevenue, 2),
            'trend' => round($trend, 2),
            'forecast_30_days' => $forecast,
            'projected_monthly' => round($avgDailyRevenue * 30, 2),
        ];
    }
}
