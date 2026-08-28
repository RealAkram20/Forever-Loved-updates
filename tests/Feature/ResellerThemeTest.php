<?php

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use App\Models\Page;
use App\Models\ResellerSetting;
use App\Models\SystemSetting;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Two resellers used to get the same site with different hues. These cover the layer that
 * changed that: a template is a directory of blades prepended to the view finder for whoever
 * is being served.
 *
 * The tests that matter most here are the leak ones. Prepending a location mutates a *shared*
 * finder that memoises name => path, so the failure mode is not "my theme did not apply" — it
 * is one reseller's header being served on another reseller's domain, or on the platform's.
 * That cannot happen under PHP-FPM, where the container is rebuilt per request; it absolutely
 * can under a long-running worker, and inside this test suite, which is the only place we can
 * reproduce it cheaply.
 */

/** A template on disk, created once for this file rather than per test. */
const FIXTURE_TEMPLATE = 'zz-test-template';

const FIXTURE_MARKER = 'data-fixture-hero';

function fixtureTemplatePath(): string
{
    return dirname(__DIR__, 2).'/themes/'.FIXTURE_TEMPLATE;
}

beforeAll(function () {
    $path = fixtureTemplatePath();

    @mkdir($path.'/site-blocks', 0777, true);
    @mkdir($path.'/pages/visitor', 0777, true);

    file_put_contents($path.'/theme.json', json_encode([
        'name' => 'Test Template',
        'description' => 'A template that exists only while this test file runs.',
        // One token, so the layering can be checked without depending on any real theme
        // shipping a palette.
        'tokens' => ['branding.bg_light' => '#123456'],
        'default_home_blocks' => [['type' => 'hero', 'props' => []]],
    ]));

    // Overrides exactly one view. That is the property worth proving: a template inherits
    // everything it does not mention, so a second theme is a handful of files rather than a
    // fork of two dozen.
    file_put_contents(
        $path.'/site-blocks/hero.blade.php',
        '<section '.FIXTURE_MARKER.'>A different hero entirely.</section>'
    );

    // And a front page of its own, which is what a real template ships. Its presence is the
    // thing ActiveTheme::ownsView() reports on, and the reason it outranks the platform's
    // page-builder layout — see the "whose front page wins" group below.
    file_put_contents(
        $path.'/pages/visitor/home.blade.php',
        "@extends('layouts.visitor')
@section('page')
@include('site-blocks.hero')
@endsection
"
    );
});

afterAll(function () {
    $path = fixtureTemplatePath();

    foreach (['/site-blocks/hero.blade.php', '/pages/visitor/home.blade.php', '/theme.json'] as $file) {
        @unlink($path.$file);
    }

    @rmdir($path.'/site-blocks');
    @rmdir($path.'/pages/visitor');
    @rmdir($path.'/pages');
    @rmdir($path);
});

function themeTenant(string $slug = 'acme'): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function themeHost(Reseller $reseller): string
{
    return 'http://'.$reseller->slug.'.'.config('reseller.domain');
}

/** The catalogue row for the fixture template, created the way a deploy creates them. */
function fixtureTheme(): Theme
{
    ThemeCatalogue::sync();

    return Theme::whereNull('reseller_id')->where('template', FIXTURE_TEMPLATE)->firstOrFail();
}

/*
|--------------------------------------------------------------------------
| The base template
|--------------------------------------------------------------------------
*/

it('renders the base template for a reseller who has chosen nothing', function () {
    $acme = themeTenant();

    expect($acme->theme_id)->toBeNull()
        ->and($acme->templateSlug())->toBe(ThemeRegistry::BASE);

    $this->get(themeHost($acme).'/')
        ->assertOk()
        // The Basic hero, which is where the current design now lives.
        ->assertSee('hero-wash-dark', false)
        ->assertDontSee(FIXTURE_MARKER, false);
});

it('leaves the platform its own site', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('hero-wash-dark', false)
        ->assertDontSee(FIXTURE_MARKER, false);
});

/*
|--------------------------------------------------------------------------
| A template actually applying
|--------------------------------------------------------------------------
*/

it('renders a chosen template in place of the base one', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    $this->get(themeHost($acme).'/')
        ->assertOk()
        ->assertSee(FIXTURE_MARKER, false)
        // The one view the template overrides is gone; everything it does not mention is not.
        ->assertDontSee('hero-wash-dark', false);
});

it('inherits every view the template does not override', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    // The template ships a hero and nothing else, so the rest of the page still has to render
    // — footer, header, the other home blocks. A theme that has to be complete is a theme
    // that rots.
    $this->get(themeHost($acme).'/')
        ->assertOk()
        ->assertSee(FIXTURE_MARKER, false)
        ->assertSee('</footer>', false);
});

/*
|--------------------------------------------------------------------------
| Leakage — the reason this is a view-path layer and not a forked tree
|--------------------------------------------------------------------------
*/

it('does not carry one reseller\'s template onto the platform\'s own site', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    $this->get(themeHost($acme).'/')->assertSee(FIXTURE_MARKER, false);

    // Absolute, matching ResellerHostScopingTest: a reseller request leaves URL::forceRootUrl
    // pointed at their host, so a relative get('/') would be served as *theirs* and the
    // assertion below would be testing nothing.
    //
    // Same process, same container, same view finder.
    $this->get('http://localhost/')
        ->assertOk()
        ->assertDontSee(FIXTURE_MARKER, false)
        ->assertSee('hero-wash-dark', false);
});

it('does not carry one reseller\'s template onto another reseller\'s site', function () {
    $acme = themeTenant('acme');
    $acme->update(['theme_id' => fixtureTheme()->id]);

    $beta = themeTenant('beta');

    $this->get(themeHost($acme).'/')->assertSee(FIXTURE_MARKER, false);

    $this->get(themeHost($beta).'/')
        ->assertOk()
        ->assertDontSee(FIXTURE_MARKER, false)
        ->assertSee('hero-wash-dark', false);
});

it('applies the template again after serving the platform in between', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    // The finder is flushed on every change, so a template must come back as readily as it
    // goes away — a one-way flush would leave a themed site rendering the base design for the
    // rest of the worker's life.
    $this->get(themeHost($acme).'/')->assertSee(FIXTURE_MARKER, false);
    $this->get('http://localhost/')->assertDontSee(FIXTURE_MARKER, false);
    $this->get(themeHost($acme).'/')->assertSee(FIXTURE_MARKER, false);
});

/*
|--------------------------------------------------------------------------
| A template that is not deployed
|--------------------------------------------------------------------------
*/

it('renders the base template when the chosen one is not on disk', function () {
    $acme = themeTenant();

    $theme = Theme::create([
        'reseller_id' => null,
        'name' => 'Ghost',
        'slug' => 'ghost',
        'template' => 'never-deployed',
        'is_published' => true,
    ]);

    $acme->update(['theme_id' => $theme->id]);

    // A site in the wrong design is recoverable. A site returning 500 to every visitor is not.
    $this->get(themeHost($acme).'/')
        ->assertOk()
        ->assertSee('hero-wash-dark', false);

    expect($theme->templateIsMissing())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Whose front page wins
|--------------------------------------------------------------------------
*/

it('lets a template supply the front page over the platform builder layout', function () {
    // The platform ships a home layout as a fallback for tenants who have built nothing. It
    // used to win over every template, so a themed site served *our* arrangement of blocks —
    // the one thing a theme exists to prevent. Found only by looking at a render; the theme
    // engine itself was working perfectly.
    Page::updateOrCreate(
        ['slug' => Page::SLUG_VISITOR_HOME, 'reseller_id' => null],
        ['title' => 'Home', 'is_published' => true, 'layout' => ['widgets' => [['type' => 'heading', 'props' => ['text' => 'PLATFORM LAYOUT']]]]],
    );
    Page::clearSlugCache(Page::SLUG_VISITOR_HOME, null);

    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    $this->get(themeHost($acme).'/')
        ->assertOk()
        ->assertSee(FIXTURE_MARKER, false)
        ->assertDontSee('PLATFORM LAYOUT', false);
});

it('still lets a reseller built layout outrank their template', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    // Theirs, made on purpose. A template must not overrule that.
    Page::updateOrCreate(
        ['slug' => Page::SLUG_VISITOR_HOME, 'reseller_id' => $acme->id],
        ['title' => 'Home', 'is_published' => true, 'layout' => ['widgets' => [['type' => 'heading', 'props' => ['text' => 'THEIR OWN LAYOUT']]]]],
    );
    Page::clearSlugCache(Page::SLUG_VISITOR_HOME, $acme->id);

    $this->get(themeHost($acme).'/')
        ->assertOk()
        ->assertSee('THEIR OWN LAYOUT', false)
        ->assertDontSee(FIXTURE_MARKER, false);
});

/*
|--------------------------------------------------------------------------
| Tokens
|--------------------------------------------------------------------------
*/

it('uses a theme token over the platform default', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');

    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    app()->instance(Reseller::class, $acme->fresh());
    ThemeSetting::forgetThemeTokens();

    expect(ThemeSetting::get('branding.bg_light'))->toBe('#123456');

    app()->forgetInstance(Reseller::class);
});

it('keeps the reseller\'s own value above the theme\'s', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');

    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);
    ResellerSetting::set($acme->id, 'branding.bg_light', '#abcdef');

    app()->instance(Reseller::class, $acme->fresh());
    ThemeSetting::forgetThemeTokens();

    // Applying a theme must never quietly discard colours somebody tuned by hand.
    expect(ThemeSetting::get('branding.bg_light'))->toBe('#abcdef');

    app()->forgetInstance(Reseller::class);
});

it('ignores a theme token outside the appearance vocabulary', function () {
    $acme = themeTenant();

    $theme = Theme::create([
        'reseller_id' => null,
        'name' => 'Overreaching',
        'slug' => 'overreaching',
        'template' => ThemeRegistry::BASE,
        // Not a colour, and not a reseller's to set — these live in the same key namespace as
        // SMTP credentials and payment keys.
        'tokens' => ['payments.pesapal_consumer_secret' => 'stolen', 'branding.bg_light' => '#222222'],
        'is_published' => true,
    ]);

    expect($theme->tokenValues())->toBe(['branding.bg_light' => '#222222']);
});

/*
|--------------------------------------------------------------------------
| The Theme page
|--------------------------------------------------------------------------
*/

it('shows the catalogue and marks what the site is using', function () {
    $acme = themeTenant();
    fixtureTheme();

    $this->actingAs($acme->owner)
        ->get('/reseller/theme')
        ->assertOk()
        ->assertSee('Basic')
        ->assertSee('Test Template')
        ->assertSee('Active');
});

it('applies a theme from the gallery', function () {
    $acme = themeTenant();
    $theme = fixtureTheme();

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theme->id])
        ->assertRedirect(route('reseller.theme'));

    expect($acme->fresh()->theme_id)->toBe($theme->id);
});

it('refuses a theme belonging to another reseller', function () {
    $acme = themeTenant('acme');
    $beta = themeTenant('beta');

    $betas = Theme::create([
        'reseller_id' => $beta->id,
        'name' => 'Beta House Style',
        'slug' => 'beta-house-style',
        'template' => ThemeRegistry::BASE,
        'is_published' => true,
    ]);

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $betas->id])
        ->assertSessionHasErrors('theme_id');

    expect($acme->fresh()->theme_id)->toBeNull();
});

it('saves the current look as a theme of their own without changing the site', function () {
    $acme = themeTenant();
    ResellerSetting::set($acme->id, 'branding.bg_light', '#101010');

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/save', ['name' => 'Our 2026 look'])
        ->assertRedirect(route('reseller.theme'));

    $saved = Theme::where('reseller_id', $acme->id)->firstOrFail();

    expect($saved->name)->toBe('Our 2026 look')
        ->and($saved->template)->toBe(ThemeRegistry::BASE)
        ->and($saved->tokenValues())->toMatchArray(['branding.bg_light' => '#101010'])
        // Inert until applied somewhere: a save button that changes the live site is one
        // nobody presses twice.
        ->and($acme->fresh()->theme_id)->toBeNull();
});

it('refuses to delete the theme the site is running', function () {
    $acme = themeTenant();

    $saved = Theme::create([
        'reseller_id' => $acme->id,
        'name' => 'In Use',
        'slug' => 'in-use',
        'template' => ThemeRegistry::BASE,
        'is_published' => true,
    ]);

    $acme->update(['theme_id' => $saved->id]);

    $this->actingAs($acme->owner)
        ->delete('/reseller/theme/'.$saved->id)
        ->assertSessionHasErrors('theme');

    expect(Theme::find($saved->id))->not->toBeNull();
});

it('does not let a reseller delete another reseller\'s theme', function () {
    $acme = themeTenant('acme');
    $beta = themeTenant('beta');

    $betas = Theme::create([
        'reseller_id' => $beta->id,
        'name' => 'Beta House Style',
        'slug' => 'beta-house-style',
        'template' => ThemeRegistry::BASE,
        'is_published' => true,
    ]);

    $this->actingAs($acme->owner)
        ->delete('/reseller/theme/'.$betas->id)
        ->assertForbidden();

    expect(Theme::find($betas->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Links on a reseller site point at the reseller site
|--------------------------------------------------------------------------
*/

it('points a reseller menu Home link at their own front page, not the platform', function () {
    $acme = themeTenant();

    // ResellerSiteProvisioner already gave them a header menu on creation, and the location
    // is unique per tenant — so take theirs rather than making a second one.
    $menu = \App\Models\Menu::firstOrCreate(
        ['reseller_id' => $acme->id, 'location' => \App\Models\Menu::LOCATION_HEADER],
        ['title' => 'Header', 'is_active' => true],
    );

    $item = \App\Models\MenuItem::create([
        'menu_id' => $menu->id,
        'label' => 'Home',
        'route_name' => 'home',
        'sort_order' => 0,
    ]);

    app()->instance(Reseller::class, $acme);
    ThemeSetting::markResolvedFromRequest();

    // A bare "/" resolves against whatever host is serving the page. On a subdomain that is
    // theirs; under the /r/{slug} fallback it is the platform's front page, which is the one
    // place a white-labeled nav must never send anyone.
    expect($item->resolvedUrl())
        ->not->toBe('/')
        ->toBe($acme->publicBaseUrl());

    app()->forgetInstance(Reseller::class);
});

it('sends a lost visitor back to the reseller front page, not the platform', function () {
    $acme = themeTenant();

    // Every dead end on a white-labeled site is a chance to hand the visitor to us. The 404
    // button used to be url('/'), which is pinned to APP_URL — so mistyping an address on a
    // funeral home's domain walked you onto the platform's homepage.
    $response = $this->get(themeHost($acme).'/definitely-not-a-page');

    $response->assertNotFound()
        ->assertSee($acme->publicBaseUrl(), false)
        ->assertDontSee('href="'.rtrim(config('app.url'), '/').'"', false);
});

it('points the account link at a route that exists, not a tenant page', function () {
    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    // SiteUrl::to('dashboard') built {reseller base}/dashboard, which under the /r/{slug}
    // fallback is /r/acme/dashboard — not a route at all, so the link in a reseller's own
    // header 404'd. The dashboard is an application screen that exists on every host, not a
    // page on their public site.
    $response = $this->actingAs($acme->owner)->get(themeHost($acme).'/');

    $response->assertOk()
        ->assertDontSee($acme->publicUrlForSlug('dashboard'), false);
});

/*
|--------------------------------------------------------------------------
| The admin catalogue screen
|--------------------------------------------------------------------------
*/

it('shows a super-admin every template and how many sites use it', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = themeTenant();
    $acme->update(['theme_id' => fixtureTheme()->id]);

    $this->actingAs($admin)
        ->get('/settings/themes')
        ->assertOk()
        ->assertSee('Basic')
        ->assertSee('Test Template')
        ->assertSee('Sync from disk');
});

it('hides a theme from the gallery without moving the sites already on it', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = themeTenant();
    $theme = fixtureTheme();
    $acme->update(['theme_id' => $theme->id]);

    $this->actingAs($admin)->post('/settings/themes/'.$theme->id.'/publish')->assertRedirect();

    expect($theme->fresh()->is_published)->toBeFalse()
        // Unpublish means "stop offering it", not "move everyone off it".
        ->and($acme->fresh()->theme_id)->toBe($theme->id);

    // And it is gone from what a reseller may pick.
    expect(Theme::selectableFor($acme->id)->pluck('id'))->not->toContain($theme->id);
});

it('keeps the admin catalogue away from resellers', function () {
    $acme = themeTenant();

    $this->actingAs($acme->owner)->get('/settings/themes')->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| The catalogue
|--------------------------------------------------------------------------
*/

it('makes every template on disk selectable', function () {
    ThemeCatalogue::sync();

    $slugs = Theme::whereNull('reseller_id')->pluck('template')->all();

    expect($slugs)->toContain(ThemeRegistry::BASE)
        ->and($slugs)->toContain(FIXTURE_TEMPLATE);
});

it('is safe to sync repeatedly', function () {
    ThemeCatalogue::sync();
    ThemeCatalogue::sync();
    ThemeCatalogue::sync();

    expect(Theme::whereNull('reseller_id')->where('template', ThemeRegistry::BASE)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A template ships the page it is meant to look like
|--------------------------------------------------------------------------
*/

it('renders a themed front page from the template manifest when the tenant has built none', function () {
    // The fallback front page used to be hand-written blades that duplicated the widgets a
    // reseller could actually edit — so the page being designed against and the page being
    // edited were two different implementations. It is now the same document as the builder's.
    $manifest = app(\App\Themes\ThemeRegistry::class)->manifest('dignified');

    expect($manifest)->not->toBeNull()
        ->and($manifest->defaultPages)->toHaveKey('visitor-home')
        ->and($manifest->defaultPages['visitor-home']['widgets'])->not->toBeEmpty();

    // Every widget the template ships must actually be a registered type, or applying the
    // theme hands a reseller a page the builder cannot open.
    $registry = app(\App\PageBuilder\WidgetRegistry::class);

    foreach ($manifest->defaultPages as $slug => $document) {
        foreach ($document['widgets'] as $widget) {
            expect($registry->classForType($widget['type']))
                ->not->toBeNull("template ships unknown widget '{$widget['type']}' on page '{$slug}'");
        }
    }
});

it('keeps the shipped document valid against its own widgets rules', function () {
    // A manifest is written by us, not by a reseller — but a value that fails validation would
    // land as a 500 on a front page the moment someone applied the theme.
    $manifest = app(\App\Themes\ThemeRegistry::class)->manifest('dignified');

    $validated = app(\App\Services\PageLayoutService::class)
        ->validateDocumentFromArray($manifest->defaultPages['visitor-home']);

    expect($validated['widgets'])->toHaveCount(count($manifest->defaultPages['visitor-home']['widgets']));
});

it('hands a reseller the template pages to edit when they have built none', function () {
    $acme = themeTenant();
    $theme = \App\Models\Theme::whereNull('reseller_id')->where('template', 'dignified')->first();

    if (! $theme) {
        test()->markTestSkipped('The dignified template is not in the catalogue here.');
    }

    $home = \App\Models\Page::where('reseller_id', $acme->id)->where('slug', 'visitor-home')->first();
    expect($home?->hasLayout())->toBeFalse();

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theme->id])
        ->assertRedirect();

    // The page they now see and the page in their builder are the same document.
    expect($home->fresh()->hasLayout())->toBeTrue();
});

it('never replaces a page the reseller has already built', function () {
    $acme = themeTenant();
    $theme = \App\Models\Theme::whereNull('reseller_id')->where('template', 'dignified')->first();

    if (! $theme) {
        test()->markTestSkipped('The dignified template is not in the catalogue here.');
    }

    $home = \App\Models\Page::where('reseller_id', $acme->id)->where('slug', 'visitor-home')->firstOrFail();

    // An afternoon's work.
    $theirs = app(\App\Services\PageLayoutService::class)->validateDocumentFromArray([
        'widgets' => [['type' => 'heading', 'props' => ['text' => 'Our own front page']]],
    ]);
    $home->layout = $theirs;
    $home->save();

    $this->actingAs($acme->owner)->post('/reseller/theme/apply', ['theme_id' => $theme->id]);

    // Applying a theme changes how a site looks. It must never change what someone wrote.
    expect($home->fresh()->layout['widgets'][0]['props']['text'])->toBe('Our own front page');
});

it('creates the pages a template brings, and links between them', function () {
    $acme = themeTenant();
    $theme = \App\Models\Theme::whereNull('reseller_id')->where('template', 'dignified')->first();

    if (! $theme) {
        test()->markTestSkipped('The dignified template is not in the catalogue here.');
    }

    // A Services listing and a page per service are not standard pages — the platform has no
    // concept of them. A template that ships them must be able to create them, or the links in
    // its own navigation lead nowhere.
    $this->actingAs($acme->owner)->post('/reseller/theme/apply', ['theme_id' => $theme->id]);

    $created = \App\Models\Page::where('reseller_id', $acme->id)
        ->whereIn('slug', ['services', 'funeral-arrangements'])
        ->pluck('slug');

    expect($created)->toContain('services')->toContain('funeral-arrangements');
});

it('refuses to let a template claim a slug that would hide something', function () {
    $acme = themeTenant();

    // A reserved slug is a path the app already answers, so a page there is unreachable.
    // A slug already used by one of their memorials is worse — the memorial would be the thing
    // that disappeared, because a funeral home changed theme.
    $reflection = new ReflectionMethod(\App\Themes\ThemePages::class, 'mayCreate');
    $reflection->setAccessible(true);

    expect($reflection->invoke(null, $acme, 'pricing'))->toBeFalse()
        ->and($reflection->invoke(null, $acme, 'dashboard'))->toBeFalse()
        ->and($reflection->invoke(null, $acme, 'Not A Slug'))->toBeFalse()
        ->and($reflection->invoke(null, $acme, 'funeral-arrangements'))->toBeTrue();

    \App\Models\Memorial::factory()->create([
        'reseller_id' => $acme->id,
        'slug' => 'grace-mukasa',
    ]);

    expect($reflection->invoke(null, $acme, 'grace-mukasa'))->toBeFalse();
});
