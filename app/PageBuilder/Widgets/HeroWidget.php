<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Support\FieldSchema;
use App\SiteBlocks\HeroBlock;

class HeroWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return HeroBlock::type();
    }

    public static function label(): string
    {
        return HeroBlock::label();
    }

    public static function category(): string
    {
        return HeroBlock::category();
    }

    public static function defaultProps(): array
    {
        return HeroBlock::defaultProps();
    }

    public static function rules(): array
    {
        return HeroBlock::rules();
    }

    public static function viewName(): string
    {
        return HeroBlock::viewName();
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'eyebrow', 'kind' => 'text', 'label' => 'Eyebrow text'],
            ['name' => 'headline_line1', 'kind' => 'text', 'label' => 'Headline'],
            ['name' => 'headline_accent', 'kind' => 'text', 'label' => 'Headline accent (coloured)'],
            ['name' => 'body', 'kind' => 'textarea', 'label' => 'Body text'],
            ['name' => 'primary_btn_label', 'kind' => 'text', 'label' => 'Primary button label'],
            ['name' => 'primary_route', 'kind' => 'text', 'label' => 'Primary button route name'],
            ['name' => 'secondary_btn_label', 'kind' => 'text', 'label' => 'Secondary button label'],
            ['name' => 'secondary_route', 'kind' => 'text', 'label' => 'Secondary button route name'],
            ['name' => 'trust_points', 'kind' => 'json', 'label' => 'Trust points (JSON array)'],
        ];
    }

    public static function previewFields(): array
    {
        return ['headline_line1', 'headline_accent'];
    }
}
