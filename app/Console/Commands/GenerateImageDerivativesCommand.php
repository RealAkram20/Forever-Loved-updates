<?php

namespace App\Console\Commands;

use App\Services\ImageDerivativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill: every image uploaded before the derivative pipeline existed gets its
 * WebP ladder. Safe to re-run — existing derivatives are skipped, so a second pass
 * costs directory listings and nothing else.
 */
class GenerateImageDerivativesCommand extends Command
{
    protected $signature = 'images:derivatives
        {--dry-run : List what would be generated without touching anything}';

    protected $description = 'Generate missing WebP derivatives for every uploaded image';

    public function handle(ImageDerivativeService $derivatives): int
    {
        $disk = Storage::disk('public');

        $sources = collect($disk->allFiles())
            // d/ holds output, share/ holds og-images; neither is a source.
            ->reject(fn (string $path) => str_contains($path, '/d/') || str_contains($path, '/share/'))
            ->filter(fn (string $path) => ImageDerivativeService::eligible($path))
            ->values();

        $this->info($sources->count().' source images found.');

        $made = 0;

        foreach ($sources as $path) {
            if ($this->option('dry-run')) {
                $this->line("would process: {$path}");

                continue;
            }

            $count = count($derivatives->generate($path));
            $made += $count;

            if ($count > 0) {
                $this->line("{$path} → {$count} sizes");
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run - nothing written.' : "Done. {$made} derivatives now on disk.");

        return self::SUCCESS;
    }
}
