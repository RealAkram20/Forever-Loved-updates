<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Installer layout uses @vite by default. If manifest.json is missing (partial FTP upload,
 * wrong extraction), @vite throws and the wizard returns HTTP 500. This helper falls back to
 * linking the built hashed assets in public/build/assets when the manifest is absent.
 */
final class InstallerVite
{
    public static function hasManifest(): bool
    {
        return is_readable(public_path('build/manifest.json'));
    }

    /**
     * @return list<string> Paths relative to public/ (e.g. build/assets/app-xxx.css)
     */
    public static function fallbackStylesheetPaths(): array
    {
        $dir = public_path('build/assets');
        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'app-*.css') ?: [];

        return collect($files)
            ->map(fn (string $abs): string => 'build/assets/'.basename($abs))
            ->unique()
            ->values()
            ->all();
    }

    public static function fallbackScriptPath(): ?string
    {
        $dir = public_path('build/assets');
        if (! is_dir($dir)) {
            return null;
        }

        $candidates = glob($dir.DIRECTORY_SEPARATOR.'app-*.js') ?: [];
        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (string $a, string $b): int => filesize($b) <=> filesize($a));

        return 'build/assets/'.basename($candidates[0]);
    }

    public static function hasUsableBuildWithoutManifest(): bool
    {
        return self::fallbackStylesheetPaths() !== [] && self::fallbackScriptPath() !== null;
    }
}
