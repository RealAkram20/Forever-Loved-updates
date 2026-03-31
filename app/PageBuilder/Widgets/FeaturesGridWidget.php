<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Support\FieldSchema;
use App\SiteBlocks\FeaturesGridBlock;

class FeaturesGridWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return FeaturesGridBlock::type();
    }

    public static function label(): string
    {
        return FeaturesGridBlock::label();
    }

    public static function category(): string
    {
        return FeaturesGridBlock::category();
    }

    public static function defaultProps(): array
    {
        return FeaturesGridBlock::defaultProps();
    }

    public static function rules(): array
    {
        return FeaturesGridBlock::rules();
    }

    public static function viewName(): string
    {
        return FeaturesGridBlock::viewName();
    }

    public static function fieldSchema(): array
    {
        return FieldSchema::infer(self::defaultProps());
    }

    public static function previewFields(): array
    {
        return ['title'];
    }
}
