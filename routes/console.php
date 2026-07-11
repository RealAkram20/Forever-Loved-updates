<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Heartbeat: proves the customer's `schedule:run` cron is alive.
// QueueHealthHelper reads this to decide queued vs synchronous dispatch.
Schedule::call(function () {
    Cache::put('scheduler.last_heartbeat', now()->toIso8601String(), now()->addDay());
})->everyMinute();

// Shared-hosting queue worker: no persistent process, the scheduler drains the
// queue each minute. --stop-when-empty + --max-time keep each run short;
// withoutOverlapping absorbs runs that spill past the minute.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('subscriptions:process-lifecycle')->dailyAt('06:00');

// Self-heal orders stuck in pending when both callback and IPN were missed.
Schedule::command('payments:reconcile')->everyFifteenMinutes()->withoutOverlapping();

Schedule::call(function () {
    if (app()->environment('production') && config('app.debug')) {
        \Illuminate\Support\Facades\Log::warning('APP_DEBUG is enabled in production — stack traces and secrets may leak to visitors. Set APP_DEBUG=false in .env.');
    }
})->daily();
