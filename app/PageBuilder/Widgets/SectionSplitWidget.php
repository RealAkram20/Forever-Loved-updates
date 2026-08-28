<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Contracts\ResellerWidget;
use App\PageBuilder\Support\SectionOptions;
use App\PageBuilder\Support\TextLimits;

/**
 * A picture beside some words.
 *
 * Deliberately not named after a page. This one widget is the About section, the "why choose
 * us" section, the top of a single service page, and half the marketing sections anyone will
 * ever ask for — which is the whole reason there are four section widgets instead of a dozen
 * named after the places they happened to be needed first.
 *
 * The limits below are the design's measured wrap points, not round numbers: the heading wraps
 * to two lines at about sixty characters at every breakpoint, so sixty is where it stops.
 */
class SectionSplitWidget implements PageWidgetContract, ResellerWidget
{
    /**
     * prop => characters. One declaration, handed to both the validator and the editor's
     * counter, so the number a reseller types against is the number that is enforced.
     */
    private const LIMITS = [
        'eyebrow' => 28,
        'heading' => 60,
        'body' => 320,
        'button_label' => 22,
        'button2_label' => 22,
    ];

    public static function type(): string
    {
        return 'section_split';
    }

    public static function label(): string
    {
        return 'Image + Text';
    }

    public static function category(): string
    {
        return 'Sections';
    }

    public static function defaultProps(): array
    {
        return array_merge([
            'eyebrow' => 'About Us',
            'heading' => 'Compassion when you need it most',
            'body' => 'Tell people who you are and why they can trust you with something this important.',
            'image' => '',
            'image_side' => 'left',
            'image_ratio' => '5/6',
            'background' => 'page',
            'padding' => 'md',
            'width' => 'normal',
        ], SectionOptions::buttonDefaults());
    }

    public static function rules(): array
    {
        return array_merge(
            TextLimits::rules(self::LIMITS),
            SectionOptions::styleRules(['background', 'padding', 'width']),
            SectionOptions::buttonRules(),
            [
                'image' => 'nullable|string|max:500',
                'image_side' => 'nullable|string|in:'.implode(',', SectionOptions::IMAGE_SIDES),
                'image_ratio' => 'nullable|string|in:'.implode(',', SectionOptions::IMAGE_RATIOS),
            ],
        );
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.section-split';
    }

    public static function fieldSchema(): array
    {
        return TextLimits::applyToFields(array_merge([
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow', 'tab' => 'content', 'placeholder' => 'Small label above the heading',
                'max_hint' => 'Sits above the heading in small capitals.'],
            ['name' => 'heading', 'kind' => 'textarea', 'label' => 'Heading', 'tab' => 'content',
                'max_hint' => 'Wraps to two lines. Longer headings crowd the picture beside them.'],
            ['name' => 'body', 'kind' => 'textarea', 'label' => 'Text', 'tab' => 'content', 'rows' => 5,
                'max_hint' => 'Roughly two short paragraphs. Blank lines start a new one.'],
            ['name' => 'image', 'kind' => 'text', 'label' => 'Image', 'tab' => 'content', 'placeholder' => '/images/... or https://'],
        ],
            SectionOptions::buttonFields(),
            [
                ['name' => 'image_side', 'kind' => 'select', 'label' => 'Image on', 'tab' => 'style', 'options' => SectionOptions::IMAGE_SIDES],
                ['name' => 'image_ratio', 'kind' => 'select', 'label' => 'Image shape', 'tab' => 'style', 'options' => SectionOptions::IMAGE_RATIOS],
            ],
            SectionOptions::styleFields(['background', 'padding', 'width']),
        ), self::LIMITS);
    }

    public static function previewFields(): array
    {
        return ['heading'];
    }
}
