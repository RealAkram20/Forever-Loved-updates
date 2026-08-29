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
            // The link beside this row used to be "View all", pointing at the directory. The
            // directory is already in the header nav on every page; what somebody reading a row
            // of examples wants next is to start one of their own.
            //
            // Renamed rather than left as view_all_label holding "Create a Memorial", which
            // would be a lie the next reader has to discover. Nothing stores the old keys but
            // this block's own layouts, and stored props merge over defaults, so the new keys
            // fall back cleanly wherever the old ones were saved.
            'cta_label' => 'Create a Memorial',
            'mobile_cta_label' => 'Create a Memorial',
            // Resolved through StandardPages so it lands on the right site's flow, exactly as
            // the hero's primary button does.
            'cta_route' => 'memorial.create.step1',
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:600',
            'cta_label' => 'nullable|string|max:80',
            'mobile_cta_label' => 'nullable|string|max:120',
            'cta_route' => 'nullable|string|max:120',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.memorial-showcase';
    }
}
