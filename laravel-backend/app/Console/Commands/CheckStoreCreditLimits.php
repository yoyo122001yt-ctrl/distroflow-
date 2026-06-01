<?php

namespace App\Console\Commands;

use App\Models\RetailStore;
use Illuminate\Console\Command;

class CheckStoreCreditLimits extends Command
{
    protected $signature = 'distroflow:check-credit';
    protected $description = 'Check retail stores approaching or exceeding credit limits';

    public function handle(): void
    {
        $stores = RetailStore::where('status', 'active')
            ->where('credit_limit', '>', 0)
            ->get();

        $warnings = [];

        foreach ($stores as $store) {
            $usagePercent = $store->credit_limit > 0
                ? ($store->current_balance / $store->credit_limit) * 100
                : 0;

            if ($usagePercent >= 90) {
                $warnings[] = [
                    'store' => $store->business_name,
                    'balance' => $store->current_balance,
                    'limit' => $store->credit_limit,
                    'usage' => round($usagePercent, 1) . '%',
                ];

                $this->warn("CREDIT WARNING: {$store->business_name} at {$usagePercent}% of limit");
            }
        }

        $this->info("Checked {$stores->count()} stores. {$warnings} approaching limit.");
    }
}
