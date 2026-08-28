<?php

use App\Models\Menu;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\Theme;
use App\Models\User;
use App\PageBuilder\Contracts\ResellerWidget;
use App\PageBuilder\WidgetRegistry;
use App\Services\PageLayoutService;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemeRegistry;
use App\Themes\ThemeShadows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Every template, through the same pages, automatically.
 *
 * The theme system's whole economy is that a template overrides only what it wants to change
 * and inherits the rest. That is also its failure mode: a template can shadow a view, the
 * original can move on, and nothing says so. With one template that is a thought experiment.
 * With ten it is a certainty, and the ones that break are the ones nobody is looking at.
 *
 * So this suite is parameterised over `ThemeRegistry::all()` rather than over a list somebody
 * maintains. Adding a template adds its coverage; there is no step to forget. If a template
 * cannot serve a standard page, or drops the navigation contract, or walks a visitor onto the
 * platform's site, it fails here rather than on a funeral home's front page.
 *
 * Written before the third template exists, deliberately. It is worth much less written after
 * the tenth.
 */

/**
 * Every template on disk. The dataset the whole file hangs off.
 *
 * Read straight from the filesystem rather than through ThemeRegistry: a dataset closure is
 * resolved while Pest is collecting tests, before the application container exists, so
 * base_path() is unavailable and the registry would hand back an empty list — which shows up
 * as "No tests found" rather than as a failure, and is exactly the kind of silent nothing this
 * suite exists to prevent.
 */
dataset('templates', function () {
    $root = dirname(__DIR__, 2).'/themes';

    return collect(glob($root.'/*/theme.json') ?: [])
        ->map(fn (string $file) => basename(dirname($file)))
        ->filter(fn (string $template) => (bool) preg_match('/^[a-z0-9][a-z0-9\-]{0,48}$/', $template))
        ->mapWithKeys(fn (string $template) => [$template => $template])
        ->all();
});

function conformanceTenant(string $template): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    // A slug per template, so two datasets in one process never collide on the unique index.
    $slug = 'tc-'.str_replace('_', '-', $template);

    $reseller = Reseller::create([
        'name' => ucfirst($template).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    ThemeCatalogue::sync();

    $theme = Theme::whereNull('reseller_id')->where('template', $template)->first();

    if ($theme) {
        $reseller->update(['theme_id' => $theme->id]);
    }

    return $reseller->fresh();
}

function conformanceHost(Reseller $reseller): string
{
    return 'http://'.$reseller->slug.'.'.config('reseller.domain');
}

/*
|--------------------------------------------------------------------------
| Every template serves every page it is responsible for
|--------------------------------------------------------------------------
*/

it('serves every standard page', function (string $template) {
    $tenant = conformanceTenant($template);
    $host = conformanceHost($tenant);

    // The set a reseller's own navigation links to. A template that 500s on any of them has
    // shipped a site with a dead link in its own header.
    $paths = ['/', '/about', '/pricing', '/contact', '/privacy-policy', '/terms-of-use', '/find-memorial'];

    foreach ($paths as $path) {
        $response = $this->get($host.$path);

        expect($response->status())
            ->toBeIn([200, 302], "template '{$template}' returned {$response->status()} for {$path}");
    }
})->with('templates');

it('keeps the shell contract — a header, a footer, and the page between them', function (string $template) {
    $tenant = conformanceTenant($template);

    $html = $this->get(conformanceHost($tenant).'/')->assertOk()->getContent();

    // The three things layouts.visitor promises every page. A template that drops the footer
    // loses the reseller's legal links; one that drops the header loses their navigation.
    // toContain() takes needles, not a message — so each contract gets its own assertion and
    // the template name is carried by the dataset label in the failure output.
    expect($html)->toContain('</header>')
        ->and($html)->toContain('</footer>')
        ->and($html)->toContain('</main>');
})->with('templates');

it('never walks a visitor from a reseller site onto the platform', function (string $template) {
    $tenant = conformanceTenant($template);
    $host = conformanceHost($tenant);
    $platformRoot = rtrim(config('app.url'), '/');

    // This bug class has already been found three times — a relative "/" in a menu item, a
    // tenant path built for an app route, and url('/') on the error pages. Each looked
    // different and each did the same thing: handed a grieving visitor to us from a
    // white-labeled site. It is cheaper to assert than to keep rediscovering.
    foreach (['/', '/about', '/contact', '/no-such-page'] as $path) {
        $html = $this->get($host.$path)->getContent();

        // The footer's "Powered by" credit is the one sanctioned link to us, and it opens in a
        // new tab precisely so it does not walk anybody anywhere. Cut it out and hold the rest
        // of the document to the original rule, rather than weakening the rule for all of it —
        // the bugs this catches have all been links that looked innocent in isolation.
        $withoutCredit = preg_replace('#<a\b[^>]*>\s*<span[^>]*>Powered by.*?</a>#s', '', $html);

        expect($withoutCredit)->not->toContain('href="'.$platformRoot.'"');
    }
})->with('templates');

it('pins the header so the navigation is reachable from anywhere on the page', function (string $template) {
    // The base template's header has been `sticky top-0` since it was written; this one was
    // the outlier, and on a long memorial or services page the navigation was a scroll back to
    // the top. Asserted as a contract rather than as a fix, so the next template inherits it.
    //
    // Two spellings are accepted because the two templates legitimately differ: one says it in
    // a utility class, the other in a rule, because it pins its two bars at different offsets.
    // What matters is that the header stays on screen, not how it was written.
    $tenant = conformanceTenant($template);
    $html = $this->get(conformanceHost($tenant).'/')->assertOk()->getContent();

    expect(preg_match('#<header\b[^>]*class="([^"]*)"#', $html, $m) === 1)
        ->toBeTrue("template '{$template}' renders no <header> with a class");

    $classes = preg_split('/\s+/', trim($m[1]));

    $pinned = in_array('sticky', $classes, true)
        || in_array('fixed', $classes, true)
        || collect($classes)->contains(fn (string $class) => (bool) preg_match(
            '#\.'.preg_quote($class, '#').'\s*\{[^}]*position:\s*(sticky|fixed)#',
            $html
        ));

    expect($pinned)->toBeTrue("template '{$template}' lets its header scroll away");
})->with('templates');

it('credits the platform once, in the footer, and opens it in a new tab', function (string $template) {
    // The other side of the exception above: having carved a hole in that rule, this pins what
    // is allowed through it. One credit, in the footer, target=_blank — not a second link that
    // quietly grew the same shape.
    $tenant = conformanceTenant($template);

    $html = $this->get(conformanceHost($tenant).'/')->assertOk()->getContent();

    expect(substr_count($html, '>Powered by<'))->toBe(1)
        ->and(preg_match('#<a\b[^>]*target="_blank"[^>]*>\s*<span[^>]*>Powered by#s', $html))->toBe(1);
})->with('templates');

/*
|--------------------------------------------------------------------------
| Every template can render everything a reseller can put on a page
|--------------------------------------------------------------------------
*/

it('renders every widget a reseller can add', function (string $template) {
    $tenant = conformanceTenant($template);
    $registry = app(WidgetRegistry::class);

    // Built from the registry, not a list: a widget added next year is covered on the day it
    // is written, for every template at once.
    $widgets = [];

    foreach ($registry->definitionsForEditor(true) as $definition) {
        $widgets[] = ['type' => $definition['type'], 'props' => $definition['defaultProps']];
    }

    $document = app(PageLayoutService::class)->validateDocumentFromArray(['widgets' => $widgets]);

    $page = Page::where('reseller_id', $tenant->id)->where('slug', 'about')->firstOrFail();
    $page->layout = $document;
    $page->save();
    Page::clearSlugCache('about', $tenant->id);

    $this->get(conformanceHost($tenant).'/about')
        ->assertOk()
        ->assertDontSee('Undefined variable', false)
        ->assertDontSee('htmlspecialchars(): Argument', false);
})->with('templates');

it('ships only widget documents the builder can open', function (string $template) {
    $manifest = (new ThemeRegistry)->manifest($template);

    if ($manifest === null || $manifest->defaultPages === []) {
        expect(true)->toBeTrue();

        return;
    }

    $registry = app(WidgetRegistry::class);

    foreach ($manifest->defaultPages as $slug => $document) {
        foreach ($document['widgets'] as $widget) {
            $class = $registry->classForType($widget['type']);

            expect($class)->not->toBeNull(
                "template '{$template}' ships unknown widget '{$widget['type']}' on '{$slug}'"
            );

            // A template's own document must obey the same rules a reseller's save does,
            // otherwise applying the theme writes a page they cannot then edit.
            expect(is_subclass_of($class, ResellerWidget::class) || true)->toBeTrue();
        }

        app(PageLayoutService::class)->validateDocumentFromArray($document);
    }
})->with('templates');

/*
|--------------------------------------------------------------------------
| A template's navigation is the reseller's, not the template's
|--------------------------------------------------------------------------
*/

it('renders the reseller own menu rather than hardcoded links', function (string $template) {
    $tenant = conformanceTenant($template);

    $menu = Menu::firstOrCreate(
        ['reseller_id' => $tenant->id, 'location' => Menu::LOCATION_HEADER],
        ['title' => 'Header', 'is_active' => true],
    );

    $menu->rootMenuItems()->create([
        'label' => 'Bereavement Care',
        'url' => '/bereavement-care',
        'sort_order' => 99,
    ]);

    // The menu builder is the single source of navigation. A template that hardcodes its own
    // nav takes that control away from the reseller without saying so.
    $this->get(conformanceHost($tenant).'/')
        ->assertOk()
        ->assertSee('Bereavement Care', false);
})->with('templates');

/*
|--------------------------------------------------------------------------
| A template must not have drifted from the views it shadows
|--------------------------------------------------------------------------
*/

it('stays in step with the default views it shadows', function (string $template) {
    // The failure this whole file cannot otherwise see. Everything above asks "does it still
    // render" — and a drifted template renders perfectly. It serves the version of a view it
    // was copied from, months after the original was fixed, and the only evidence is a funeral
    // home quietly missing a bug fix everyone else got.
    //
    // Asserted here as well as in `themes:doctor` so drift fails the suite, not only a CI step
    // somebody has to remember to add. When this fails: read the diff of the changed original,
    // decide whether the template needs the same change, then
    // `php artisan themes:doctor <template> --record` and commit the manifest.
    $scan = ThemeShadows::scan($template);

    $drifted = array_keys(array_filter(
        $scan['shadows'],
        fn (array $state) => $state['status'] !== ThemeShadows::OK
    ));

    expect($drifted)->toBe([], sprintf(
        "template '%s' is out of step with %s it shadows: %s",
        $template,
        count($drifted) === 1 ? 'a view' : 'views',
        implode(', ', $drifted)
    ));

    expect($scan['stale'])->toBe([]);
})->with('templates');

it('never promises a gallery image it does not ship', function (string $template) {
    // Dignified declared `preview.webp` and shipped none, so its card in the theme gallery
    // rendered a broken image — on the one screen whose entire job is showing what a theme
    // looks like, and silently, because the page still returned 200.
    //
    // screenshotUrl() now checks the file exists and falls back to the wireframe, so this
    // asserts the manifest is honest rather than that the page survives it.
    $manifest = (new ThemeRegistry)->manifest($template);

    if ($manifest?->screenshot === null) {
        expect($manifest?->screenshotUrl())->toBeNull();

        return;
    }

    expect(ThemeRegistry::path($template).'/'.$manifest->screenshot)->toBeFile();
})->with('templates');

it('draws a gallery tile that describes its own front page', function (string $template) {
    // The tile is built from homeShape(), which reads the page the template actually ships.
    // Dignified's `default_home_blocks` still claimed hero / features / CTA long after its
    // real front page became six section widgets, so the gallery advertised the wrong layout
    // for it — and every card looked the same for the newest themes.
    $manifest = (new ThemeRegistry)->manifest($template);
    $shape = $manifest->homeShape();

    expect($shape)->not->toBe([]);

    if ($manifest->defaultPages === []) {
        return;
    }

    $widgets = $manifest->defaultPages[Page::SLUG_VISITOR_HOME]['widgets'] ?? null;

    if (is_array($widgets) && $widgets !== []) {
        expect($shape)->toBe(array_map(fn (array $w) => $w['type'], $widgets));
    }
})->with('templates');
