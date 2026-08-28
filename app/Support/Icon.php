<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Inline SVG icons, from files committed to the repo.
 *
 * Copied out of `lucide-static` into `resources/icons/lucide/` rather than read from
 * node_modules at render time: node_modules is not deployed, and an icon set that works in
 * development and renders empty squares in production is worse than no icon set. The copy is
 * curated — a couple of dozen files — instead of vendoring two thousand.
 *
 * `resources/icons/custom/` holds the ones no general-purpose set has. A casket on a bier, a
 * hearse, a headstone and an urn are not in Lucide, Font Awesome or anything else general, so
 * they are drawn here on Lucide's own spec — 24px grid, 2px stroke, round caps and joins — to
 * sit in the same family rather than looking imported from somewhere else.
 *
 * Inlined rather than sprited or <img>-ed because these have to inherit `currentColor`: the
 * same icon appears dark on a gold tile and light on a dark band.
 */
class Icon
{
    /** Where a name is looked for, in order. Custom wins, so a set icon can be replaced. */
    private const SETS = ['custom', 'lucide'];

    public static function exists(string $name): bool
    {
        return self::path($name) !== null;
    }

    private static function path(string $name): ?string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', $name)) {
            return null;
        }

        foreach (self::SETS as $set) {
            $file = resource_path("icons/{$set}/{$name}.svg");

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The icon as an inline <svg>, sized and coloured by the classes you pass.
     *
     * Lucide ships every file with width="24" height="24" and its own class attribute. Both
     * are stripped: a hardcoded width beats a Tailwind `h-5 w-5` in the cascade, so leaving
     * them in makes every icon 24px regardless of what the caller asked for.
     *
     * @param  string|null  $strokeWidth  Lucide draws at 2. The thin line work on the
     *                                    Dignified template wants 1.5, and scaling an icon up
     *                                    without thinning its stroke is what makes an icon set
     *                                    look bolted on.
     */
    public static function svg(string $name, string $class = 'h-5 w-5', ?string $strokeWidth = null): string
    {
        $file = self::path($name);

        if ($file === null) {
            return '';
        }

        $key = 'icon.'.$name.'.'.md5($class.'|'.$strokeWidth.'|'.filemtime($file));

        return Cache::remember($key, 86400, function () use ($file, $class, $strokeWidth) {
            $svg = (string) file_get_contents($file);

            // Everything before the first '>' is the opening tag; only it is rewritten.
            $svg = preg_replace('/\s(width|height)="[^"]*"/i', '', $svg, 2) ?? $svg;
            $svg = preg_replace('/\sclass="[^"]*"/i', '', $svg, 1) ?? $svg;
            $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg) ?? $svg;

            if ($strokeWidth !== null) {
                $svg = preg_replace('/\sstroke-width="[^"]*"/i', ' stroke-width="'.e($strokeWidth).'"', $svg, 1) ?? $svg;
            }

            $attrs = 'class="'.e($class).'" aria-hidden="true" focusable="false"';

            return preg_replace('/<svg\b/i', '<svg '.$attrs, $svg, 1) ?? $svg;
        });
    }

    /** Every icon available, for the page builder's icon picker. */
    public static function names(): array
    {
        $names = [];

        foreach (self::SETS as $set) {
            foreach (glob(resource_path("icons/{$set}/*.svg")) ?: [] as $file) {
                $names[] = basename($file, '.svg');
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }
}
