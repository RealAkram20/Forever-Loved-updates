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
            'title' => 'Because Every Life Leaves a Story',
            'subtitle' => 'This memorial website is more than a site to visit – it\'s a place for memories, forever to be remembered with care.',
            'cards' => [
                ['icon' => 'image', 'title' => 'Beautiful Memorials', 'body' => 'Elegant pages to celebrate a life, preserve images, write stories, and save your legacy.'],
                ['icon' => 'heart', 'title' => 'Collect Tributes', 'body' => 'Let community and loved ones\' reflections live with you, and be heard, lifted & held forever.'],
                ['icon' => 'image', 'title' => 'Photos & Videos Gallery', 'body' => 'Upload and gather photos and videos preserving vibrant and precious moments.'],
                ['icon' => 'infinity', 'title' => 'All-Purpose Biographies', 'body' => 'Add a biography and honor the journey they lived – including early life, love, memories.'],
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
            'cards.*.icon' => 'required|string|in:book,heart,image,sparkles,infinity,flower',
            'cards.*.title' => 'required|string|max:200',
            'cards.*.body' => 'required|string|max:1000',
        ];
    }

    public static function viewName(): string
    {
        return 'site-blocks.features-grid';
    }
}
