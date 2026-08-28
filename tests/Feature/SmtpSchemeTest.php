<?php

use App\Models\SystemSetting;
use App\Services\SystemMailConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

uses(RefreshDatabase::class);

/**
 * The Encryption dropdown in Settings → SMTP has to actually decide how mail leaves.
 *
 * Symfony's mailer chooses its transport from the DSN *scheme*, and accepts only "smtp" or
 * "smtps". Laravel ignores `encryption` entirely — MailManager::createSmtpTransport reads
 * `scheme`, and falls back to the port when it is empty. This configurator only ever set
 * `encryption`, so the dropdown was inert: delivery was decided by MAIL_SCHEME in the
 * environment, or by the port, and never by what the admin saved.
 *
 * When that environment carried MAIL_SCHEME=tls — not a scheme Symfony knows — every send
 * threw UnsupportedSchemeException. Production's failed-jobs table filled with 158 of them,
 * and because password resets and login codes are queued mail like everything else, nobody
 * could get back into their account. No setting in the admin UI could reach the cause.
 *
 * So the property under test is not "a config key is written". It is that the saved choice
 * produces a scheme Symfony will accept, and that it wins over a hostile environment.
 */
function smtpOn(string $encryption, int $port = 587): void
{
    SystemSetting::set('smtp.enabled', '1');
    SystemSetting::set('smtp.host', 'mail.example.com');
    SystemSetting::set('smtp.port', (string) $port);
    SystemSetting::set('smtp.encryption', $encryption);
}

it('maps every encryption choice to a scheme symfony accepts', function (string $encryption, string $expected) {
    smtpOn($encryption);

    SystemMailConfigurator::applyFromSettings();

    expect(config('mail.mailers.smtp.scheme'))->toBe($expected);
})->with([
    // STARTTLS: connect in the clear, upgrade after. The common 587 setup.
    'tls' => ['tls', 'smtp'],
    // Implicit TLS: encrypted from the first byte. The common 465 setup.
    'ssl' => ['ssl', 'smtps'],
    // No upgrade offered — but the transport is still smtp, not an empty or invented scheme.
    'none' => ['none', 'smtp'],
]);

it('never leaves a scheme symfony will refuse', function (string $encryption) {
    smtpOn($encryption);

    SystemMailConfigurator::applyFromSettings();

    // The exact failure that took production's mail down: anything outside this pair throws
    // UnsupportedSchemeException on every single send.
    expect(config('mail.mailers.smtp.scheme'))->toBeIn(['smtp', 'smtps']);
})->with(['tls', 'ssl', 'none']);

it('actually builds a mail transport, in the state production was in', function (string $encryption, int $port) {
    // The assertion that matters. Everything above checks a string; this constructs the real
    // Symfony transport the queue would use, with MAIL_SCHEME=tls in the environment — the
    // exact configuration that threw UnsupportedSchemeException on all 158 failed jobs.
    // Building it does not open a socket, so no mail server is needed to prove this.
    Config::set('mail.mailers.smtp.scheme', 'tls');

    smtpOn($encryption, $port);
    SystemMailConfigurator::applyFromSettings();

    // The manager memoises mailers, so it has to be rebuilt after the config changes.
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');

    $transport = Mail::mailer('smtp')->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class);
})->with([
    'starttls on 587' => ['tls', 587],
    'implicit tls on 465' => ['ssl', 465],
    'unencrypted on 25' => ['none', 25],
]);

it('overrides a hostile MAIL_SCHEME from the environment', function () {
    // The state production was actually in. An admin on managed hosting has no shell and no
    // way to edit .env; the settings page is the only lever they have, so it has to be the
    // one that wins.
    Config::set('mail.mailers.smtp.scheme', 'tls');

    smtpOn('tls');
    SystemMailConfigurator::applyFromSettings();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtp');
});

it('leaves the mailer alone when smtp is switched off', function () {
    // Off means "use whatever .env says" — usually log in development. Reaching into the
    // config here would silently redirect mail nobody asked us to touch.
    SystemSetting::set('smtp.enabled', '0');
    Config::set('mail.default', 'log');
    Config::set('mail.mailers.smtp.scheme', null);

    SystemMailConfigurator::applyFromSettings();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers.smtp.scheme'))->toBeNull();
});

it('leaves the mailer alone when no host is saved', function () {
    // Enabled but blank is a half-finished form, not an instruction to send through nowhere.
    SystemSetting::set('smtp.enabled', '1');
    SystemSetting::set('smtp.host', '');
    Config::set('mail.default', 'log');

    SystemMailConfigurator::applyFromSettings();

    expect(config('mail.default'))->toBe('log');
});
