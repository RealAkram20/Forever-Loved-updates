<?php

namespace App\PageBuilder\Support;

/**
 * The shared vocabulary every section widget speaks.
 *
 * Four widgets can compose all of these pages only if their property panels look like one
 * tool rather than four. Background, padding, alignment and width mean the same thing and
 * offer the same choices everywhere, so learning one section teaches the rest.
 *
 * Colour is a *named role*, never a picker. A free colour control per widget is how a
 * white-label site ends up with eleven shades of gold — and the point of these limits is that
 * a reseller can rearrange their site without being able to ruin it. The theme decides what
 * "Dark" or "Accent" actually looks like, which is also what lets one page survive a change of
 * theme.
 */
class SectionOptions
{
    /** Named surfaces. What each one renders as is the theme's business. */
    public const BACKGROUNDS = ['page', 'muted', 'dark', 'accent', 'image'];

    /** Vertical breathing room. Named rather than numeric so themes can set their own scale. */
    public const PADDING = ['none', 'sm', 'md', 'lg'];

    public const ALIGNMENTS = ['left', 'center'];

    /** Content column width. 'full' is edge-to-edge for banners and grids. */
    public const WIDTHS = ['narrow', 'normal', 'wide', 'full'];

    public const IMAGE_SIDES = ['left', 'right'];

    public const IMAGE_RATIOS = ['5/6', '4/5', '1/1', '3/2', '16/9'];

    public const COLUMNS = [2, 3, 4];

    /** How hard the scrim sits over a background image, so text stays readable. */
    public const OVERLAYS = ['none', 'light', 'medium', 'heavy'];

    /**
     * The style-tab fields every section widget carries, in the same order every time.
     *
     * @param  array<int, string>  $only  restrict to these prop names
     * @return array<int, array<string, mixed>>
     */
    public static function styleFields(array $only = []): array
    {
        $fields = [
            ['name' => 'background', 'kind' => 'select', 'label' => 'Background', 'tab' => 'style', 'options' => self::BACKGROUNDS],
            ['name' => 'overlay', 'kind' => 'select', 'label' => 'Image darkening', 'tab' => 'style', 'options' => self::OVERLAYS],
            ['name' => 'padding', 'kind' => 'select', 'label' => 'Spacing', 'tab' => 'style', 'options' => self::PADDING],
            ['name' => 'width', 'kind' => 'select', 'label' => 'Content width', 'tab' => 'style', 'options' => self::WIDTHS],
            ['name' => 'alignment', 'kind' => 'select', 'label' => 'Alignment', 'tab' => 'style', 'options' => self::ALIGNMENTS],
        ];

        if ($only === []) {
            return $fields;
        }

        return array_values(array_filter($fields, fn ($f) => in_array($f['name'], $only, true)));
    }

    /**
     * Validation for whichever of those a widget uses.
     *
     * @param  array<int, string>  $only
     * @return array<string, string>
     */
    public static function styleRules(array $only = []): array
    {
        $all = [
            'background' => 'nullable|string|in:'.implode(',', self::BACKGROUNDS),
            'overlay' => 'nullable|string|in:'.implode(',', self::OVERLAYS),
            'padding' => 'nullable|string|in:'.implode(',', self::PADDING),
            'width' => 'nullable|string|in:'.implode(',', self::WIDTHS),
            'alignment' => 'nullable|string|in:'.implode(',', self::ALIGNMENTS),
        ];

        return $only === [] ? $all : array_intersect_key($all, array_flip($only));
    }

    /**
     * A button pair, which every section offers and none requires.
     *
     * Two is the cap on purpose: a section with three calls to action has none.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buttonFields(): array
    {
        return [
            ['name' => 'button_label', 'kind' => 'text', 'label' => 'Button', 'tab' => 'content', 'placeholder' => 'Learn more'],
            ['name' => 'button_url', 'kind' => 'text', 'label' => 'Button link', 'tab' => 'content', 'placeholder' => 'https:// or /about'],
            ['name' => 'button2_label', 'kind' => 'text', 'label' => 'Second button', 'tab' => 'content', 'placeholder' => 'Optional'],
            ['name' => 'button2_url', 'kind' => 'text', 'label' => 'Second button link', 'tab' => 'content'],
        ];
    }

    /** @return array<string, string> */
    public static function buttonRules(): array
    {
        return [
            'button_url' => 'nullable|string|max:500',
            'button2_url' => 'nullable|string|max:500',
        ];
    }

    /** @return array<string, mixed> */
    public static function buttonDefaults(): array
    {
        return [
            'button_label' => '',
            'button_url' => '',
            'button2_label' => '',
            'button2_url' => '',
        ];
    }
}
