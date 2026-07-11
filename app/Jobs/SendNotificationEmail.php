<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId)
    {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);
        if (! $notification) {
            return; // deleted since enqueue — nothing to send
        }

        // dispatchEmail applies the admin SMTP settings itself, which is what
        // makes this safe inside a queue worker that never saw the request.
        NotificationService::dispatchEmail($notification);
    }

    public function failed(?\Throwable $e): void
    {
        Log::warning('Notification email permanently failed', [
            'notification_id' => $this->notificationId,
            'error' => $e?->getMessage(),
        ]);
    }
}
