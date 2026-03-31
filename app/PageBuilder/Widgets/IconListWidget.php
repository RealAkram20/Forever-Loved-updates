<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class IconListWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'icon_list';
    }

    public static function label(): string
    {
        return 'Icon list';
    }

    public static function category(): string
    {
        return 'Basic';
    }

    public static function defaultProps(): array
    {
        return [
            'title' => '',
            'items' => [
                'Free to start',
                'Secure & Private',
                'No credit card required',
            ],
            'alignment' => 'left',
        ];
    }

    public static function rules(): array
    {
        return [
            'title' => 'nullable|string|max:200',
            'items' => 'nullable|array|max:20',
            'items.*' => 'string|max:200',
            'alignment' => 'required|string|in:left,center',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.icon-list';
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'title', 'kind' => 'text', 'label' => 'Title (optional)'],
            ['name' => 'items', 'kind' => 'json', 'label' => 'Items (JSON array of strings)'],
            ['name' => 'alignment', 'kind' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center']],
        ];
    }

    public static function previewFields(): array
    {
        return ['title'];
    }
}
