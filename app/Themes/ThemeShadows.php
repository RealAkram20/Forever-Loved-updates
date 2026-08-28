<?php

namespace App\Themes;

/**
 * Which default views a template replaces, and whether the originals have moved on since.
 *
 * The cascade's economy is that a template mirrors only the `resources/views/` paths it wants
 * to change and inherits the rest. That is also its one silent failure mode: a template
 * shadows a view, the original is fixed six months later, and the template keeps serving the
 * old behaviour on a real funeral home's site. Nothing throws. Nothing 500s. The conformance
 * suite passes, because the page still renders — it just renders last year's version.
 *
 * So each template records, in its own `theme.json`, a fingerprint of the *original* it was
 * written against. `themes:doctor` compares those against what `resources/views` holds today
 * and fails when they disagree. Drift becomes a build failure instead of a discovery.
 *
 * The fingerprint is of the original, never of the template's own copy. A template is
 * *supposed* to differ from what it replaces — that is the entire point of it. What matters
 * is whether the thing it was derived from has changed underneath it.
 */
class ThemeShadows
{
    /** A shadow whose original is byte-for-byte what the template was written against. */
    public const OK = 'ok';

    /** The original has changed since. The template may be missing whatever changed in it. */
    public const DRIFTED = 'drifted';

    /** Shadowed, but never recorded — so nothing can be said about it yet. */
    public const UNRECORDED = 'unrecorded';

    /** Recorded, but the original is gone: a rename, a deletion, or a stale entry. */
    public const VANISHED = 'vanished';

    /**
     * Sixteen hex characters of a SHA-256, not all sixty-four.
     *
     * This guards against a file changing by accident, not against someone forging a
     * collision — the input is a blade in our own repository. Sixteen is short enough that a
     * `theme.json` diff stays readable, which is what makes anyone actually look at it.
     */
    public const FINGERPRINT_LENGTH = 16;

    /**
     * A fingerprint that does not depend on which machine checked the file out.
     *
     * Line endings are normalised first: this repository is developed on Windows and built on
     * Linux, and without this every shadow would read as drifted on one of the two — a doctor
     * that cries wolf on a fresh clone is a doctor everybody learns to ignore.
     */
    public static function fingerprint(string $file): ?string
    {
        if (! is_file($file)) {
            return null;
        }

        $contents = (string) file_get_contents($file);
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);

        return substr(hash('sha256', $contents), 0, self::FINGERPRINT_LENGTH);
    }

    /** Every `*.blade.php` a template ships, as paths relative to the template root. */
    public static function bladesIn(string $template): array
    {
        $root = ThemeRegistry::path($template);

        if (! is_dir($root)) {
            return [];
        }

        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $root)) + 1);
            $found[] = $relative;
        }

        sort($found);

        return $found;
    }

    /** Where a template blade's original lives, whether or not it exists. */
    public static function originalPath(string $relative): string
    {
        return resource_path('views/'.$relative);
    }

    /**
     * What this template shadows, what it owns outright, and where it has drifted.
     *
     * "Owns" is not a problem to report — a template is free to bring views the platform has
     * no concept of (Dignified's `sections/framed-image`). They are listed only so the ratio
     * is visible: a template that is mostly shadows is a fork wearing a cascade's clothes.
     *
     * @return array{
     *     shadows: array<string, array{status: string, recorded: ?string, current: ?string}>,
     *     own: array<int, string>,
     *     stale: array<int, string>,
     * }
     */
    public static function scan(string $template): array
    {
        $recorded = self::recorded($template);
        $shadows = [];
        $own = [];

        foreach (self::bladesIn($template) as $relative) {
            $original = self::originalPath($relative);

            if (! is_file($original)) {
                // Not a shadow at all — a view this template brings itself. It cannot drift,
                // because there is nothing underneath it to drift from.
                $own[] = $relative;

                continue;
            }

            $current = self::fingerprint($original);
            $was = $recorded[$relative] ?? null;

            $shadows[$relative] = [
                'status' => match (true) {
                    $was === null => self::UNRECORDED,
                    $was === $current => self::OK,
                    default => self::DRIFTED,
                },
                'recorded' => $was,
                'current' => $current,
            ];
        }

        // Recorded against a path the template no longer ships, or an original that has since
        // been deleted or renamed. Either way the entry is now a lie, and a lie in the
        // baseline is worse than a missing one — it reads as "checked and fine".
        $stale = [];

        foreach (array_keys($recorded) as $relative) {
            if (! isset($shadows[$relative])) {
                $stale[] = $relative;
            }
        }

        sort($stale);

        return ['shadows' => $shadows, 'own' => $own, 'stale' => $stale];
    }

    /**
     * The fingerprints a template's `theme.json` currently claims.
     *
     * Read from the raw file rather than through ThemeManifest so that `--record` and the
     * check read exactly the same bytes, and so a malformed manifest degrades to "nothing
     * recorded" rather than to a fatal.
     *
     * @return array<string, string>
     */
    public static function recorded(string $template): array
    {
        $decoded = self::manifestJson($template);
        $shadows = $decoded['shadows'] ?? null;

        if (! is_array($shadows)) {
            return [];
        }

        $out = [];

        foreach ($shadows as $relative => $fingerprint) {
            if (is_string($relative) && is_string($fingerprint)) {
                $out[$relative] = $fingerprint;
            }
        }

        return $out;
    }

    /**
     * Rewrite a template's baseline to match `resources/views` as it stands now.
     *
     * Deliberately a separate, explicit act rather than something `themes:sync` does.
     * `themes:sync` runs from a deploy migration, and a deploy that rewrote these would
     * re-baseline the drift it was meant to catch — every check would compare the originals
     * against themselves and pass forever. The baseline has to be committed by whoever
     * reviewed the template against the changed original, which is a person, not a deploy.
     *
     * @return array{recorded: int, removed: int}
     */
    public static function record(string $template): array
    {
        $scan = self::scan($template);
        $decoded = self::manifestJson($template);

        $shadows = [];

        foreach ($scan['shadows'] as $relative => $state) {
            if ($state['current'] !== null) {
                $shadows[$relative] = $state['current'];
            }
        }

        ksort($shadows);

        $before = self::recorded($template);

        if ($shadows === []) {
            unset($decoded['shadows']);
        } else {
            $decoded['shadows'] = $shadows;
        }

        file_put_contents(
            ThemeRegistry::path($template).'/theme.json',
            json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n"
        );

        return [
            'recorded' => count(array_diff_assoc($shadows, $before)),
            'removed' => count(array_diff_key($before, $shadows)),
        ];
    }

    /** @return array<string, mixed> */
    private static function manifestJson(string $template): array
    {
        $file = ThemeRegistry::path($template).'/theme.json';

        if (! is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }
}
