<?php

namespace App\SiteBlocks;

class FeaturesGridBlock extends AbstractSiteBlock
{
    public static function type(): string
    {
        return 'features_grid';
    }

    public static function label(): string
    {
        return 'Features grid';
    }

    public static function category(): string
    {
        return 'Homepage';
    }

    public static function defaultProps(): array
    {
        return [
            'eyebrow' => 'Why Choose Us',
            'title' => 'Everything You Need to Honor a Life',
            'subtitle' => 'Our platform provides all the tools to create a meaningful, lasting tribute.',
            'cards' => [
                ['icon' => 'book', 'title' => 'Beautiful Memorials', 'body' => 'Create elegant, personalized memorial pages with biographies, life timelines, and cherished stories.'],
                ['icon' => 'heart', 'title' => 'Collect Tributes', 'body' => 'Let family and friends leave virtual flowers, light candles, and share heartfelt written tributes.'],
                ['icon' => 'image', 'title' => 'Photo & Video Gallery', 'body' => 'Upload and display photos and videos in a stunning gallery to preserve precious moments.'],
                ['icon' => 'sparkles', 'title' => 'AI-Powered Biography', 'body' => 'Let our AI help craft a beautiful biography from key details, making it easy to tell their story.'],
            ],
        ];
    }

    public static function rules(): array
    {
        return [
            'eyebrow' => 'nullable|string|max:120',
            'title' => 'required|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'cards' => 'required|array|min:1|max:12',
            'cards.*.icon' => 'required|string|in:book,heart,image,sparkles',
            'cards.*.title' => 'required|string|max:200',
            'cards.*.body' => 'required|string|max:1000',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.features-grid';
    }
}
