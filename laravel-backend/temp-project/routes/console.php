<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled commands
Schedule::command('notifications:retry-failed --max=3')->everyFiveMinutes();
Schedule::command('reports:send-scheduled --type=daily')->dailyAt('08:00');
Schedule::command('reports:send-scheduled --type=weekly')->weeklyOn(1, '08:00');
Schedule::command('reports:send-scheduled --type=monthly')->monthlyOn(1, '08:00');
