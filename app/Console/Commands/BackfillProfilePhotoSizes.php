<?php

namespace App\Console\Commands;

use App\Models\Memorial;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Profile photos uploaded before profile_photo_size existed have no recorded size, so any
 * storage total silently undercounts them. Reads each file back off disk once to fill the
 * gap. Safe to re-run: it only touches rows still missing a size.
 */
class BackfillProfilePhotoSizes extends Command
{
    protected $signature = 'memorials:backfill-photo-sizes {--dry-run : Report what would change without writing}';

    protected $description = 'Record file sizes for profile photos uploaded before sizes were tracked';

    public function handle(): int
    {
        $query = Memorial::whereNotNull('profile_photo_path')->whereNull('profile_photo_size');
        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to backfill — every profile photo already has a recorded size.');

            return self::SUCCESS;
        }

        $this->info(($this->option('dry-run') ? 'Would update ' : 'Updating ').$total.' memorial(s).');

        $disk = Storage::disk('public');
        $updated = 0;
        $missing = 0;

        $query->chunkById(200, function ($memorials) use ($disk, &$updated, &$missing) {
            foreach ($memorials as $memorial) {
                if (! $disk->exists($memorial->profile_photo_path)) {
                    // The row points at a file that is gone. Left alone rather than zeroed,
                    // so a re-run picks it up if the disk was merely unmounted.
                    $missing++;
                    $this->warn("  missing on disk: {$memorial->profile_photo_path}");

                    continue;
                }

                if (! $this->option('dry-run')) {
                    $memorial->updateQuietly(['profile_photo_size' => $disk->size($memorial->profile_photo_path)]);
                }

                $updated++;
            }
        });

        $this->newLine();
        $this->info("Done. {$updated} sized".($missing ? ", {$missing} missing on disk." : '.'));

        return self::SUCCESS;
    }
}
