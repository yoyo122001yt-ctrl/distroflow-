<?php

namespace App\Exports;

use App\Models\RetailStore;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StoreStatementExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected \DateTime $startDate,
        protected \DateTime $endDate
    ) {}

    public function collection(): Collection
    {
        return RetailStore::withCount(['salesOrders as order_count' => function ($q) {
                $q->whereBetween('order_date', [$this->startDate, $this->endDate]);
            }])
            ->withSum(['salesOrders as total_amount' => function ($q) {
                $q->whereBetween('order_date', [$this->startDate, $this->endDate]);
            }], 'total_amount')
            ->get();
    }

    public function headings(): array
    {
        return ['Store', 'Type', 'Phone', 'Orders', 'Total Amount', 'Balance', 'Credit Limit'];
    }

    public function map($store): array
    {
        return [
            $store->business_name,
            $store->store_type ?? 'N/A',
            $store->phone ?? 'N/A',
            $store->order_count ?? 0,
            number_format($store->total_amount ?? 0, 2),
            number_format($store->balance ?? 0, 2),
            number_format($store->credit_limit ?? 0, 2),
        ];
    }
}
