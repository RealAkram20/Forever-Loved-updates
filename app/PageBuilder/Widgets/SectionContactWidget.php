<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Contracts\ResellerWidget;
use App\PageBuilder\Support\SectionOptions;
use App\PageBuilder\Support\TextLimits;

/**
 * Where to find the business, and how to reach it.
 *
 * The one section widget that mostly does *not* take its content from props. The address,
 * phones, opening hours and map come from Reseller → Settings, because they are facts about
 * the business rather than words on a page: a reseller who moves premises should change their
 * address once, not hunt for every page a builder widget put it on.
 *
 * What props control here is presentation — the heading above it, whether the map shows, which
 * side it sits on. That split is the point: the reseller can put this section anywhere, on as
 * many pages as they like, and it stays correct everywhere by construction.
 */
class SectionContactWidget implements PageWidgetContract, ResellerWidget
{
    private const LIMITS = [
        'eyebrow' => 28,
        'heading' => 44,
        'body' => 200,
    ];

    public static function type(): string
    {
        return 'section_contact';
    }

    public static function label(): string
    {
        return 'Contact & Location';
    }

    public static function category(): string
    {
        return 'Sections';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => '',
            'heading' => 'Contact & Location',
            'body' => '',
            'show_map' => true,
            'map_side' => 'right',
            'background' => 'page',
            'padding' => 'md',
            'width' => 'normal',
        ];
    }

    public static function rules(): array
    {
        return array_merge(
            TextLimits::rules(self::LIMITS),
            SectionOptions::styleRules(['background', 'padding', 'width']),
            [
                'show_map' => 'nullable|boolean',
                'map_side' => 'nullable|string|in:'.implode(',', SectionOptions::IMAGE_SIDES),
            ],
        );
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.section-contact';
    }

    public static function fieldSchema(): array
    {
        return TextLimits::applyToFields(array_merge([
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow', 'tab' => 'content'],
            ['name' => 'heading', 'kind' => 'text', 'label' => 'Heading', 'tab' => 'content'],
            ['name' => 'body', 'kind' => 'textarea', 'label' => 'Intro', 'tab' => 'content',
                'max_hint' => 'Optional line above the details.'],
            // Said plainly, because the alternative is a reseller hunting the builder for an
            // address field that does not exist here.
            ['name' => 'show_map', 'kind' => 'checkbox', 'label' => 'Show map', 'tab' => 'content',
                'max_hint' => 'Your address, phones, hours and map come from Settings → Contact & Location.'],
        ], [
            ['name' => 'map_side', 'kind' => 'select', 'label' => 'Map on', 'tab' => 'style', 'options' => SectionOptions::IMAGE_SIDES],
        ], SectionOptions::styleFields(['background', 'padding', 'width'])), self::LIMITS);
    }

    public static function previewFields(): array
    {
        return ['heading'];
    }
}
