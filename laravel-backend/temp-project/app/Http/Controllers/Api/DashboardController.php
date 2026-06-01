<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\RetailStore;
use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $totalStores = RetailStore::count();
            $totalProducts = Product::count();
            $activeProducts = Product::where('is_active', true)->count();
            $ordersToday = SalesOrder::whereDate('created_at', today())->count();
            $pendingOrders = SalesOrder::where('status', 'pending')->count();
            $monthlyRevenue = SalesOrder::where('status', 'delivered')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');

            return response()->json([
                'data' => [
                    'total_stores' => $totalStores,
                    'total_products' => $totalProducts,
                    'active_products' => $activeProducts,
                    'orders_today' => $ordersToday,
                    'pending_orders' => $pendingOrders,
                    'monthly_revenue' => $monthlyRevenue,
                ],
                'message' => 'Dashboard stats retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function salesTrend()
    {
        try {
            $data = SalesOrder::where('status', 'delivered')
                ->whereYear('created_at', now()->year)
                ->selectRaw('DATE(created_at) as date, SUM(total) as amount')
                ->groupBy('date')
                ->orderBy('date')
                ->take(30)
                ->get();

            return response()->json([
                'data' => $data,
                'message' => 'Sales trend retrieved',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve sales trend',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
