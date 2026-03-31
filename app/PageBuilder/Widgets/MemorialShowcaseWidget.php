<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Support\FieldSchema;
use App\SiteBlocks\MemorialShowcaseBlock;

class MemorialShowcaseWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return MemorialShowcaseBlock::type();
    }

    public static function label(): string
    {
        return MemorialShowcaseBlock::label();
    }

    public static function category(): string
    {
        return MemorialShowcaseBlock::category();
    }

    public static function defaultProps(): array
    {
        return MemorialShowcaseBlock::defaultProps();
    }

    public static function rules(): array
    {
        return MemorialShowcaseBlock::rules();
    }

    public static function viewName(): string
    {
        return MemorialShowcaseBlock::viewName();
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
