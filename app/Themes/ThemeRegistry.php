<?php

namespace App\Themes;

/**
 * The templates that exist on disk.
 *
 * `themes/{template}/` mirrors only the `resources/views/` paths it wants to
 * replace — a template that changes the header and the hero is two files. Everything it does
 * not provide falls through to `basic`, and then to `resources/views`, so there is no
 * scaffolding to copy and no obligation to be complete. That fallback is the whole reason
 * this is a view-path layer rather than a forked view tree: without it, every template would
 * own 24 blades and the second one would start rotting the day the first feature landed.
 */
class ThemeRegistry
{
    /**
     * The base template every other one falls back to, and the one the platform itself runs.
     *
     * It is not "the default in case something is missing" — it is where the reseller-facing
     * site actually lives now. `resources/views` keeps only what no template owns.
     */
    public const BASE = 'basic';

    /** @var array<string, ThemeManifest>|null */
    private ?array $manifests = null;

    /**
     * `<project>/themes`, not `resources/themes`.
     *
     * Templates are a thing people hand each other — a designer builds one, a deploy drops one
     * in — so they sit at the top level where they can be found without knowing Laravel's
     * layout. The cost is that Tailwind no longer sees them for free: `resources/css/app.css`
     * scans `resources/**`, so this directory needs its own `@source` line. Without it a
     * template's blades generate no CSS at all and the site renders unstyled, which is a much
     * louder failure than it sounds like.
     */
    public static function root(): string
    {
        return base_path('themes');
    }

    public static function path(string $template = ''): string
    {
        return $template === '' ? self::root() : self::root().DIRECTORY_SEPARATOR.$template;
    }

    public static function isValidSlug(string $template): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9\-]{0,48}$/', $template);
    }

    /**
     * A template exists when its directory holds a theme.json. The slug check is not
     * cosmetic: this value reaches a filesystem path, and it arrives from a database column
     * that an admin edits.
     */
    public function exists(string $template): bool
    {
        return self::isValidSlug($template) && is_file(self::path($template).'/theme.json');
    }

    /**
     * @return array<string, ThemeManifest> keyed by template slug
     */
    public function all(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $out = [];

        foreach (glob(self::root().'/*/theme.json') ?: [] as $file) {
            $template = basename(dirname($file));

            if (! self::isValidSlug($template)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($file), true);

            if (! is_array($decoded)) {
                continue;
            }

            $out[$template] = ThemeManifest::fromArray($template, $decoded);
        }

        // Base first, then alphabetical — the gallery reads as "what you have now, then what
        // else there is", rather than in whatever order the filesystem happens to answer in.
        uksort($out, function (string $a, string $b) {
            if ($a === self::BASE || $b === self::BASE) {
                return $a === self::BASE ? -1 : 1;
            }

            return strcmp($a, $b);
        });

        return $this->manifests = $out;
    }

    public function manifest(string $template): ?ThemeManifest
    {
        return $this->all()[$template] ?? null;
    }
}
