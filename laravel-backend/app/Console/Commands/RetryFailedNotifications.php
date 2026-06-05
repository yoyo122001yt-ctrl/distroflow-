<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class RetryFailedNotifications extends Command
{
    protected $signature = 'notifications:retry-failed {--max=3 : Max retry attempts}';
    protected $description = 'Retry failed SMS/WhatsApp notifications';

    public function handle(NotificationService $notifier): int
    {
        $this->info('Retrying failed notifications...');

        $max = (int) $this->option('max');
        $retried = $notifier->retryFailed($max);

        $this->info("Successfully retried {$retried} notification(s).");

        return Command::SUCCESS;
    }
}
