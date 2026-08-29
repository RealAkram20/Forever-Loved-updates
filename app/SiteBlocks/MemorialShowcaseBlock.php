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
        // Deliberately neutral. Resellers can add this block to their own pages and inherit
        // whatever is here, and our homepage copy for it makes a claim about the memorials
        // underneath — that they are examples rather than real families — which is true of our
        // seeded ones and false of theirs. The platform's own wording lives in
        // SiteLayoutService::defaultHomeDocument(), where only the platform reads it.
        return [
            'eyebrow' => 'Trending',
            'title' => 'Popular Memorials',
            'description' => '',
            'view_all_label' => 'View all',
            'mobile_view_all_label' => 'View All Memorials',
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:600',
            'view_all_label' => 'nullable|string|max:80',
            'mobile_view_all_label' => 'nullable|string|max:120',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.memorial-showcase';
    }
}
