<?php

namespace App\PageBuilder\Widgets;

use App\PageBuilder\Contracts\PageWidgetContract;
use App\PageBuilder\Support\FieldSchema;

class PricingPlansWidget implements PageWidgetContract
{
    public static function type(): string
    {
        return 'pricing_plans';
    }

    public static function label(): string
    {
        return 'Pricing plans';
    }

    public static function category(): string
    {
        return 'Marketing';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => 'Pricing',
            'title' => 'Choose Your Memorial Plan',
            'subtitle' => 'Start free, upgrade when you need more. Every plan includes a beautiful memorial page.',
            'comparison_title' => 'Compare Plans',
            'trust_columns' => [
                [
                    'tone' => 'green',
                    'title' => 'Secure & Encrypted',
                    'body' => 'SSL encryption protects all your data and memories.',
                ],
                [
                    'tone' => 'blue',
                    'title' => 'Cancel Anytime',
                    'body' => 'No lock-in contracts. Downgrade or cancel whenever you need.',
                ],
                [
                    'tone' => 'purple',
                    'title' => 'Dedicated Support',
                    'body' => 'Our team is here to help you every step of the way.',
                ],
            ],
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'comparison_title' => 'nullable|string|max:200',
            'trust_columns' => 'nullable|array|max:6',
            'trust_columns.*.tone' => 'required|string|in:green,blue,purple',
            'trust_columns.*.title' => 'required|string|max:120',
            'trust_columns.*.body' => 'nullable|string|max:500',
        ];
    }

    public static function viewName(): string
    {
        return 'page-builder.widgets.pricing-plans';
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
