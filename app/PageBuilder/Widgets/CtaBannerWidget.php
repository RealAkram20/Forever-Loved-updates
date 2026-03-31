<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Support\FieldSchema;
use App\SiteBlocks\CtaBannerBlock;

class CtaBannerWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return CtaBannerBlock::type();
    }

    public static function label(): string
    {
        return CtaBannerBlock::label();
    }

    public static function category(): string
    {
        return CtaBannerBlock::category();
    }

    public static function defaultProps(): array
    {
        return CtaBannerBlock::defaultProps();
    }

    public static function rules(): array
    {
        return CtaBannerBlock::rules();
    }

    public static function viewName(): string
    {
        return CtaBannerBlock::viewName();
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
