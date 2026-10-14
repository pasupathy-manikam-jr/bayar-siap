<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('jobproof:reminders')->dailyAt('09:00')->timezone('Asia/Kuala_Lumpur');

// Shared hosting (staging) has no process supervisor: cron runs the scheduler every minute, which drains the queue.
// Production runs Horizon on Redis instead, so this only applies to the database queue.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping()
    ->when(fn () => config('queue.default') === 'database');
