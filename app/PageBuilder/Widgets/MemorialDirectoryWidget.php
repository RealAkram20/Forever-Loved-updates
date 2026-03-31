<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class MemorialDirectoryWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'memorial_directory';
    }

    public static function label(): string
    {
        return 'Memorial directory';
    }

    public static function category(): string
    {
        return 'Marketing';
    }

    public static function defaultProps(): array
    {
        return [];
    }

    public static function rules(): array
    {
        return [];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.memorial-directory';
    }

    public static function fieldSchema(): array
    {
        return [];
    }

    public static function previewFields(): array
    {
        return [];
    }
}
