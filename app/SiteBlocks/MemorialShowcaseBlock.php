<?php

namespace App\SiteBlocks;

class MemorialShowcaseBlock extends AbstractSiteBlock
{
    public static function type(): string
    {
        return 'memorial_showcase';
    }

    public static function label(): string
    {
        return 'Popular memorials';
    }

    public static function category(): string
    {
        return 'Homepage';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => 'Trending',
            'title' => 'Popular Memorials',
            'view_all_label' => 'View all',
            'mobile_view_all_label' => 'View All Memorials',
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'required|string|max:200',
            'view_all_label' => 'nullable|string|max:80',
            'mobile_view_all_label' => 'nullable|string|max:120',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.memorial-showcase';
    }
}
