<?php

namespace App\Http\Controllers\Api;

use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new ProductsImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'message' => 'Import completed',
            'data' => [
                'imported' => $import->getImportedCount(),
                'failed' => $import->getFailedCount(),
                'errors' => $import->getErrors(),
            ],
        ]);
    }

    public function downloadTemplate()
    {
        $headers = ['name', 'sku', 'category', 'selling_price', 'cost_price', 'stock_quantity', 'unit', 'description', 'is_active'];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product-import-template.csv"',
        ]);
    }
}
