<?php

namespace App\Jobs;

use App\Services\SystemMailConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One-off transactional email to an address (guest welcome, subscriber
 * updates) where no Notification record exists to hang the payload on.
 */
class SendRawEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $to,
        public ?string $name,
        public string $subject,
        public string $body,
        public bool $isHtml = false,
    ) {
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        SystemMailConfigurator::applyFromSettings();
        if (! SystemMailConfigurator::mailDeliveryConfigured()) {
            return; // mail was disabled after enqueue — drop silently, matches inline behavior
        }

        $callback = function ($message) {
            $message->to($this->to, $this->name)->subject($this->subject);
        };

        $this->isHtml ? Mail::html($this->body, $callback) : Mail::raw($this->body, $callback);
    }

    public function failed(?\Throwable $e): void
    {
        Log::warning('Raw email permanently failed', [
            'to' => $this->to,
            'subject' => $this->subject,
            'error' => $e?->getMessage(),
        ]);
    }
}
