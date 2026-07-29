<?php

namespace App\Console\Commands;

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Until Memorial's creating hook existed, reseller_id was stamped in exactly one place —
 * Reseller\DashboardController::storeMemorial(). A reseller's own client creating a memorial
 * through the normal /memorials or /create-memorial flow produced an untenanted record: not
 * in the reseller's list or analytics, not counted against their tier allowance or storage
 * cap, not served on their subdomain, and handed out with a platform URL.
 *
 * This attributes those existing rows to the tenant their owner belongs to. Safe to re-run:
 * it only touches memorials that still have no reseller_id.
 */
class BackfillMemorialResellerAttribution extends Command
{
    protected $signature = 'memorials:backfill-reseller-attribution
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Attribute existing memorials to the reseller their owner belongs to';

    public function handle(): int
    {
        // Deliberately NOT keyed off original_reseller_id: a memorial whose owner was rolled
        // over to direct platform ownership has a null reseller_id on purpose, and
        // re-attaching it would undo the rollover. Only the live owner link is trusted.
        $tenantedOwnerIds = User::whereNotNull('reseller_id')->pluck('reseller_id', 'id');

        if ($tenantedOwnerIds->isEmpty()) {
            $this->info('No users belong to a reseller — nothing to attribute.');

            return self::SUCCESS;
        }

        $query = Memorial::whereNull('reseller_id')->whereIn('user_id', $tenantedOwnerIds->keys());
        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to backfill — every reseller client\'s memorial is already attributed.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $this->info(($dryRun ? 'Would attribute ' : 'Attributing ').$total.' memorial(s).');
        $this->newLine();

        $perReseller = [];

        $query->chunkById(200, function ($memorials) use ($tenantedOwnerIds, $dryRun, &$perReseller) {
            foreach ($memorials as $memorial) {
                $resellerId = $tenantedOwnerIds[$memorial->user_id];

                if (! $dryRun) {
                    // updateQuietly: this is a data correction, not a content edit, and it
                    // must not bump timestamps or fire model events.
                    $memorial->updateQuietly([
                        'reseller_id' => $resellerId,
                        'original_reseller_id' => $memorial->original_reseller_id ?? $resellerId,
                    ]);
                }

                $perReseller[$resellerId] = ($perReseller[$resellerId] ?? 0) + 1;
            }
        });

        $names = Reseller::whereIn('id', array_keys($perReseller))->pluck('name', 'id');

        foreach ($perReseller as $resellerId => $count) {
            $this->line(sprintf('  %-40s %d', $names[$resellerId] ?? "reseller #{$resellerId}", $count));
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run — nothing written.' : 'Done.');

        // Newly attributed profiles can push a reseller past their included allowance, which
        // is a billable overage rather than an error — say so instead of letting it surface
        // as a surprise on the next invoice.
        if (! $dryRun) {
            foreach (Reseller::whereIn('id', array_keys($perReseller))->get() as $reseller) {
                if ($reseller->overageProfiles() > 0) {
                    $this->warn(sprintf(
                        '  %s is now %d profile(s) over the %s allowance.',
                        $reseller->name,
                        $reseller->overageProfiles(),
                        $reseller->tier?->name ?? 'current'
                    ));
                }
            }
        }

        return self::SUCCESS;
    }
}
