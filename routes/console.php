<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('jobproof:reminders')->dailyAt('09:00')->timezone('Asia/Kuala_Lumpur');

// Staging runs a systemd worker (bayar-siap-queue, so live notifications go out in ~1 s); this per-minute drain from
// cron is the fallback if that worker is down.
// Production runs Horizon on Redis instead, so this only applies to the database queue.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('queue.default') === 'database');
