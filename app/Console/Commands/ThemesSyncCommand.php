<?php

namespace App\Console\Commands;

use App\Models\Theme;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemeRegistry;
use Illuminate\Console\Command;

/**
 * Makes every template on disk selectable, and says which rows have lost their template.
 *
 * Run automatically by the deploy migration, so a theme that ships is a theme resellers can
 * pick the same day. Kept as a command as well because templates are edited between deploys
 * in development, and because the orphan report is worth being able to ask for on its own.
 */
class ThemesSyncCommand extends Command
{
    protected $signature = 'themes:sync';

    protected $description = 'Sync the theme catalogue with the templates in themes';

    public function handle(ThemeRegistry $registry): int
    {
        $templates = $registry->all();

        if ($templates === []) {
            $this->error('No templates found in '.ThemeRegistry::root().'.');

            return self::FAILURE;
        }

        $result = ThemeCatalogue::sync();

        $this->table(
            ['Template', 'Name', 'Tokens', 'Status'],
            collect($templates)->map(fn ($manifest, $template) => [
                $template,
                $manifest->name,
                count($manifest->tokens) ?: '—',
                in_array($template, $result['created'], true) ? 'created' : 'up to date',
            ])->values()->all()
        );

        foreach ($result['orphaned'] as $slug) {
            // Not an error the command should fail on — the site still renders, in the base
            // design. It is a thing somebody needs to know, which is a different thing.
            $this->warn("Catalogue theme '{$slug}' points at a template that is not deployed; "
                .'sites using it are rendering as '.ThemeRegistry::BASE.'.');
        }

        $usingMissing = Theme::query()
            ->whereNotIn('template', array_keys($templates))
            ->whereHas('reseller')
            ->count();

        if ($usingMissing > 0) {
            $this->warn("{$usingMissing} reseller-owned theme(s) also point at a missing template.");
        }

        return self::SUCCESS;
    }
}
