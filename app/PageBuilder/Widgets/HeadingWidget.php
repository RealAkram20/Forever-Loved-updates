<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;

class HeadingWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'heading';
    }

    public static function label(): string
    {
        return 'Heading';
    }

    public static function category(): string
    {
        return 'Basic';
    }

    public static function defaultProps(): array
    {
        return [
            'level' => 2,
            'text' => 'Section title',
            'link' => '',
            'alignment' => 'left',
            'color' => '',
            'font_size' => '',
            'font_size_unit' => 'px',
            'font_weight' => '',
            'line_height' => '',
            'letter_spacing' => '',
        ];
    }

    public static function rules(): array
    {
        return [
            'level' => 'required|integer|in:1,2,3,4,5,6',
            'text' => 'required|string|max:500',
            'link' => 'nullable|string|max:500',
            'alignment' => 'required|string|in:left,center,right,justify',
            'color' => 'nullable|string|max:50',
            'font_size' => 'nullable|string|max:10',
            'font_size_unit' => 'nullable|string|in:px,em,rem,%,vw',
            'font_weight' => 'nullable|string|in:,100,200,300,400,500,600,700,800,900',
            'line_height' => 'nullable|string|max:10',
            'letter_spacing' => 'nullable|string|max:10',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.heading';
    }

    public static function fieldSchema(): array
    {
        return [
            // Content tab
            ['name' => 'text', 'kind' => 'textarea', 'label' => 'Title', 'tab' => 'content'],
            ['name' => 'link', 'kind' => 'text', 'label' => 'Link', 'tab' => 'content', 'placeholder' => 'Type or paste your URL'],
            ['name' => 'level', 'kind' => 'select', 'label' => 'HTML Tag', 'tab' => 'content', 'options' => [1, 2, 3, 4, 5, 6], 'cast' => 'int', 'prefix' => 'H'],

            // Style tab
            ['name' => 'alignment', 'kind' => 'alignment', 'label' => 'Alignment', 'tab' => 'style'],
            ['name' => 'color', 'kind' => 'color', 'label' => 'Text Color', 'tab' => 'style'],
            ['name' => 'font_size', 'kind' => 'size_unit', 'label' => 'Font Size', 'tab' => 'style', 'units' => ['px', 'em', 'rem', '%', 'vw'], 'unit_prop' => 'font_size_unit'],
            ['name' => 'font_weight', 'kind' => 'select', 'label' => 'Font Weight', 'tab' => 'style', 'options' => ['', '100', '200', '300', '400', '500', '600', '700', '800', '900']],
            ['name' => 'line_height', 'kind' => 'text', 'label' => 'Line Height', 'tab' => 'style', 'placeholder' => 'e.g. 1.2 or 40px'],
            ['name' => 'letter_spacing', 'kind' => 'text', 'label' => 'Letter Spacing', 'tab' => 'style', 'placeholder' => 'e.g. 1px or 0.05em'],
        ];
    }

    public static function previewFields(): array
    {
        return ['text'];
    }
}
