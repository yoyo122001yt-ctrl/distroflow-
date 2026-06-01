<?php

namespace App\Exports;

use App\Models\SalesOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected \DateTime $startDate,
        protected \DateTime $endDate
    ) {}

    public function collection(): Collection
    {
        return SalesOrder::with(['retailStore', 'items'])
            ->whereBetween('order_date', [$this->startDate, $this->endDate])
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return ['Order #', 'Store', 'Date', 'Items', 'Total', 'Status'];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->retailStore?->business_name ?? 'N/A',
            $order->order_date->format('Y-m-d H:i'),
            $order->items->sum('quantity'),
            number_format($order->total_amount, 2),
            $order->status,
        ];
    }
}
