<?php

use App\Jobs\SendNotificationEmail;
use App\Jobs\SendRawEmail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Sorting the failed-jobs table by who the mail was to.
 *
 * The rows here are shaped the way Laravel actually writes them — displayName plus a PHP-
 * serialized command — with the exact 451 line Hostinger returned, so the command is proven
 * against the real table shape and not a tidy fixture.
 */
uses(RefreshDatabase::class);

const RATE_LIMIT_451 = 'Symfony\Component\Mailer\Exception\UnexpectedResponseException: Expected response code "250" but got code "451", with message "451 4.7.1 Ratelimit "hostinger_out_ratelimit" exceeded for key "RLnwkd3rjwooxjsc6s11if1fnm"". in /app/vendor/symfony/mailer/Transport/Smtp/SmtpTransport.php:340';

function failedRow(object $job, string $exception = RATE_LIMIT_451): int
{
    return DB::table('failed_jobs')->insertGetId([
        'uuid' => (string) \Illuminate\Support\Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'displayName' => get_class($job),
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'data' => ['commandName' => get_class($job), 'command' => serialize($job)],
        ]),
        'exception' => $exception,
        'failed_at' => now(),
    ]);
}

function welcomeTo(string $email): SendRawEmail
{
    return new SendRawEmail(to: $email, name: null, subject: 'Welcome', body: 'Hello');
}

it('classifies by recipient and reports without deleting', function () {
    $grace = User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);
    $junkUser = User::factory()->create(['name' => 'Claim funds at graph.org/x', 'email' => 'suspicious@example.test']);

    $a = failedRow(welcomeTo('deleted-victim@example.test'));   // recipient no longer a user → junk
    $b = failedRow(welcomeTo('suspicious@example.test'));        // recipient exists but is suspicious → junk
    $c = failedRow(welcomeTo('grace@example.test'));             // real user → keep
    $d = failedRow(new \App\Jobs\GenerateImageDerivatives(1));   // not mail → keep:other

    $this->artisan('queue:failed-triage')
        ->expectsOutputToContain('4 failed jobs')
        ->expectsOutputToContain('hostinger_out_ratelimit')
        ->expectsOutputToContain('2  junk:welcome')
        ->expectsOutputToContain('1  keep:mail')
        ->expectsOutputToContain('1  keep:other')
        ->expectsOutputToContain('2 junk. Nothing deleted.')
        ->assertSuccessful();

    expect(DB::table('failed_jobs')->count())->toBe(4);
})->skip(fn () => ! class_exists(\App\Jobs\GenerateImageDerivatives::class), 'no non-mail job to use');

it('treats an admin "new user" notice about a junk account as junk, and one about a real account as keepable', function () {
    $admin = User::factory()->create(['email' => 'admin@example.test']);
    $grace = User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);

    $aboutJunk = Notification::create([
        'user_id' => $admin->id, 'type' => 'new_user_signup', 'title' => 'New user', 'message' => 'x',
        'data' => ['user_id' => 999, 'user_name' => 'graph.org/spam', 'user_email' => 'gone-victim@example.test'],
    ]);
    $aboutGrace = Notification::create([
        'user_id' => $admin->id, 'type' => 'new_user_signup', 'title' => 'New user', 'message' => 'x',
        'data' => ['user_id' => $grace->id, 'user_name' => 'Grace Namutebi', 'user_email' => 'grace@example.test'],
    ]);

    failedRow(new SendNotificationEmail($aboutJunk->id));
    failedRow(new SendNotificationEmail($aboutGrace->id));
    failedRow(new SendNotificationEmail(424242)); // notification row gone entirely

    $this->artisan('queue:failed-triage')
        ->expectsOutputToContain('2  junk:admin-notice')
        ->expectsOutputToContain('1  keep:mail')
        ->assertSuccessful();
});

it('purges only the junk and keeps the rest for retry', function () {
    $grace = User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);

    $junk1 = failedRow(welcomeTo('victim-one@example.test'));
    $junk2 = failedRow(welcomeTo('victim-two@example.test'));
    $keep = failedRow(welcomeTo('grace@example.test'));

    $this->artisan('queue:failed-triage', ['--purge-junk' => true])
        ->expectsOutputToContain('Deleted 2 junk failed jobs. 1 kept for retry')
        ->assertSuccessful();

    expect(DB::table('failed_jobs')->pluck('id')->all())->toBe([$keep]);
});

it('warns when jobs are still pending, because they will hit the same cap', function () {
    User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);
    failedRow(welcomeTo('grace@example.test'));
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
    ]);

    $this->artisan('queue:failed-triage')
        ->expectsOutputToContain('1 job is still pending')
        ->assertSuccessful();
});

it('does nothing and says so when the table is empty', function () {
    $this->artisan('queue:failed-triage', ['--purge-junk' => true])
        ->expectsOutput('No failed jobs.')
        ->assertSuccessful();
});
