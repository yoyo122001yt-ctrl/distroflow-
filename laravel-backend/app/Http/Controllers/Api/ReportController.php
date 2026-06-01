<?php

namespace App\Http\Controllers\Api;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function routeSettlement(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'driver_id']);
            $report = $this->reportService->routeSettlementReport($filters);

            return response()->json([
                'data' => $report,
                'message' => 'Route settlement report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function salesByStore(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'store_id']);
            $report = $this->reportService->salesByStoreReport($filters);

            return response()->json([
                'data' => $report,
                'message' => 'Sales by store report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function salesByProduct(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'product_id']);
            $report = $this->reportService->salesByProductReport($filters);

            return response()->json([
                'data' => $report,
                'message' => 'Sales by product report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function expiry(Request $request)
    {
        try {
            $days = $request->integer('days', 30);
            $report = $this->reportService->expiryReport($days);

            return response()->json([
                'data' => $report,
                'message' => 'Expiry report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function driverPerformance(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to', 'driver_id']);
            $report = $this->reportService->driverPerformanceReport($filters);

            return response()->json([
                'data' => $report,
                'message' => 'Driver performance report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function creditAging()
    {
        try {
            $report = $this->reportService->creditAgingReport();

            return response()->json([
                'data' => $report,
                'message' => 'Credit aging report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function profitByRoute(Request $request)
    {
        try {
            $filters = $request->only(['date_from', 'date_to']);
            $report = $this->reportService->profitByRouteReport($filters);

            return response()->json([
                'data' => $report,
                'message' => 'Profit by route report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function truckInventory()
    {
        try {
            $report = $this->reportService->truckInventoryReport();

            return response()->json([
                'data' => $report,
                'message' => 'Truck inventory report generated',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|string|in:route_settlement,sales_store,sales_product,expiry,driver_performance,credit_aging,profit_route,truck_inventory',
                'format' => 'required|string|in:csv,xlsx,pdf',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
            ]);

            $filters = $request->only(['date_from', 'date_to', 'driver_id', 'store_id', 'product_id']);

            $reportData = match ($request->type) {
                'route_settlement' => $this->reportService->routeSettlementReport($filters),
                'sales_store' => $this->reportService->salesByStoreReport($filters),
                'sales_product' => $this->reportService->salesByProductReport($filters),
                'expiry' => $this->reportService->expiryReport($request->integer('days', 30)),
                'driver_performance' => $this->reportService->driverPerformanceReport($filters),
                'credit_aging' => $this->reportService->creditAgingReport(),
                'profit_route' => $this->reportService->profitByRouteReport($filters),
                'truck_inventory' => $this->reportService->truckInventoryReport(),
            };

            return response()->json([
                'data' => $reportData,
                'export_info' => [
                    'type' => $request->type,
                    'format' => $request->format,
                    'generated_at' => now(),
                ],
                'message' => 'Report exported successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to export report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
