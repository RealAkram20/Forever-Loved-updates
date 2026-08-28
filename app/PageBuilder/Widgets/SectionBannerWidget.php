<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Contracts\ResellerWidget;
use App\PageBuilder\Support\SectionOptions;
use App\PageBuilder\Support\TextLimits;

/**
 * Words over a background, with somewhere to go next.
 *
 * The home page hero, the title band on an inner page, a call-to-action strip, and the
 * feedback bar with its three fields are the same object at four sizes. Splitting them into
 * four widgets would mean four property panels to learn, four sets of limits to keep in step,
 * and four blades per theme — for one arrangement.
 *
 * `height` is what separates a hero from a title band, and `form` is what turns a call to
 * action into the feedback bar. Both are settings because that is what they are.
 */
class SectionBannerWidget implements PageWidgetContract, ResellerWidget
{
    private const LIMITS = [
        'eyebrow' => 34,
        // Two lines at hero size in the reference ("Dignified care. / Compassionate service.").
        'heading' => 64,
        'body' => 200,
        'button_label' => 22,
        'button2_label' => 22,
        'form_button_label' => 18,
    ];

    public const HEIGHTS = ['band', 'short', 'tall', 'screen'];

    public const FORMS = ['none', 'enquiry'];

    public static function type(): string
    {
        return 'section_banner';
    }

    public static function label(): string
    {
        return 'Banner';
    }

    public static function category(): string
    {
        return 'Sections';
    }

    public static function defaultProps(): array
    {
        return array_merge([
            'eyebrow' => '',
            'heading' => 'Dignified care. Compassionate service.',
            'body' => 'Honouring life with respect and supporting families with compassion.',
            'image' => '',
            'height' => 'tall',
            'overlay' => 'medium',
            'alignment' => 'left',
            'width' => 'normal',
            'background' => 'image',
            'padding' => 'md',
            'form' => 'none',
            'form_button_label' => 'Submit',
        ], SectionOptions::buttonDefaults());
    }

    public static function rules(): array
    {
        return array_merge(
            TextLimits::rules(self::LIMITS),
            SectionOptions::styleRules(['background', 'padding', 'width', 'alignment', 'overlay']),
            SectionOptions::buttonRules(),
            [
                'image' => 'nullable|string|max:500',
                'height' => 'nullable|string|in:'.implode(',', self::HEIGHTS),
                'form' => 'nullable|string|in:'.implode(',', self::FORMS),
            ],
        );
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.section-banner';
    }

    public static function fieldSchema(): array
    {
        return TextLimits::applyToFields(array_merge([
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow', 'tab' => 'content'],
            ['name' => 'heading', 'kind' => 'textarea', 'label' => 'Heading', 'tab' => 'content',
                'max_hint' => 'Two lines at banner size. A full stop mid-heading starts the second line.'],
            ['name' => 'body', 'kind' => 'textarea', 'label' => 'Text', 'tab' => 'content',
                'max_hint' => 'One or two lines under the heading.'],
            ['name' => 'image', 'kind' => 'text', 'label' => 'Background image', 'tab' => 'content', 'placeholder' => '/images/... or https://'],
            [
                'name' => 'form',
                'kind' => 'select',
                'label' => 'Enquiry form',
                'tab' => 'content',
                'options' => self::FORMS,
                // The one setting whose consequence is not obvious from its name.
                'max_hint' => 'Adds name, email and message fields. Replies go to your contact email.',
            ],
            ['name' => 'form_button_label', 'kind' => 'text', 'label' => 'Form button', 'tab' => 'content'],
        ],
            SectionOptions::buttonFields(),
            [['name' => 'height', 'kind' => 'select', 'label' => 'Height', 'tab' => 'style', 'options' => self::HEIGHTS]],
            SectionOptions::styleFields(['background', 'overlay', 'padding', 'width', 'alignment']),
        ), self::LIMITS);
    }

    public static function previewFields(): array
    {
        return ['heading'];
    }
}
