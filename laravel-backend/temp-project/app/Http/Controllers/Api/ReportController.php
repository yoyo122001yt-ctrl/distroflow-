<?php

namespace App\Http\Controllers\Api;

use App\Exports\DriverPerformanceExport;
use App\Exports\InventoryReportExport;
use App\Exports\SalesReportExport;
use App\Exports\StoreStatementExport;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function show($type, Request $request)
    {
        $startDate = $request->start_date ? new \DateTime($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? new \DateTime($request->end_date) : now()->endOfMonth();

        $data = match ($type) {
            'sales' => $this->reportService->generateSalesReport($startDate, $endDate),
            'inventory' => $this->reportService->generateInventoryReport(),
            'driver-performance' => $this->reportService->generateDriverPerformanceReport($startDate, $endDate),
            'store-analysis' => $this->reportService->generateStoreAnalysisReport($startDate, $endDate),
            'profitability' => $this->reportService->generateProfitabilityReport($startDate, $endDate),
            'forecast' => $this->reportService->generateForecastReport(),
            default => throw new \InvalidArgumentException("Unknown report type: {$type}"),
        };

        return response()->json(['data' => $data]);
    }

    public function exportReport($type, Request $request)
    {
        $startDate = $request->start_date ? new \DateTime($request->start_date) : now()->startOfMonth();
        $endDate = $request->end_date ? new \DateTime($request->end_date) : now()->endOfMonth();
        $format = $request->format ?? 'xlsx';

        $exportClass = match ($type) {
            'sales' => new SalesReportExport($startDate, $endDate),
            'inventory' => new InventoryReportExport(),
            'driver-performance' => new DriverPerformanceExport($startDate, $endDate),
            'store-statement' => new StoreStatementExport($startDate, $endDate),
            default => throw new \InvalidArgumentException("Unknown export type: {$type}"),
        };

        $filename = "{$type}-report-" . now()->format('Y-m-d');

        if ($format === 'pdf') {
            $data = $exportClass->collection();
            $pdf = Pdf::loadView('pdf.report', compact('data', 'type', 'startDate', 'endDate'));
            return $pdf->download("{$filename}.pdf");
        }

        $writerType = match ($format) {
            'csv' => \Maatwebsite\Excel\Excel::CSV,
            'xls' => \Maatwebsite\Excel\Excel::XLS,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };

        return Excel::download($exportClass, "{$filename}.{$format}", $writerType);
    }
}
