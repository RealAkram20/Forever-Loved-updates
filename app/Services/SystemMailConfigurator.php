<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;

class SystemMailConfigurator
{
    /**
     * Apply SMTP from admin settings (Settings → SMTP) when enabled.
     * If disabled, Laravel keeps the mail driver from .env (e.g. smtp, log).
     */
    public static function applyFromSettings(): void
    {
        if (! (bool) SystemSetting::get('smtp.enabled', false)) {
            return;
        }

        $host = SystemSetting::get('smtp.host');
        if (empty($host)) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', SystemSetting::get('smtp.port', 587));
        Config::set('mail.mailers.smtp.username', SystemSetting::get('smtp.username'));
        Config::set('mail.mailers.smtp.password', SystemSetting::get('smtp.password'));

        $encryption = SystemSetting::get('smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);

        $fromAddress = SystemSetting::get('smtp.from_address');
        $fromName = SystemSetting::get('smtp.from_name', SystemSetting::get('branding.app_name', config('app.name')));

        if ($fromAddress) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * True if mail is configured to leave the server (not log/array only).
     */
    public static function mailDeliveryConfigured(): bool
    {
        self::applyFromSettings();

        $default = config('mail.default');

        return match ($default) {
            'log', 'array' => false,
            'smtp' => filled(config('mail.mailers.smtp.host')),
            default => true,
        };
    }
}
