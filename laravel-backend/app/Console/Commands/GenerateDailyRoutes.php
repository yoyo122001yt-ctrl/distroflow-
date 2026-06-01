<?php

namespace App\Console\Commands;

use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\SalesOrder;
use App\Services\RouteOptimizationService;
use Illuminate\Console\Command;

class GenerateDailyRoutes extends Command
{
    protected $signature = 'distroflow:generate-routes';
    protected $description = 'Generate daily route assignments and manifests';

    public function handle(): void
    {
        $routes = Route::where('status', 'active')->get();

        foreach ($routes as $route) {
            $pendingOrders = SalesOrder::where('route_id', $route->id)
                ->where('status', 'approved')
                ->where(function ($q) {
                    $q->whereNull('route_assignments.id');
                })
                ->get();

            if ($pendingOrders->isEmpty()) {
                $this->info("No pending orders for route {$route->code}");
                continue;
            }

            $assignment = RouteAssignment::create([
                'route_id' => $route->id,
                'assignment_date' => now()->addDay()->toDateString(),
                'status' => 'scheduled',
            ]);

            $this->info("Generated assignment for route {$route->code} with {$pendingOrders->count()} orders");
        }

        $this->info('Daily route generation complete.');
    }
}
