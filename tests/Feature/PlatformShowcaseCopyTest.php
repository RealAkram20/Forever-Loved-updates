<?php

use App\Models\Page;
use App\Models\SiteLayout;
use App\Support\PlatformShowcaseCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The homepage showcase heading is stored data. These prove the migration path moves it in
 * both stores, moves it back, does nothing twice, and that the page then says the new words
 * and not the old paragraph.
 */
uses(RefreshDatabase::class);

function storedShowcasePage(array $props): Page
{
    return Page::forceCreate([
        'reseller_id' => null,
        'slug' => Page::SLUG_VISITOR_HOME,
        'title' => 'Home',
        'is_published' => true,
        'layout' => ['widgets' => [
            ['type' => 'hero', 'props' => []],
            ['type' => 'memorial_showcase', 'props' => $props + ['view_all_label' => 'stale']],
        ]],
    ]);
}

function showcaseProps(Page $page): array
{
    foreach ($page->fresh()->layout['widgets'] as $w) {
        if ($w['type'] === 'memorial_showcase') {
            return $w['props'];
        }
    }

    return [];
}

it('rewrites the stored page from the old copy to the new, and drops the dead prop', function () {
    $page = storedShowcasePage(PlatformShowcaseCopy::PREVIOUS);

    expect(PlatformShowcaseCopy::apply())->toBe(1);

    $props = showcaseProps($page);
    expect($props['eyebrow'])->toBe('Featured Memorials')
        ->and($props['title'])->toBe('Remembering Loved Ones')
        ->and($props['description'])->toBe('')
        ->and($props)->not->toHaveKey('view_all_label');
});

it('is idempotent — a second apply changes nothing and says so', function () {
    storedShowcasePage(PlatformShowcaseCopy::PREVIOUS);

    PlatformShowcaseCopy::apply();
    expect(PlatformShowcaseCopy::apply())->toBe(0);
});

it('is reversible — PREVIOUS puts the paragraph back', function () {
    $page = storedShowcasePage(PlatformShowcaseCopy::PREVIOUS);

    PlatformShowcaseCopy::apply(PlatformShowcaseCopy::CURRENT);
    PlatformShowcaseCopy::apply(PlatformShowcaseCopy::PREVIOUS);

    expect(showcaseProps($page)['title'])->toBe('Memorial Inspiration')
        ->and(showcaseProps($page)['description'])->toStartWith('Not sure where to begin?');
});

it('leaves a reseller\'s own showcase alone', function () {
    $reseller = \App\Models\Reseller::factory()->create();
    // Creating a reseller already leaves it a visitor-home row (the composite unique on
    // reseller_id+slug said so), so this writes onto that row rather than inserting another.
    $theirs = Page::updateOrCreate(
        ['reseller_id' => $reseller->id, 'slug' => Page::SLUG_VISITOR_HOME],
        ['title' => 'Home', 'is_published' => true,
         'layout' => ['widgets' => [['type' => 'memorial_showcase', 'props' => ['title' => 'Our Families']]]]]
    );

    PlatformShowcaseCopy::apply();

    expect(showcaseProps($theirs)['title'])->toBe('Our Families');
});

it('renders the new heading on the homepage and not the old paragraph', function () {
    storedShowcasePage(PlatformShowcaseCopy::PREVIOUS);
    PlatformShowcaseCopy::apply();

    // The block is `@if ($popularMemorials->isNotEmpty())` at the top: with nothing eligible it
    // renders no heading at all, and this test would be asserting against an empty section.
    \App\Models\Memorial::factory()->count(3)->create([
        'is_public' => true,
        'status' => \App\Models\Memorial::STATUS_ACTIVE,
        'reseller_id' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Featured Memorials')
        ->assertSee('Remembering Loved Ones')
        ->assertDontSee('Memorial Inspiration')
        ->assertDontSee('Not sure where to begin?');
});

it('has a migration that applies the current copy', function () {
    $page = storedShowcasePage(PlatformShowcaseCopy::PREVIOUS);

    $migration = require database_path('migrations/2026_09_07_000000_rename_platform_showcase_copy.php');
    $migration->up();

    expect(showcaseProps($page)['title'])->toBe('Remembering Loved Ones');

    $migration->down();

    expect(showcaseProps($page)['title'])->toBe('Memorial Inspiration');
});
