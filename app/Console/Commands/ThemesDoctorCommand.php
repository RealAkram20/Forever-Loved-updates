<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Models\Theme;
use App\Themes\ThemeRegistry;
use App\Themes\ThemeShadows;
use Illuminate\Console\Command;

/**
 * Finds the failures the conformance suite cannot see.
 *
 * ThemeConformanceTest catches *breakage*: a template that 500s, drops its footer, or walks a
 * visitor onto the platform. It cannot catch *drift* — a template that shadows a view, the
 * original moving on, and the template quietly continuing to serve the old behaviour. Nothing
 * throws; the page renders; the tests pass. With one template that is a thought experiment.
 * With the ten that are planned it is a certainty, and the templates that rot are the ones
 * nobody is looking at.
 *
 *   php artisan themes:doctor                        # check all, non-zero exit on drift
 *   php artisan themes:doctor dignified              # check one
 *   php artisan themes:doctor dignified --record     # re-baseline after reviewing the change
 *
 * Exits non-zero on drift so CI can gate it. Catalogue disagreements are reported but do not
 * fail the command: they are database state, and the database CI runs against is not the one
 * serving anybody's site. Use --strict when the check is running against a real one.
 */
class ThemesDoctorCommand extends Command
{
    protected $signature = 'themes:doctor
        {template? : Check only this template, instead of every one on disk}
        {--record : Re-baseline against the current resources/views}
        {--strict : Also fail on catalogue disagreements, not just drift}';

    protected $description = 'Report templates that have drifted from the views they shadow';

    public function handle(ThemeRegistry $registry): int
    {
        $templates = $registry->all();

        if ($only = $this->argument('template')) {
            if (! isset($templates[$only])) {
                $this->error("No template '{$only}' in ".ThemeRegistry::root().'.');

                return self::FAILURE;
            }

            $templates = [$only => $templates[$only]];
        }

        if ($templates === []) {
            $this->error('No templates found in '.ThemeRegistry::root().'.');

            return self::FAILURE;
        }

        if ($this->option('record')) {
            return $this->record($templates);
        }

        $problems = 0;
        $advisories = 0;
        $rows = [];

        foreach ($templates as $template => $manifest) {
            $scan = ThemeShadows::scan($template);

            // `basic` is `resources/views` itself. A blade under themes/basic/ would shadow
            // the platform's own site with a copy of it — which is exactly the arrangement
            // that was built once and reverted, because it meant the main website rendered
            // from inside the reseller theme system. Cheap to assert, expensive to rediscover.
            if ($template === ThemeRegistry::BASE && ThemeShadows::bladesIn($template) !== []) {
                $this->error(sprintf(
                    "'%s' ships blades. It must ship a manifest and nothing else — it *is* resources/views, "
                    .'and a blade here serves the platform its own site through the tenant theme system.',
                    $template
                ));
                $problems++;
            }

            // A manifest can also promise a file it does not ship. Dignified declared
            // `preview.webp` and shipped none, so every gallery card for it rendered a broken
            // image — visible on the one screen whose whole job is showing what a theme looks
            // like, and invisible to every test, because the page still returned 200.
            if ($manifest->screenshot !== null
                && ! is_file(ThemeRegistry::path($template).'/'.$manifest->screenshot)) {
                $advisories++;
                $this->warn(sprintf(
                    "Template '%s' declares screenshot '%s' but does not ship it; its gallery card shows a broken image.",
                    $template,
                    $manifest->screenshot,
                ));
            }

            $drifted = $this->relativesWithStatus($scan, ThemeShadows::DRIFTED);
            $unrecorded = $this->relativesWithStatus($scan, ThemeShadows::UNRECORDED);

            $rows[] = [
                $template,
                count($scan['shadows']) ?: '—',
                count($scan['own']) ?: '—',
                $this->summarise($drifted, $unrecorded, $scan['stale']),
            ];

            $problems += count($drifted) + count($unrecorded) + count($scan['stale']);

            $this->detail($template, $drifted, $unrecorded, $scan['stale']);
        }

        $this->table(['Template', 'Shadows', 'Own views', 'Status'], $rows);

        $advisories += $this->catalogue($templates);

        if ($problems > 0) {
            $this->newLine();
            $this->error($problems.' '.str('issue')->plural($problems).' found. '
                .'Review each changed original against the template that shadows it, then run '
                .'`themes:doctor --record` and commit the result.');

            return self::FAILURE;
        }

        if ($advisories > 0 && $this->option('strict')) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Every template is in step with the views it shadows.');

        return self::SUCCESS;
    }

    /**
     * The per-template detail, printed above the summary table so the table reads as the
     * conclusion rather than as the report.
     *
     * @param  array<int, string>  $drifted
     * @param  array<int, string>  $unrecorded
     * @param  array<int, string>  $stale
     */
    private function detail(string $template, array $drifted, array $unrecorded, array $stale): void
    {
        foreach ($drifted as $relative) {
            $this->line(sprintf(
                "  <fg=red>drifted</>    %s/%s\n             the original at %s has changed since this template was written against it",
                $template,
                $relative,
                'resources/views/'.$relative,
            ));
        }

        foreach ($unrecorded as $relative) {
            $this->line(sprintf(
                '  <fg=yellow>unrecorded</> %s/%s — shadows a default view with no baseline, so drift in it cannot be detected',
                $template,
                $relative,
            ));
        }

        foreach ($stale as $relative) {
            $this->line(sprintf(
                '  <fg=yellow>stale</>      %s/%s — a baseline for a view this template no longer shadows',
                $template,
                $relative,
            ));
        }
    }

    /**
     * @param  array<int, string>  $drifted
     * @param  array<int, string>  $unrecorded
     * @param  array<int, string>  $stale
     */
    private function summarise(array $drifted, array $unrecorded, array $stale): string
    {
        $parts = [];

        if ($drifted !== []) {
            $parts[] = count($drifted).' drifted';
        }

        if ($unrecorded !== []) {
            $parts[] = count($unrecorded).' unrecorded';
        }

        if ($stale !== []) {
            $parts[] = count($stale).' stale';
        }

        return $parts === [] ? 'in step' : implode(', ', $parts);
    }

    /**
     * @param  array{shadows: array<string, array{status: string, recorded: ?string, current: ?string}>}  $scan
     * @return array<int, string>
     */
    private function relativesWithStatus(array $scan, string $status): array
    {
        return array_keys(array_filter($scan['shadows'], fn ($state) => $state['status'] === $status));
    }

    /**
     * The two ways disk and catalogue disagree. Same questions the admin screen at
     * /settings/themes answers; asked here so CI can ask them without a browser.
     *
     * @param  array<string, \App\Themes\ThemeManifest>  $templates
     */
    private function catalogue(array $templates): int
    {
        $found = 0;

        $rows = Theme::whereNull('reseller_id')->pluck('template')->all();

        foreach (array_diff(array_keys($templates), $rows) as $template) {
            // On disk, no row: nobody can select it. Silent, and indistinguishable from
            // "we never shipped that theme" unless someone says so.
            $this->warn("Template '{$template}' is on disk but not in the catalogue; no reseller can choose it. Run `themes:sync`.");
            $found++;
        }

        // Only when looking at everything. Narrowed to one template, every *other* catalogue
        // row is trivially "not on disk" as far as this list knows, and reporting them all as
        // orphans would make `themes:doctor dignified` a wall of noise about themes it was
        // never asked about.
        $orphans = $this->argument('template') ? [] : array_diff($rows, array_keys($templates));

        foreach ($orphans as $template) {
            // Same count the admin screen shows, by the same route: there is no themes→resellers
            // relation, because theme_id is nullOnDelete and a hasMany here would invite the
            // cascade that deleting a theme must never do.
            $orphan = Theme::whereNull('reseller_id')->where('template', $template)->first();
            $inUse = $orphan ? Reseller::where('theme_id', $orphan->id)->count() : 0;

            $this->warn("Catalogue theme '{$template}' has no template on disk; "
                .($inUse > 0 ? $inUse.' site(s) using it are' : 'sites using it would be')
                .' rendering as '.ThemeRegistry::BASE.'.');
            $found++;
        }

        return $found;
    }

    /**
     * @param  array<string, \App\Themes\ThemeManifest>  $templates
     */
    private function record(array $templates): int
    {
        $rows = [];

        foreach ($templates as $template => $manifest) {
            $result = ThemeShadows::record($template);

            $rows[] = [
                $template,
                $result['recorded'] ?: '—',
                $result['removed'] ?: '—',
            ];
        }

        $this->table(['Template', 'Baselines written', 'Stale entries dropped'], $rows);
        $this->info('Baselines rewritten. Commit the theme.json changes — they are what the next check compares against.');

        return self::SUCCESS;
    }
}
