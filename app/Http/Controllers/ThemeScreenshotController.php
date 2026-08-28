<?php

namespace App\Http\Controllers;

use App\Themes\ThemeRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a template's gallery screenshot out of `themes/{template}/`.
 *
 * Through a route rather than by copying files into `public/` on sync, so a template stays
 * one self-contained directory: its blades, its manifest and its preview image ship, move and
 * roll back together. A sync step that copies assets is a step that can half-run.
 */
class ThemeScreenshotController extends Controller
{
    private const TYPES = [
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
    ];

    public function show(Request $request, string $template): BinaryFileResponse
    {
        $registry = app(ThemeRegistry::class);

        abort_unless($registry->exists($template), 404);

        $manifest = $registry->manifest($template);

        abort_unless($manifest?->screenshot !== null, 404);

        // basename(), because the value comes from a file the deploy put there and a path
        // that climbs out of the theme directory would serve any file the process can read.
        $file = ThemeRegistry::path($template).DIRECTORY_SEPARATOR.basename($manifest->screenshot);
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        abort_unless(is_file($file) && isset(self::TYPES[$extension]), 404);

        return response()->file($file, [
            'Content-Type' => self::TYPES[$extension],
            // Templates change only on deploy, and the URL carries no version — a week is
            // long enough to be worth caching and short enough that a redesign lands.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
