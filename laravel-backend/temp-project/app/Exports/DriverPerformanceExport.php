<?php

namespace App\Exports;

use App\Models\Driver;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DriverPerformanceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected \DateTime $startDate,
        protected \DateTime $endDate
    ) {}

    public function collection(): Collection
    {
        return Driver::withCount(['deliveries as total_deliveries' => function ($q) {
                $q->whereBetween('created_at', [$this->startDate, $this->endDate]);
            }])
            ->withCount(['deliveries as on_time_deliveries' => function ($q) {
                $q->whereBetween('created_at', [$this->startDate, $this->endDate])
                  ->where('status', 'completed')
                  ->whereRaw("completed_at <= scheduled_date");
            }])
            ->get();
    }

    public function headings(): array
    {
        return ['Driver', 'Phone', 'Total Deliveries', 'On-Time', 'On-Time Rate (%)'];
    }

    public function map($driver): array
    {
        $rate = $driver->total_deliveries > 0
            ? round(($driver->on_time_deliveries / $driver->total_deliveries) * 100, 1)
            : 0;

        return [
            $driver->name,
            $driver->phone ?? 'N/A',
            $driver->total_deliveries,
            $driver->on_time_deliveries,
            $rate,
        ];
    }
}
