<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\SiteLayout;
use App\PageBuilder\Widgets\ContactFormWidget;
use App\PageBuilder\Widgets\HeadingWidget;
use App\PageBuilder\Widgets\MemorialDirectoryWidget;
use App\PageBuilder\Widgets\PricingPlansWidget;
use App\Services\PageLayoutService;
use App\Services\SiteLayoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketingPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedVisitorHome();
        $this->seedPricing();
        $this->seedContact();
        $this->seedFindMemorial();
    }

    private function seedVisitorHome(): void
    {
        $blocks = [];
        $layout = SiteLayout::query()->where('key', SiteLayout::KEY_VISITOR_HOME)->first();
        if ($layout && is_string($layout->json) && $layout->json !== '') {
            $decoded = json_decode($layout->json, true);
            if (is_array($decoded) && isset($decoded['blocks']) && is_array($decoded['blocks'])) {
                $blocks = $decoded['blocks'];
            }
        }
        if ($blocks === []) {
            $blocks = app(SiteLayoutService::class)->defaultHomeDocument()['blocks'];
        }

        $widgets = [];
        foreach ($blocks as $i => $block) {
            if (! is_array($block) || empty($block['type'])) {
                continue;
            }
            $widgets[] = [
                'id' => 'w_'.Str::lower(Str::random(10)),
                'type' => (string) $block['type'],
                'order' => $i,
                'props' => is_array($block['props'] ?? null) ? $block['props'] : [],
            ];
        }

        try {
            $layoutDoc = app(PageLayoutService::class)->validateDocumentFromArray([
                'version' => 1,
                'widgets' => $widgets,
            ]);
        } catch (\Throwable) {
            $blocks = app(SiteLayoutService::class)->defaultHomeDocument()['blocks'];
            $widgets = [];
            foreach ($blocks as $i => $block) {
                $widgets[] = [
                    'id' => 'w_'.Str::lower(Str::random(10)),
                    'type' => (string) $block['type'],
                    'order' => $i,
                    'props' => $block['props'] ?? [],
                ];
            }
            $layoutDoc = app(PageLayoutService::class)->validateDocumentFromArray([
                'version' => 1,
                'widgets' => $widgets,
            ]);
        }

        Page::query()->updateOrCreate(
            ['slug' => Page::SLUG_VISITOR_HOME],
            [
                'title' => 'Home',
                'content' => null,
                'layout' => $layoutDoc,
                'is_published' => true,
            ]
        );

        Page::clearSlugCache(Page::SLUG_VISITOR_HOME);
    }

    private function seedPricing(): void
    {
        $layoutDoc = app(PageLayoutService::class)->validateDocumentFromArray([
            'version' => 1,
            'widgets' => [
                [
                    'id' => 'w_'.Str::lower(Str::random(10)),
                    'type' => 'pricing_plans',
                    'order' => 0,
                    'props' => PricingPlansWidget::defaultProps(),
                ],
            ],
        ]);

        Page::query()->updateOrCreate(
            ['slug' => 'pricing'],
            [
                'title' => 'Pricing & Features',
                'content' => null,
                'layout' => $layoutDoc,
                'is_published' => true,
            ]
        );

        Page::clearSlugCache('pricing');
    }

    private function seedContact(): void
    {
        $layoutDoc = app(PageLayoutService::class)->validateDocumentFromArray([
            'version' => 1,
            'widgets' => [
                [
                    'id' => 'w_'.Str::lower(Str::random(10)),
                    'type' => 'contact_form',
                    'order' => 0,
                    'props' => ContactFormWidget::defaultProps(),
                ],
            ],
        ]);

        Page::query()->updateOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact Us',
                'content' => null,
                'layout' => $layoutDoc,
                'is_published' => true,
            ]
        );

        Page::clearSlugCache('contact');
    }

    private function seedFindMemorial(): void
    {
        $layoutDoc = app(PageLayoutService::class)->validateDocumentFromArray([
            'version' => 1,
            'widgets' => [
                [
                    'id' => 'w_'.Str::lower(Str::random(10)),
                    'type' => HeadingWidget::type(),
                    'order' => 0,
                    'props' => array_merge(HeadingWidget::defaultProps(), [
                        'level' => 1,
                        'text' => 'Find Memorial',
                        'alignment' => 'left',
                    ]),
                ],
                [
                    'id' => 'w_'.Str::lower(Str::random(10)),
                    'type' => MemorialDirectoryWidget::type(),
                    'order' => 1,
                    'props' => MemorialDirectoryWidget::defaultProps(),
                ],
            ],
        ]);

        Page::query()->updateOrCreate(
            ['slug' => Page::SLUG_FIND_MEMORIAL],
            [
                'title' => 'Find Memorial',
                'content' => null,
                'layout' => $layoutDoc,
                'is_published' => true,
            ]
        );

        Page::clearSlugCache(Page::SLUG_FIND_MEMORIAL);
    }
}
