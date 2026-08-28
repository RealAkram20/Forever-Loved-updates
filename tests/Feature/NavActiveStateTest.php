<?php

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * "You are here", in the navigation.
 *
 * This was broken on **every reseller site, on every page, on every host** and nothing said so
 * — the nav simply never highlighted anything, which reads as a slightly plain design rather
 * than as a fault. On a white-labeled site the nav is the only thing telling a visitor where
 * they are, and a grieving family clicking between About and Contact had nothing to orient by.
 *
 * The cause: MenuItem::isActive() compared route *names*. Every item the menu builder creates
 * is stored as `cms.page` with a slug, while the pages themselves are served under four other
 * names depending only on how the site was reached — `reseller.about` on the path fallback,
 * `about` on a real host, and so on. The names could never match, so the comparison could
 * never be true. It now compares where the link actually goes.
 *
 * Asserted through `aria-current="page"` rather than a colour class: it is the same answer for
 * every template, it is what a screen reader announces, and a test that hunts for
 * `bg-[var(--dg-red)]` would pass a template into existence with the wrong markup.
 */
function navTenant(string $slug, ?string $template = null): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    // Creating a Reseller provisions its standard pages and menus — see the model's booted().
    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    if ($template !== null) {
        ThemeCatalogue::sync();
        $theme = Theme::whereNull('reseller_id')->where('template', $template)->first();

        if ($theme) {
            $reseller->update(['theme_id' => $theme->id]);
        }
    }

    return $reseller->fresh();
}

/** Every standard page a reseller's nav links to, and the label that should light up on it. */
dataset('nav pages', [
    'home' => ['', 'Home'],
    'about' => ['/about', 'About'],
    'pricing' => ['/pricing', 'Pricing'],
    'contact' => ['/contact', 'Contact'],
    'find a memorial' => ['/find-memorial', 'Find Memorial'],
]);

/**
 * Every anchor on the page that claims to be the current one.
 *
 * A page carries more than one nav — a desktop bar and a collapsed mobile menu — and both
 * should say where the visitor is. So the assertion is not "exactly one marker" but "every
 * marker is on the right link": counting would break the day a template adds a footer nav, and
 * would pass a nav that highlighted Home on every page.
 *
 * @return array<int, string>
 */
function navCurrentLabels(string $html): array
{
    preg_match_all('#<a[^>]*aria-current="page"[^>]*>\s*([^<]+?)\s*</a>#s', $html, $m);

    return $m[1] ?? [];
}

it('marks the current page in the navigation, on every reseller page', function (string $path, string $label) {
    $acme = navTenant('nav-acme', 'dignified');

    $labels = navCurrentLabels($this->get('/r/'.$acme->slug.$path)->assertOk()->getContent());

    // None at all was the bug — on every page of every reseller site.
    expect($labels)->not->toBeEmpty()
        ->and(array_unique($labels))->toBe([$label]);
})->with('nav pages');

it('does the same on the base template', function (string $path, string $label) {
    // The fix is in MenuItem, so it is not Dignified's. A template-specific fix would have
    // left every other theme — including the one most resellers run — still broken.
    $acme = navTenant('nav-basic', null);

    $labels = navCurrentLabels($this->get('/r/'.$acme->slug.$path)->assertOk()->getContent());

    expect($labels)->not->toBeEmpty()
        ->and(array_unique($labels))->toBe([$label]);
})->with('nav pages');

it('leaves the platform its own current page', function () {
    $this->get('/')->assertOk()->assertSee('aria-current="page"', false);
    $this->get('/about')->assertOk()->assertSee('aria-current="page"', false);
});

it('matches a menu item stored as a cms page against the route that actually serves it', function () {
    // The bug in one assertion. Every item the menu builder writes looks like this — a
    // `cms.page` route name and a slug — and the page it points at is served under a different
    // route name entirely. Comparing names could never be true; comparing addresses is.
    $acme = navTenant('nav-cms', 'dignified');

    $menu = Menu::where('reseller_id', $acme->id)->where('location', Menu::LOCATION_HEADER)->firstOrFail();
    $items = $menu->rootMenuItems;

    expect($items)->not->toBeEmpty()
        ->and($items->every(fn (MenuItem $i) => $i->route_name === 'cms.page'))->toBeTrue();

    $home = $items->firstWhere(fn (MenuItem $i) => ($i->route_parameters['slug'] ?? null) === Page::SLUG_VISITOR_HOME);

    expect($home)->not->toBeNull();

    // The route serving a reseller's front page on the path fallback is not called 'cms.page'
    // and never will be.
    $this->get('/r/'.$acme->slug)->assertOk();

    expect(request()->route()?->getName())->not->toBe('cms.page');
});
