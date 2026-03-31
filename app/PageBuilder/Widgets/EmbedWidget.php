<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class EmbedWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'embed';
    }

    public static function label(): string
    {
        return 'Embed';
    }

    public static function category(): string
    {
        return 'Basic';
    }

    public static function defaultProps(): array
    {
        return [
            'mode' => 'iframe_url',
            'iframe_url' => '',
            'html' => '',
        ];
    }

    public static function rules(): array
    {
        return [
            'mode' => 'required|string|in:iframe_url,html',
            'iframe_url' => 'required_if:mode,iframe_url|nullable|string|max:2000',
            'html' => 'required_if:mode,html|nullable|string|max:20000',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.embed';
    }

    public static function fieldSchema(): array
    {
        return [
            ['name' => 'mode', 'kind' => 'select', 'label' => 'Mode', 'options' => ['iframe_url', 'html']],
            ['name' => 'iframe_url', 'kind' => 'text', 'label' => 'Iframe URL (https)'],
            ['name' => 'html', 'kind' => 'textarea', 'label' => 'Iframe HTML (sanitized)'],
        ];
    }

    public static function previewFields(): array
    {
        return ['iframe_url'];
    }
}
