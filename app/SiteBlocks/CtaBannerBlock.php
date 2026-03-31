<?php

namespace App\SiteBlocks;

class CtaBannerBlock extends AbstractSiteBlock
{
    public static function type(): string
    {
        return 'cta_banner';
    }

    public static function label(): string
    {
        return 'CTA banner';
    }

    public static function category(): string
    {
        return 'Homepage';
    }

    public static function defaultProps(): array
    {
        return [
            'title' => 'Start Preserving Memories Today',
            'body' => 'Every life has a story worth telling. Create a free memorial in minutes and invite loved ones to contribute their memories.',
            'primary_label' => 'Get Started Free',
            'primary_route' => 'memorial.create.step1',
            'secondary_label' => 'View Plans',
            'secondary_route' => 'pricing',
        ];
    }

    public static function rules(): array
    {
        return [
            'title' => 'required|string|max:300',
            'body' => 'nullable|string|max:1000',
            'primary_label' => 'nullable|string|max:100',
            'primary_route' => 'required|string|max:120',
            'secondary_label' => 'nullable|string|max:100',
            'secondary_route' => 'nullable|string|max:120',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.cta-banner';
    }
}
