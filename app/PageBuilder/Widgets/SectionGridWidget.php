<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Contracts\ResellerWidget;
use App\PageBuilder\Support\SectionOptions;
use App\PageBuilder\Support\TextLimits;
use App\Support\Icon;

/**
 * A row of cards: an icon, a name, a line of explanation, somewhere to go.
 *
 * The services grid, the "why choose us" list, a features strip, a team row — all the same
 * shape, so all the same widget. What changes between them is the number of columns and
 * whether the cards carry icons, both of which are settings rather than separate widgets.
 *
 * The item cap is 12 and the column choice is 2/3/4 because those are the arrangements that
 * stay legible; a reseller who needs thirty of something needs a page, not a grid.
 */
class SectionGridWidget implements PageWidgetContract, ResellerWidget
{
    private const LIMITS = [
        'eyebrow' => 28,
        'heading' => 60,
        'body' => 240,
        'button_label' => 22,
        'button2_label' => 22,
    ];

    /** Per-card limits. Short, because a card is a signpost and not a paragraph. */
    private const ITEM_LIMITS = [
        'title' => 34,
        'text' => 120,
    ];

    public static function type(): string
    {
        return 'section_grid';
    }

    public static function label(): string
    {
        return 'Card Grid';
    }

    public static function category(): string
    {
        return 'Sections';
    }

    public static function defaultProps(): array
    {
        return array_merge([
            'eyebrow' => 'Our Services',
            'heading' => '',
            'body' => '',
            'columns' => 3,
            'items' => [
                ['icon' => 'casket', 'title' => 'Funeral Arrangements', 'text' => '', 'url' => ''],
                ['icon' => 'file-lock', 'title' => 'Documentation & Advisory', 'text' => '', 'url' => ''],
                ['icon' => 'hearse', 'title' => 'Repatriation Services', 'text' => '', 'url' => ''],
                ['icon' => 'headstone', 'title' => 'Memorial Services', 'text' => '', 'url' => ''],
                ['icon' => 'cross', 'title' => 'Burial & Cremation', 'text' => '', 'url' => ''],
                ['icon' => 'urn', 'title' => 'Condolence Support', 'text' => '', 'url' => ''],
            ],
            'background' => 'muted',
            'padding' => 'md',
            'width' => 'normal',
            'alignment' => 'center',
        ], SectionOptions::buttonDefaults());
    }

    public static function rules(): array
    {
        return array_merge(
            TextLimits::rules(self::LIMITS),
            SectionOptions::styleRules(['background', 'padding', 'width', 'alignment']),
            SectionOptions::buttonRules(),
            [
                'columns' => 'nullable|integer|in:'.implode(',', SectionOptions::COLUMNS),
                'items' => 'nullable|array|max:12',
                'items.*.icon' => 'nullable|string|max:60',
                'items.*.title' => 'nullable|string|max:'.self::ITEM_LIMITS['title'],
                'items.*.text' => 'nullable|string|max:'.self::ITEM_LIMITS['text'],
                'items.*.url' => 'nullable|string|max:500',
            ],
        );
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.section-grid';
    }

    public static function fieldSchema(): array
    {
        return TextLimits::applyToFields(array_merge([
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow', 'tab' => 'content'],
            ['name' => 'heading', 'kind' => 'textarea', 'label' => 'Heading', 'tab' => 'content',
                'max_hint' => 'Optional. The cards can stand on their own.'],
            ['name' => 'body', 'kind' => 'textarea', 'label' => 'Intro', 'tab' => 'content',
                'max_hint' => 'Optional line above the cards.'],
            [
                'name' => 'items',
                'kind' => 'repeater',
                'label' => 'Cards',
                'tab' => 'content',
                'item_label' => 'card',
                'max_items' => 12,
                'item_fields' => TextLimits::applyToItemFields([
                    ['name' => 'title', 'kind' => 'text', 'label' => 'Title'],
                    ['name' => 'text', 'kind' => 'textarea', 'label' => 'Description'],
                    // A named icon rather than a free upload: the set is drawn to one weight
                    // and one grid, and a pasted PNG among them is instantly visible.
                    ['name' => 'icon', 'kind' => 'select', 'label' => 'Icon', 'options' => array_merge([''], Icon::names())],
                    ['name' => 'url', 'kind' => 'text', 'label' => 'Links to', 'placeholder' => '/funeral-arrangements'],
                ], self::ITEM_LIMITS),
            ],
        ],
            SectionOptions::buttonFields(),
            [['name' => 'columns', 'kind' => 'select', 'label' => 'Columns', 'tab' => 'style', 'options' => SectionOptions::COLUMNS, 'cast' => 'int']],
            SectionOptions::styleFields(['background', 'padding', 'width', 'alignment']),
        ), self::LIMITS);
    }

    public static function previewFields(): array
    {
        return ['eyebrow', 'heading'];
    }
}
