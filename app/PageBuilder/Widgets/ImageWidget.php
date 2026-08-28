<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class ImageWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'image';
    }

    public static function label(): string
    {
        return 'Image';
    }

    public static function category(): string
    {
        return 'Basic';
    }

    public static function defaultProps(): array
    {
        return [
            'src' => '',
            'alt' => '',
            'caption' => '',
            'href' => '',
        ];
    }

    public static function rules(): array
    {
        return [
            // Nullable, not required. defaultProps ships an empty src, so `required` meant a
            // freshly dropped Image widget failed the whole page save with "The props.src
            // field is required" — an error naming a field the person cannot connect to the
            // widget they just added, on a page whose other edits are then lost. Half-built is
            // a state a page passes through; the view renders a placeholder until an image is
            // chosen. Found by the theme conformance suite.
            'src' => 'nullable|string|max:2000',
            'alt' => 'nullable|string|max:500',
            'caption' => 'nullable|string|max:500',
            'href' => 'nullable|string|max:2000',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.image';
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'src', 'kind' => 'text', 'label' => 'Image URL'],
            ['name' => 'alt', 'kind' => 'text', 'label' => 'Alt text'],
            ['name' => 'caption', 'kind' => 'text', 'label' => 'Caption'],
            ['name' => 'href', 'kind' => 'text', 'label' => 'Link URL (optional)'],
        ];
    }

    public static function previewFields(): array
    {
        return ['src'];
    }
}
