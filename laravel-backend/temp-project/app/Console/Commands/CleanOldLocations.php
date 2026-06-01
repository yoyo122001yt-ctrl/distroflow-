<?php

namespace App\Console\Commands;

use App\Models\DriverLocation;
use Illuminate\Console\Command;

class CleanOldLocations extends Command
{
    protected $signature = 'distroflow:clean-old-locations';
    protected $description = 'Delete driver location data older than 7 days';

    public function handle(): void
    {
        $cutoff = now()->subDays(7);
        $deleted = DriverLocation::where('recorded_at', '<', $cutoff)->delete();

        $this->info("Deleted {$deleted} old location records.");
    }
}
