<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Services\FEFOService;
use Illuminate\Console\Command;

class CheckExpiryDates extends Command
{
    protected $signature = 'distroflow:check-expiry';
    protected $description = 'Check and update batch expiry statuses';

    public function handle(): void
    {
        $expired = Batch::where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where('expiry_date', '<', now())
            ->get();

        foreach ($expired as $batch) {
            $batch->update(['status' => 'expired']);
            $this->warn("Batch {$batch->batch_number} for product {$batch->product_id} has expired.");
        }

        $expiringSoon = Batch::where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>=', now())
            ->count();

        $this->info("Expired batches marked: {$expired->count()}");
        $this->info("Batches expiring within 30 days: {$expiringSoon}");
    }
}
