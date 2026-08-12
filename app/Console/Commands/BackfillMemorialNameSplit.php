<?php

namespace App\Console\Commands;

use App\Models\Memorial;
use Illuminate\Console\Command;

/**
 * The reseller intake screen used to collect one combined "Full Name of the Deceased"
 * and never populated first_name / last_name. Memorial's accessors paper over this by
 * parsing full_name on read, so a two- or three-part name still renders correctly —
 * but a single-token name parses to a last name of '', and the edit form requires one.
 * A client invited to a memorial recorded as "Prince" could not save that form at all.
 *
 * Intake collects the parts now. This stores them for the rows created before it did,
 * so the columns hold the real values rather than leaning on a legacy fallback.
 * Safe to re-run: only rows whose first_name column is null are touched, and a row
 * with no full_name is left alone.
 */
class BackfillMemorialNameSplit extends Command
{
    protected $signature = 'memorials:backfill-name-split
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Populate first/middle/last name on memorials that only have a combined full_name';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Memorial::query()
            ->whereNull('first_name')
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '');

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to backfill — every memorial already has its name split.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Would update ' : 'Updating ').$total.' memorial(s).');

        $rows = [];
        $updated = 0;

        $query->orderBy('id')->chunkById(200, function ($memorials) use ($dryRun, &$rows, &$updated) {
            foreach ($memorials as $memorial) {
                $parts = $this->split((string) $memorial->full_name);

                if ($parts === null) {
                    continue;
                }

                $rows[] = [$memorial->id, $memorial->full_name, $parts['first_name'], $parts['middle_name'] ?? '—', $parts['last_name']];
                $updated++;

                if (! $dryRun) {
                    // Quietly: this is a data repair, not an edit anyone made.
                    $memorial->updateQuietly($parts);
                }
            }
        });

        if ($rows !== []) {
            $this->table(['ID', 'Full name', 'First', 'Middle', 'Last'], array_slice($rows, 0, 25));

            if (count($rows) > 25) {
                $this->line('… and '.(count($rows) - 25).' more.');
            }
        }

        $this->info($dryRun
            ? "Dry run complete — {$updated} memorial(s) would be updated."
            : "Done — {$updated} memorial(s) updated.");

        return self::SUCCESS;
    }

    /**
     * First token, last token, everything between as the middle. A single-token name
     * ("Prince", a mononym, or a family that only gave one name) fills both first and
     * last, because the edit form requires both and refusing to guess would leave the
     * record exactly as broken as it is now.
     *
     * @return array{first_name: string, middle_name: ?string, last_name: string}|null
     */
    private function split(string $fullName): ?array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return ['first_name' => $parts[0], 'middle_name' => null, 'last_name' => $parts[0]];
        }

        $first = array_shift($parts);
        $last = array_pop($parts);

        return [
            'first_name' => $first,
            'middle_name' => $parts === [] ? null : implode(' ', $parts),
            'last_name' => $last,
        ];
    }
}
