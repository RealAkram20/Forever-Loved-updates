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
        // White-label envelope: the display name the recipient reads, and where a
        // reply lands. The from-ADDRESS stays the platform's (SPF/DKIM sign for our
        // domain, not the reseller's), but the name is what humans actually see.
        public ?string $fromName = null,
        public ?string $replyTo = null,
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
            if ($this->fromName) {
                $message->from(config('mail.from.address'), $this->fromName);
            }
            if ($this->replyTo) {
                $message->replyTo($this->replyTo);
            }
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
