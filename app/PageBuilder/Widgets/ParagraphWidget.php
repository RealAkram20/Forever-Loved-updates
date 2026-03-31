<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class ParagraphWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'paragraph';
    }

    public static function label(): string
    {
        return 'Paragraph';
    }

    public static function category(): string
    {
        return 'Basic';
    }

    public static function defaultProps(): array
    {
        return [
            'content' => '<p>Add your text here.</p>',
            'class' => '',
        ];
    }

    public static function rules(): array
    {
        return [
            'content' => 'nullable|string|max:50000',
            'class' => 'nullable|string|max:200',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.paragraph';
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'content', 'kind' => 'richtext', 'label' => 'Content'],
            ['name' => 'class', 'kind' => 'text', 'label' => 'CSS class (optional)'],
        ];
    }

    public static function previewFields(): array
    {
        return ['content'];
    }
}
