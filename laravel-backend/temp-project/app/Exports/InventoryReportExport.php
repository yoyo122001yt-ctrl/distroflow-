<?php

namespace App\Exports;

use App\Models\Batch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Batch::with(['product', 'warehouse'])
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get();
    }

    public function headings(): array
    {
        return ['Product', 'SKU', 'Warehouse', 'Batch', 'Quantity', 'Expiry Date', 'Days Left', 'Value'];
    }

    public function map($batch): array
    {
        $daysLeft = $batch->expiry_date ? now()->diffInDays($batch->expiry_date, false) : null;
        return [
            $batch->product?->name ?? 'N/A',
            $batch->product?->sku ?? 'N/A',
            $batch->warehouse?->name ?? 'N/A',
            $batch->batch_number ?? 'N/A',
            $batch->quantity,
            $batch->expiry_date?->format('Y-m-d') ?? 'N/A',
            $daysLeft !== null ? ($daysLeft >= 0 ? $daysLeft : 'Expired') : 'N/A',
            number_format($batch->quantity * ($batch->product?->cost_price ?? 0), 2),
        ];
    }
}
