<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduledReports extends Command
{
    protected $signature = 'reports:send-scheduled {--type=daily : daily|weekly|monthly}';
    protected $description = 'Generate and email scheduled reports to managers';

    public function handle(ReportService $reportService): int
    {
        $type = $this->option('type');
        $this->info("Generating {$type} reports...");

        $managers = User::whereIn('role', ['admin', 'warehouse_manager', 'manager'])
            ->where('is_active', true)
            ->get();

        if ($managers->isEmpty()) {
            $this->warn('No active managers found to send reports to.');
            return Command::SUCCESS;
        }

        $dateRange = match ($type) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        $salesReport = $reportService->generateSalesReport($dateRange[0], $dateRange[1]);
        $driverReport = $reportService->generateDriverPerformanceReport($dateRange[0], $dateRange[1]);

        foreach ($managers as $manager) {
            try {
                Mail::raw(
                    "{$type|ucfirst} Report for " . $dateRange[0]->format('Y-m-d') . " to " . $dateRange[1]->format('Y-m-d') . "\n\n"
                    . "Sales: {$salesReport['total_sales']} orders, total {$salesReport['total_revenue']} EGP\n"
                    . "Deliveries: {$driverReport['total_deliveries']} completed\n"
                    . "On-Time Rate: {$driverReport['on_time_rate']}%\n\n"
                    . "Login to view the full dashboard.",
                    function ($message) use ($manager, $type) {
                        $message->to($manager->email)
                            ->subject("DistroFlow " . ucfirst($type) . " Report");
                    }
                );
                $this->info("Report sent to {$manager->email}");
            } catch (\Throwable $e) {
                $this->error("Failed to send to {$manager->email}: {$e->getMessage()}");
            }
        }

        $this->info('Scheduled reports sent successfully.');
        return Command::SUCCESS;
    }
}
