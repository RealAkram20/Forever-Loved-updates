<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Answers "is background processing actually running on this install?".
 * Customers self-host on shared hosting where the cron may never be set up,
 * so dispatch decisions and the admin health banner both key off this.
 */
class QueueHealthHelper
{
    public const HEARTBEAT_KEY = 'scheduler.last_heartbeat';

    /** Minutes without a heartbeat before the scheduler counts as down. */
    private const HEARTBEAT_STALE_MINUTES = 5;

    /** Minutes a job may wait before the queue counts as backed up. */
    private const QUEUE_STALE_MINUTES = 10;

    public static function schedulerHealthy(): bool
    {
        $beat = rescue(fn () => Cache::get(self::HEARTBEAT_KEY), null, false);
        if (! $beat) {
            return false;
        }

        $at = rescue(fn () => Carbon::parse($beat), null, false);

        return $at !== null && $at->gt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    public static function queueHealthy(): bool
    {
        $oldest = rescue(fn () => DB::table('jobs')->min('available_at'), null, false);
        if ($oldest === null) {
            return true; // empty queue is a healthy queue
        }

        return $oldest > now()->subMinutes(self::QUEUE_STALE_MINUTES)->getTimestamp();
    }

    public static function failedJobsCount(): int
    {
        return (int) rescue(fn () => DB::table('failed_jobs')->count(), 0, false);
    }

    /**
     * The crontab line an admin should paste into their host's panel.
     *
     * Two shared-hosting traps this avoids:
     * - PHP_BINARY under a web request is the SAPI serving the page. On LiteSpeed
     *   (Hostinger, most cPanel hosts) that is `lsphp`, which is not a CLI binary
     *   and does nothing under cron — so hand back the `php` beside it.
     * - No `>> /dev/null 2>&1`. Panel cron fields often exec the command without
     *   a shell, and artisan then rejects `>>` as an unexpected argument.
     */
    public static function cronLine(): string
    {
        $binary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';

        if (str_ends_with($binary, '/lsphp')) {
            $cli = substr($binary, 0, -5).'php';
            if (is_executable($cli)) {
                $binary = $cli;
            }
        }

        return '* * * * * '.$binary.' '.base_path('artisan').' schedule:run';
    }
}
