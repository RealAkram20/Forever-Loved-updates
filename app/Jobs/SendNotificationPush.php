<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendNotificationPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $notificationId)
    {
    }

    public function backoff(): int
    {
        return 120;
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);
        if (! $notification) {
            return;
        }

        NotificationService::dispatchPush($notification);
    }

    public function failed(?\Throwable $e): void
    {
        $message = $e?->getMessage() ?? '';
        if (str_contains($message, 'BCMath') || str_contains($message, 'GMP')) {
            // Server capability problem, not a transient failure — every push
            // will fail until the host enables the extension.
            Log::error('Push notifications require the BCMath or GMP PHP extension — enable it in your hosting PHP configuration.', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        Log::warning('Push notification permanently failed', [
            'notification_id' => $this->notificationId,
            'error' => $message,
        ]);
    }
}
