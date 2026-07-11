<?php

namespace App\Support;

use App\Helpers\QueueHealthHelper;
use Illuminate\Support\Facades\Log;

/**
 * Queue a job only when the customer's cron is demonstrably running;
 * otherwise run it inline so work never rots unseen in the jobs table.
 * A dead cron therefore degrades to the app's original synchronous
 * behavior instead of silently dropping email/notifications.
 */
class ReliableDispatch
{
    public static function dispatch(object $job): void
    {
        if (QueueHealthHelper::schedulerHealthy()) {
            dispatch($job);

            return;
        }

        try {
            dispatch_sync($job);
        } catch (\Throwable $e) {
            // Inline fallback runs inside a user request — the job's own
            // failure handling applies, but it must never break the response.
            Log::warning('Synchronous fallback job failed', [
                'job' => get_class($job),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
