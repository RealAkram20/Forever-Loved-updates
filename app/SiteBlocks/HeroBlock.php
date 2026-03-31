<?php

namespace App\SiteBlocks;

class HeroBlock extends AbstractSiteBlock
{
    public static function type(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'Hero';
    }

    public static function category(): string
    {
        return 'Homepage';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => 'Celebrate lives that matter',
            'headline_line1' => 'Honor Your Loved Ones.',
            'headline_accent' => 'Forever Remembered.',
            'body' => 'Create beautiful, lasting digital memorials. Share memories, collect tributes, and preserve legacies for generations to come.',
            'primary_btn_label' => 'Create a Memorial',
            'primary_route' => 'memorial.create.step1',
            'secondary_btn_label' => 'Explore Memorials',
            'secondary_route' => 'memorial.directory',
            'trust_points' => [
                'Free to start',
                'Secure & Private',
                'No credit card required',
            ],
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:200',
            'headline_line1' => 'required|string|max:300',
            'headline_accent' => 'nullable|string|max:200',
            'body' => 'nullable|string|max:2000',
            'primary_btn_label' => 'nullable|string|max:100',
            'primary_route' => 'required|string|max:120',
            'secondary_btn_label' => 'nullable|string|max:100',
            'secondary_route' => 'nullable|string|max:120',
            'trust_points' => 'nullable|array|max:10',
            'trust_points.*' => 'string|max:120',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.hero';
    }
}
