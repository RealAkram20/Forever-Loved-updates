<?php

namespace App\Console\Commands;

use App\Support\JunkUserPurge;
use Illuminate\Console\Command;

/**
 * The console end of the same cleanup the admin Users screen offers, for volumes a browser
 * request cannot get through.
 *
 * The 2026-09-04 relay created accounts by the thousand. The admin screen removes 500 per
 * click, which is right for a screen and wrong for a backlog. This walks the same definition
 * (`JunkUserPurge::scope`) with the same refusals (memorial owners, payment history, staff,
 * protected) in chunks, and can be re-run until it reports nothing left.
 *
 * `--dry-run` is the default way to use it the first time: it prints a sample and the total
 * and touches nothing. Deleting user rows on a production database is not something to do
 * from a description of what the query probably matches.
 */
class PurgeSuspiciousUsers extends Command
{
    protected $signature = 'users:purge-suspicious
                            {--dry-run : List what would be deleted without deleting it}
                            {--limit=0 : Stop after this many deletions (0 = no limit)}
                            {--sample=15 : How many candidates to print in a dry run}';

    protected $description = 'Delete accounts whose name is a URL or a message, and which own nothing';

    public function handle(): int
    {
        $total = JunkUserPurge::query()->count();

        if ($total === 0) {
            $this->info('No suspicious accounts found.');

            return self::SUCCESS;
        }

        $this->line(sprintf('%s suspicious %s.', number_format($total), $total === 1 ? 'account' : 'accounts'));

        if ($this->option('dry-run')) {
            $sample = JunkUserPurge::query()->latest()->limit((int) $this->option('sample'))->get(['id', 'name', 'email', 'created_at']);

            $this->table(
                ['id', 'name (truncated)', 'email', 'created'],
                $sample->map(fn ($u) => [
                    $u->id,
                    mb_strimwidth($u->name, 0, 60, '…'),
                    $u->email,
                    $u->created_at?->toDateTimeString(),
                ])->all()
            );

            $this->comment('Dry run — nothing deleted. Re-run without --dry-run to delete.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $summary = ['deleted' => 0, 'skipped' => []];
        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);

        // chunkById rather than chunk: rows are being deleted out from under the cursor, and
        // offset paging would skip every other page. Ordering by id makes the walk stable.
        JunkUserPurge::query()->chunkById(200, function ($users) use (&$summary, $bar, $limit) {
            foreach ($users as $user) {
                if ($limit > 0 && $summary['deleted'] >= $limit) {
                    return false;
                }

                $one = JunkUserPurge::purge([$user], null);
                $summary['deleted'] += $one['deleted'];

                foreach ($one['skipped'] as $reason => $n) {
                    $summary['skipped'][$reason] = ($summary['skipped'][$reason] ?? 0) + $n;
                }

                if ($one['deleted']) {
                    $bar->advance();
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info(JunkUserPurge::describe($summary, JunkUserPurge::query()->count() ?: null));

        return self::SUCCESS;
    }
}
