<?php

namespace App\PageBuilder\Support;

/**
 * Turns the named choices a section widget stores into the classes a blade renders.
 *
 * Every section view — the plain ones and each theme's — asks the same four questions, so the
 * mapping lives here rather than being re-derived in eight blades that would answer "medium
 * spacing" slightly differently.
 *
 * The values below are the *plain* answers. A theme that wants its own scale overrides the
 * widget's view and calls these with its own tables, or ignores them entirely; nothing here is
 * binding on a template that has an opinion.
 */
class SectionRender
{
    /** @param array<string, mixed> $props */
    public static function background(array $props, array $map = []): string
    {
        $map = $map ?: [
            'page' => 'bg-white dark:bg-gray-900',
            'muted' => 'bg-gray-50 dark:bg-gray-800/40',
            'dark' => 'bg-gray-900 text-white',
            'accent' => 'bg-brand-500 text-white',
            'image' => 'bg-gray-900 text-white',
        ];

        return $map[$props['background'] ?? 'page'] ?? $map['page'];
    }

    /** @param array<string, mixed> $props */
    public static function padding(array $props, array $map = []): string
    {
        $map = $map ?: [
            'none' => '',
            'sm' => 'py-8',
            'md' => 'py-14 sm:py-16',
            'lg' => 'py-20 sm:py-24',
        ];

        return $map[$props['padding'] ?? 'md'] ?? $map['md'];
    }

    /** @param array<string, mixed> $props */
    public static function width(array $props, array $map = []): string
    {
        $map = $map ?: [
            'narrow' => 'max-w-3xl',
            'normal' => 'max-w-6xl',
            'wide' => 'max-w-7xl',
            'full' => 'max-w-none',
        ];

        return $map[$props['width'] ?? 'normal'] ?? $map['normal'];
    }

    /** @param array<string, mixed> $props */
    public static function overlay(array $props): string
    {
        return match ($props['overlay'] ?? 'medium') {
            'none' => '',
            'light' => 'bg-black/25',
            'heavy' => 'bg-black/70',
            default => 'bg-black/50',
        };
    }

    /**
     * The buttons a section has, as a list, so a view loops once instead of repeating itself
     * for a second button that is usually absent.
     *
     * @param  array<string, mixed>  $props
     * @return array<int, array{label: string, url: string, primary: bool}>
     */
    public static function buttons(array $props): array
    {
        $out = [];

        foreach ([['button_label', 'button_url', true], ['button2_label', 'button2_url', false]] as [$labelKey, $urlKey, $primary]) {
            $label = trim((string) ($props[$labelKey] ?? ''));

            if ($label === '') {
                continue;
            }

            $out[] = [
                'label' => $label,
                // A button with no link still renders: half-built is a state a page passes
                // through, and vanishing controls make the builder feel broken.
                'url' => self::url((string) ($props[$urlKey] ?? '')),
                'primary' => $primary,
            ];
        }

        return $out;
    }

    /**
     * Resolve a link a reseller typed.
     *
     * A bare path has to be resolved against *their* site, not ours — SiteUrl exists because
     * url() quietly answers with the platform's address instead of failing.
     */
    public static function url(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '#';
        }

        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $value)) {
            return $value;
        }

        return \App\Support\SiteUrl::to(ltrim($value, '/'));
    }

    /**
     * Body copy typed into a textarea, as paragraphs.
     *
     * Blank lines separate paragraphs, which is what someone typing into a box expects. The
     * text is escaped by the caller; this only decides where the breaks go.
     *
     * @return array<int, string>
     */
    public static function paragraphs(?string $body): array
    {
        return collect(preg_split('/\n\s*\n/', (string) $body))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * An image path from a widget, with a template's own artwork resolved.
     *
     * A document shipped by a template refers to its pictures as `{theme}/hero.webp`. Storing
     * a resolved URL instead would bake this install's address into the reseller's saved page,
     * so a subdirectory install that later moves to a custom domain would carry broken images
     * into its new home. Resolving here keeps what is stored portable and what is rendered
     * correct on whatever host is serving it.
     *
     * The template is the *active* one, so a page seeded from one theme and then viewed under
     * another picks up the second theme's artwork rather than 404ing on the first's.
     */
    public static function image(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || ! str_contains($value, '{theme}/')) {
            return $value;
        }

        $template = app(\App\Themes\ActiveTheme::class)->template() ?: \App\Themes\ThemeRegistry::BASE;

        return str_replace('{theme}/', asset('images/themes/'.$template).'/', $value);
    }
}
