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

        // Symfony's mailer picks its transport from the DSN *scheme*, and only accepts "smtp"
        // or "smtps". Laravel ignores `encryption` outright — see MailManager::createSmtpTransport
        // — so setting it alone left this whole dropdown inert: whatever an admin chose,
        // delivery was decided by MAIL_SCHEME in the environment or, failing that, by the port.
        //
        // Worse, an environment carrying MAIL_SCHEME=tls throws
        // UnsupportedSchemeException on every send, and nothing in the admin UI could reach it.
        // That is what filled production's failed-jobs table, and it is why no password reset
        // or login code left the server: they are queued mail like everything else.
        //
        //   ssl  -> smtps  implicit TLS, the whole session encrypted from connect (usually 465)
        //   tls  -> smtp   STARTTLS, upgraded after connect (usually 587)
        //   none -> smtp   no upgrade offered; the transport is the same either way
        //
        // Set explicitly so the saved setting wins over a stale env var, which is the only way
        // an admin on managed hosting can fix this without a shell.
        Config::set('mail.mailers.smtp.scheme', $encryption === 'ssl' ? 'smtps' : 'smtp');

        // Still published for anything that reads it back for display; it no longer decides
        // anything.
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
