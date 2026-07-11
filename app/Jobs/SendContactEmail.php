<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Services\SystemMailConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendContactEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $name,
        public string $email,
        public string $subject,
        public string $body,
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

        $appName = SystemSetting::get('branding.app_name', config('app.name'));
        $toAddress = SystemSetting::get('smtp.from_address');
        if (! $toAddress) {
            Log::warning('Contact email dropped: no smtp.from_address configured', ['from' => $this->email]);

            return;
        }

        $html = $this->buildHtml($appName);

        Mail::html($html, function ($msg) use ($toAddress, $appName) {
            $msg->to($toAddress)
                ->replyTo($this->email, $this->name)
                ->subject("{$appName} - Contact: {$this->subject}");
        });
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('Contact form email permanently failed', [
            'from' => $this->email,
            'subject' => $this->subject,
            'error' => $e?->getMessage(),
        ]);
    }

    private function buildHtml(string $appName): string
    {
        $safeName = e($this->name);
        $safeEmail = e($this->email);
        $safeSubject = e($this->subject);
        $safeBody = nl2br(e($this->body));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <tr>
                                <td style="background:#465fff;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:18px;font-weight:600;">{$appName} - Contact Form</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td>
                                                <h2 style="margin:0 0 16px;color:#1f2937;font-size:18px;font-weight:600;">{$safeSubject}</h2>
                                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                                    <tr>
                                                        <td style="padding:4px 0;color:#6b7280;font-size:14px;"><strong>From:</strong> {$safeName}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0;color:#6b7280;font-size:14px;"><strong>Email:</strong> <a href="mailto:{$safeEmail}" style="color:#465fff;">{$safeEmail}</a></td>
                                                    </tr>
                                                </table>
                                                <div style="padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
                                                    <p style="margin:0;color:#374151;font-size:15px;line-height:1.6;">{$safeBody}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                                    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                                        This message was sent via the contact form on {$appName}.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
