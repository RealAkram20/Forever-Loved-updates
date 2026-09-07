<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Support\JunkUserPurge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sort the failed-jobs table into "safe to drop" and "worth retrying", by recipient.
 *
 * After the 2026-09-04 relay the dashboard showed 4,444 failed jobs, nearly all of them the
 * same line: Hostinger's outbound cap, `451 4.7.1 Ratelimit "hostinger_out_ratelimit"`. Those
 * were overwhelmingly the attack's own mail — welcome messages to the victims, and "new user"
 * notices to admins about accounts that were never people — which Hostinger declined to send.
 * The cap doing that was the best thing that happened that day.
 *
 * The dashboard offers two buttons and both are wrong for this. **Retry all** would try to
 * send the phishing again. **Clear them** (`queue:flush`) drops everything, including any real
 * family's notification that failed in the same window and deserves a retry once the cap
 * lifts. Neither looks at *who the mail was to*, which is the only thing that distinguishes the
 * two.
 *
 * This does. A `SendRawEmail` carries its recipient in the serialized payload; a
 * `SendNotificationEmail` carries a notification id whose row names the account it is about.
 * A recipient that is no longer a user — or still is, and matches JunkUserPurge's definition —
 * is junk. Everything else is kept.
 *
 * Read-only unless `--purge-junk` is passed. Run it bare first.
 */
class TriageFailedJobs extends Command
{
    protected $signature = 'queue:failed-triage
                            {--purge-junk : Delete the failed jobs classified as junk; keep the rest}
                            {--sample=8 : How many examples to print per class}';

    protected $description = 'Classify failed jobs by recipient — junk relay mail vs mail worth retrying — and optionally drop the junk';

    /** @var array<string, array{n:int, examples:array<int,string>}> */
    private array $buckets = [];

    /** @var array<int> */
    private array $junkIds = [];

    public function handle(): int
    {
        $total = DB::table('failed_jobs')->count();

        if ($total === 0) {
            $this->info('No failed jobs.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%s failed %s.', number_format($total), $total === 1 ? 'job' : 'jobs'));
        $this->newLine();

        // The same first-line grouping the dashboard banner uses, so the numbers agree.
        $reasons = [];

        DB::table('failed_jobs')->select('id', 'payload', 'exception')->orderBy('id')->chunkById(500, function ($rows) use (&$reasons) {
            foreach ($rows as $row) {
                $first = trim(strtok((string) $row->exception, "\n"));
                $first = preg_replace('/ in [^\s]+:\d+$/', '', $first) ?? $first;
                // 300, as the dashboard banner keeps, and not less: the provider's own error
                // token ("hostinger_out_ratelimit") sits ~140 characters into the 451 line,
                // and a reason that truncates before it has thrown away the diagnosis.
                $key = mb_substr($first, 0, 300);
                $reasons[$key] = ($reasons[$key] ?? 0) + 1;

                [$bucket, $example] = $this->classify($row->payload);
                $this->put($bucket, $example);

                if (str_starts_with($bucket, 'junk')) {
                    $this->junkIds[] = (int) $row->id;
                }
            }
        });

        arsort($reasons);
        $this->line('<comment>By reason</comment>');
        foreach (array_slice($reasons, 0, 5, true) as $reason => $n) {
            $this->line(sprintf('  %6s  %s', number_format($n), $reason));
        }
        $this->newLine();

        $this->line('<comment>By recipient</comment>');
        $labels = [
            'junk:welcome' => 'welcome mail to an address that is no longer a user, or is a suspicious one',
            'junk:admin-notice' => '"new user" notice to admins about an account that was junk',
            'keep:mail' => 'mail to a real, current user — worth retrying once the cap has lifted',
            'keep:other' => 'not mail (or recipient unknown) — left alone',
        ];
        foreach ($labels as $bucket => $label) {
            $n = $this->buckets[$bucket]['n'] ?? 0;
            $this->line(sprintf('  %6s  %-18s %s', number_format($n), $bucket, $label));
            foreach (array_slice($this->buckets[$bucket]['examples'] ?? [], 0, (int) $this->option('sample')) as $ex) {
                $this->line("            · {$ex}");
            }
        }
        $this->newLine();

        $pending = DB::table('jobs')->count();
        if ($pending > 0) {
            $this->warn(sprintf(
                '%s %s still pending in the queue. If those are mail, they will hit the same cap when a worker picks them up.',
                number_format($pending),
                $pending === 1 ? 'job is' : 'jobs are'
            ));
        }

        $junk = count($this->junkIds);

        if (! $this->option('purge-junk')) {
            $this->comment(sprintf(
                '%s junk. Nothing deleted. Re-run with --purge-junk to drop those and keep the %s worth retrying.',
                number_format($junk),
                number_format($total - $junk)
            ));

            return self::SUCCESS;
        }

        foreach (array_chunk($this->junkIds, 500) as $chunk) {
            DB::table('failed_jobs')->whereIn('id', $chunk)->delete();
        }

        $this->info(sprintf(
            'Deleted %s junk failed %s. %s kept for retry (queue:retry all, once the provider cap has cleared).',
            number_format($junk),
            $junk === 1 ? 'job' : 'jobs',
            number_format($total - $junk)
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{0:string,1:string}  bucket, and a one-line example for the report
     */
    private function classify(string $payload): array
    {
        $decoded = json_decode($payload, true);
        $display = (string) ($decoded['displayName'] ?? '');
        $command = (string) ($decoded['data']['command'] ?? '');

        if ($command === '') {
            return ['keep:other', $display ?: '(unreadable payload)'];
        }

        // Scalars are read straight off the serialized string rather than unserialize()d: the
        // job classes exist, but nested Carbon/ModelIdentifier objects make a full unserialize
        // both heavier and touchier than a regex for two string properties.
        if (str_ends_with($display, 'SendRawEmail')) {
            $to = $this->serializedString($command, 'to');

            if ($to === null) {
                return ['keep:other', "{$display} (no recipient)"];
            }

            return $this->isJunkRecipient($to)
                ? ['junk:welcome', $to]
                : ['keep:mail', $to];
        }

        if (str_ends_with($display, 'SendNotificationEmail')) {
            $id = $this->serializedInt($command, 'notificationId');
            $notification = $id ? Notification::find($id) : null;

            if (! $notification) {
                // The notification row is gone (cascade from a purged user, or cleaned up).
                // Nothing left to send about; it cannot be retried meaningfully.
                return ['junk:admin-notice', "notification #{$id} (row gone)"];
            }

            $data = is_array($notification->data) ? $notification->data : (json_decode((string) $notification->data, true) ?: []);
            $about = $data['user_email'] ?? null;

            if ($about && $this->isJunkRecipient($about)) {
                return ['junk:admin-notice', "about {$about}"];
            }

            $recipient = User::find($notification->user_id)?->email;

            return $recipient
                ? ['keep:mail', "{$recipient} ({$notification->type})"]
                : ['keep:other', "{$display} #{$id}"];
        }

        return ['keep:other', $display ?: '(unknown job)'];
    }

    /**
     * Junk if nobody by that address is a user any more, or if the user that is matches the
     * suspicious-account definition. A real family's failed welcome mail passes both.
     */
    private function isJunkRecipient(string $email): bool
    {
        $user = User::where('email', strtolower(trim($email)))->first();

        if (! $user) {
            return true;
        }

        return JunkUserPurge::scope(User::whereKey($user->id))->exists();
    }

    private function serializedString(string $serialized, string $prop): ?string
    {
        // s:2:"to";s:19:"victim@example.test";
        if (preg_match('/s:'.strlen($prop).':"'.preg_quote($prop, '/').'";s:(\d+):"/', $serialized, $m, PREG_OFFSET_CAPTURE)) {
            $len = (int) $m[1][0];
            $start = $m[0][1] + strlen($m[0][0]);

            return substr($serialized, $start, $len);
        }

        return null;
    }

    private function serializedInt(string $serialized, string $prop): ?int
    {
        if (preg_match('/s:'.strlen($prop).':"'.preg_quote($prop, '/').'";i:(\d+);/', $serialized, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function put(string $bucket, string $example): void
    {
        $this->buckets[$bucket]['n'] = ($this->buckets[$bucket]['n'] ?? 0) + 1;

        if (count($this->buckets[$bucket]['examples'] ?? []) < 20) {
            $this->buckets[$bucket]['examples'][] = $example;
        }
    }
}
