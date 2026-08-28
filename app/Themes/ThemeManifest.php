<?php

namespace App\Themes;

use App\Helpers\AppearanceKeys;

/**
 * One template on disk, as declared by its `theme.json`.
 *
 * A template is the *markup* half of a theme: a directory under `themes/` holding
 * whichever blades it wants to replace, and nothing else. The DB half — which reseller has
 * chosen it, and what colours they run it with — is App\Models\Theme.
 *
 * Kept as a file rather than rows because a template without its blades is a broken theme,
 * and the two must ship and roll back together.
 */
class ThemeManifest
{
    /**
     * @param  array<string, string>  $tokens
     * @param  array<int, array{type: string, props: array<string, mixed>}>  $defaultHomeBlocks
     */
    private function __construct(
        public readonly string $template,
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $screenshot,
        public readonly ?string $css,
        public readonly array $tokens,
        public readonly array $defaultHomeBlocks,
        /**
         * slug => page-builder document, for the pages this template ships ready-built.
         *
         * This is what makes "the reseller can edit their own site" true rather than
         * aspirational: applying the theme hands them a populated page in the builder, not a
         * blank one plus a screenshot of what it is supposed to look like.
         *
         * @var array<string, array{widgets: array<int, array<string, mixed>>}>
         */
        public readonly array $defaultPages,
        /**
         * Fingerprints of the `resources/views` originals this template was written against,
         * keyed by the path it shadows. Written by `themes:doctor --record`, read by
         * `themes:doctor` to tell drift from a template that is simply different on purpose.
         *
         * @var array<string, string>
         */
        public readonly array $shadows,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $template, array $data): self
    {
        return new self(
            template: $template,
            name: (string) ($data['name'] ?? ucfirst($template)),
            description: (string) ($data['description'] ?? ''),
            screenshot: self::filled($data['screenshot'] ?? null),
            css: self::filled($data['css'] ?? null),
            tokens: self::sanitizeTokens($data['tokens'] ?? []),
            defaultHomeBlocks: is_array($data['default_home_blocks'] ?? null) ? $data['default_home_blocks'] : [],
            defaultPages: self::sanitizePages($data['default_pages'] ?? []),
            shadows: self::sanitizeShadows($data['shadows'] ?? []),
        );
    }

    /**
     * @param  mixed  $shadows
     * @return array<string, string>
     */
    private static function sanitizeShadows(mixed $shadows): array
    {
        if (! is_array($shadows)) {
            return [];
        }

        return array_filter(
            $shadows,
            fn ($value, $key) => is_string($key) && is_string($value),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private static function filled(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * A template may only declare keys the appearance vocabulary already knows.
     *
     * Every one of these ends up interpolated into a `<style>` block by BrandingHelper or
     * AppearanceHelper, and the same allow-list is what stops a reseller writing an arbitrary
     * setting through the Appearance form. A theme.json is a file in the repo rather than user
     * input, but the ways a file gets into the repo are not all careful ones, and an unknown
     * key here would fail silently rather than loudly — it would simply never be read.
     *
     * @param  mixed  $tokens
     * @return array<string, string>
     */
    private static function sanitizeTokens(mixed $tokens): array
    {
        if (! is_array($tokens)) {
            return [];
        }

        $allowed = array_flip(AppearanceKeys::resellerWritable());

        return array_filter(
            array_map(fn ($v) => is_scalar($v) ? (string) $v : null, $tokens),
            fn ($value, $key) => $value !== null && isset($allowed[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Public URL for the gallery screenshot, or null when the template ships none.
     *
     * Checks the file is actually there, not just that the manifest mentions it. Dignified
     * declared `preview.webp` and shipped none, so the gallery served a broken image on the
     * one screen whose entire job is showing what a theme looks like — and did it silently,
     * because the page still returned 200. Falling back to the wireframe is strictly better
     * than a broken tile, and this way a mistyped filename costs a plainer card rather than
     * an obviously broken one. `themes:doctor` still reports the mismatch.
     */
    public function screenshotUrl(): ?string
    {
        if ($this->screenshot === null
            || ! is_file(ThemeRegistry::path($this->template).'/'.$this->screenshot)) {
            return null;
        }

        return route('themes.screenshot', ['template' => $this->template]);
    }

    /**
     * The sections the gallery tile should draw, in order.
     *
     * Prefers the home page the template actually ships over `default_home_blocks`, which is
     * a hand-written summary and drifts: Dignified's still claimed hero / features / CTA long
     * after its real front page became six section widgets. The page document cannot be wrong
     * about itself, so it wins where it exists.
     *
     * @return array<int, string>
     */
    public function homeShape(): array
    {
        $widgets = $this->defaultPages[\App\Models\Page::SLUG_VISITOR_HOME]['widgets'] ?? null;

        if (is_array($widgets) && $widgets !== []) {
            return array_values(array_filter(array_map(fn ($w) => $w['type'] ?? null, $widgets)));
        }

        return array_values(array_filter(array_map(fn ($b) => $b['type'] ?? null, $this->defaultHomeBlocks)));
    }

    /**
     * Keep only well-formed page documents.
     *
     * A manifest is a file on disk written by us, not user input — but a typo here would land
     * as a 500 on a reseller's front page the moment they applied the theme, which is a
     * needlessly expensive way to find out. Malformed entries are dropped and the page simply
     * starts empty.
     *
     * @param  mixed  $pages
     * @return array<string, array{widgets: array<int, array<string, mixed>>}>
     */
    private static function sanitizePages(mixed $pages): array
    {
        if (! is_array($pages)) {
            return [];
        }

        $out = [];

        foreach ($pages as $slug => $document) {
            if (! is_string($slug) || ! is_array($document)) {
                continue;
            }

            $widgets = $document['widgets'] ?? null;

            if (! is_array($widgets) || $widgets === []) {
                continue;
            }

            $entry = ['widgets' => array_values(array_filter(
                $widgets,
                fn ($w) => is_array($w) && is_string($w['type'] ?? null),
            ))];

            // A title means "create this page if the tenant has none". Standard pages already
            // exist and keep their own title; anything else is a page the template brings —
            // a Services listing, a page per service — that the platform has no concept of.
            if (is_string($document['title'] ?? null) && trim($document['title']) !== '') {
                $entry['title'] = trim($document['title']);
            }

            $out[$slug] = $entry;
        }

        return $out;
    }
}
