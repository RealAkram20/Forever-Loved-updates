<?php

use App\Models\Reseller;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Looking at a theme before it becomes your live site.
 *
 * Applying used to be the only way to find out what a theme looked like, on the real site, for
 * everyone, immediately. This is the way out of that — and it is the feature with the largest
 * gap between "works" and "safe", so most of what is asserted here is what it must *not* do.
 *
 * The three failures worth naming, because each was a live possibility in the design:
 *
 *  1. **Anyone re-skinning a public site with a link.** The obvious implementation is
 *     `?theme=`, and it hands every visitor a defacement primitive. The preview lives in the
 *     previewer's session and is reachable only through a signed, short-lived handoff.
 *  2. **A cache serving a preview to real visitors.** Any proxy in front of a reseller's site
 *     will happily store one staff member's preview under the plain public URL. Previewed
 *     responses carry no-store.
 *  3. **A preview that persists.** Nothing here may write to resellers.theme_id — a preview
 *     that could would be an apply with extra steps and a worse name.
 */
function previewTenant(string $slug = 'preview-acme'): Reseller
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

    ThemeCatalogue::sync();

    return $reseller->fresh();
}

/** The template a preview switches *to*. Real, so what is asserted is a real difference. */
function previewTheme(): Theme
{
    return Theme::whereNull('reseller_id')->where('template', 'dignified')->firstOrFail();
}

/**
 * The marker that says the base template rendered.
 *
 * A reseller who has chosen nothing runs `basic`, whose home is the platform's block
 * renderer. Dignified ships a front page of its own, so the marker disappears the moment the
 * preview takes — which is the whole observable effect, stated once here.
 */
const PREVIEW_BASE_MARKER = 'hero-wash-dark';

/*
|--------------------------------------------------------------------------
| It works
|--------------------------------------------------------------------------
*/

it('renders the previewed template instead of the applied one', function () {
    $acme = previewTenant();

    $this->get('/r/'.$acme->slug)->assertOk()->assertSee(PREVIEW_BASE_MARKER, false);

    $this->get(ThemePreview::linkFor($acme, previewTheme()))
        ->assertRedirect($acme->publicBaseUrl());

    $this->get('/r/'.$acme->slug)
        ->assertOk()
        ->assertDontSee(PREVIEW_BASE_MARKER, false);
});

it('previews the palette as well as the markup', function () {
    // A preview that swapped the blades but kept the applied theme's colours would show
    // Dignified in somebody else's brand and answer a question nobody asked.
    $acme = previewTenant();

    $this->get('/r/'.$acme->slug)->assertOk()->assertDontSee('#BB1520', false);

    $this->get(ThemePreview::linkFor($acme, previewTheme()));

    $this->get('/r/'.$acme->slug)->assertOk()->assertSee('#BB1520', false);
});

it('says on every page that this is a preview', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));

    // Welded onto the response rather than into a layout, so a template cannot drop it —
    // and a template that dropped it would look exactly like a site already changed.
    $this->get('/r/'.$acme->slug)
        ->assertOk()
        ->assertSee('tpv-bar', false)
        ->assertSee('Previewing <strong>Dignified</strong>', false);
});

it('ends the preview when asked', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));
    $this->get('/r/'.$acme->slug)->assertDontSee(PREVIEW_BASE_MARKER, false);

    $this->get('/r/'.$acme->slug.'/theme-preview/stop')
        ->assertRedirect($acme->publicBaseUrl());

    $this->get('/r/'.$acme->slug)->assertOk()->assertSee(PREVIEW_BASE_MARKER, false);
});

it('addresses the handoff at the reseller own host when they have one', function () {
    // Production serves every reseller on a subdomain or their own domain, where the session
    // carrying the preview is on a *different host* from the dashboard that started it —
    // the case the signed handoff exists for, and the one the path fallback cannot show.
    //
    // Only the link is asserted here. The routes for a reseller subdomain are registered at
    // boot from config('reseller.domain'), so a config() change inside a test cannot conjure
    // that host into existence; the test below rides the host that *is* registered.
    config(['app.url' => 'https://forever.test', 'reseller.domain' => 'forever.test']);

    $acme = previewTenant();

    expect($acme->usingFallbackAddress())->toBeFalse()
        ->and(ThemePreview::linkFor($acme, previewTheme()))
        ->toStartWith('https://'.$acme->slug.'.forever.test/theme-preview/'.previewTheme()->id.'?');
});

it('works on a real reseller host, not only the path fallback', function () {
    $acme = previewTenant();

    // The host the domain routes are actually registered for in this environment. The tenant
    // is resolved from the Host header here rather than from a path segment, which is a
    // different middleware (ResolveResellerByHost) and the one production runs.
    $host = 'http://'.$acme->slug.'.'.config('reseller.domain');

    $this->get($host.'/')->assertOk()->assertSee(PREVIEW_BASE_MARKER, false);

    // Signed relative, then addressed at that host — exactly what linkFor() builds for a
    // reseller whose subdomain this environment can serve.
    $link = $host.URL::temporarySignedRoute(
        'theme.preview.enter',
        now()->addMinutes(5),
        ['theme' => previewTheme()->id],
        absolute: false,
    );

    $this->get($link)->assertRedirect($acme->publicBaseUrl());

    $this->get($host.'/')
        ->assertOk()
        ->assertDontSee(PREVIEW_BASE_MARKER, false)
        ->assertSee('tpv-bar', false);
});

/*
|--------------------------------------------------------------------------
| It cannot be used against anyone
|--------------------------------------------------------------------------
*/

it('refuses an unsigned link', function () {
    $acme = previewTenant();

    // The whole reason this is not `?theme=`. Without the signature, the URL alone re-skins
    // a stranger's public site.
    $this->get('/r/'.$acme->slug.'/theme-preview/'.previewTheme()->id)
        ->assertForbidden();

    $this->get('/r/'.$acme->slug)->assertOk()->assertSee(PREVIEW_BASE_MARKER, false);
});

it('refuses a tampered link', function () {
    $acme = previewTenant();

    $tampered = ThemePreview::linkFor($acme, previewTheme()).'0';

    $this->get($tampered)->assertForbidden();
});

it('refuses an expired link', function () {
    $acme = previewTenant();

    $link = url(URL::temporarySignedRoute(
        'reseller.theme.preview.enter',
        now()->subMinute(),
        ['reseller' => $acme->slug, 'theme' => previewTheme()->id],
        absolute: false,
    ));

    $this->get($link)->assertForbidden();
});

it('refuses a theme the reseller cannot choose', function () {
    $acme = previewTenant();
    $other = previewTenant('preview-beta');

    // Another tenant's saved theme. The signature would be perfectly valid — it is the
    // re-check against the tenant the *host* resolved that stops it.
    $theirs = Theme::create([
        'reseller_id' => $other->id,
        'name' => 'Beta House Style',
        'slug' => 'beta-house-style',
        'template' => 'dignified',
        'tokens' => [],
        'is_published' => true,
    ]);

    $link = url(URL::temporarySignedRoute(
        'reseller.theme.preview.enter',
        now()->addMinutes(5),
        ['reseller' => $acme->slug, 'theme' => $theirs->id],
        absolute: false,
    ));

    $this->get($link)->assertForbidden();
});

it('shows one reseller preview to nobody else', function () {
    $acme = previewTenant();
    $beta = previewTenant('preview-beta');

    $this->get(ThemePreview::linkFor($acme, previewTheme()));

    // Same browser, same session, a different tenant's site. The preview is about acme, and
    // saying so is the difference between a preview and a defacement.
    $this->get('/r/'.$beta->slug)
        ->assertOk()
        ->assertSee(PREVIEW_BASE_MARKER, false)
        ->assertDontSee('tpv-bar', false);

    // And never on ours.
    $this->get('/')->assertOk()->assertDontSee('tpv-bar', false);
});

it('shows the live site to a visitor while a preview is running', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));
    $this->get('/r/'.$acme->slug)->assertDontSee(PREVIEW_BASE_MARKER, false);

    // A different browser entirely. The preview lives in a session, so this is the assertion
    // that the public site is untouched — the promise the bar makes to the person previewing.
    $this->flushSession();

    $this->get('/r/'.$acme->slug)
        ->assertOk()
        ->assertSee(PREVIEW_BASE_MARKER, false)
        ->assertDontSee('tpv-bar', false);
});

it('keeps a previewed page out of every cache', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));

    // Otherwise a CDN stores one staff member's preview under the plain public URL and hands
    // it to real visitors — the cache poisoning the query-parameter design was rejected for,
    // arrived at by another route.
    $response = $this->get('/r/'.$acme->slug)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('changes nothing about the reseller record', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));
    $this->get('/r/'.$acme->slug);

    // A preview that could persist would be an apply with a worse name.
    expect($acme->fresh()->theme_id)->toBeNull();
});

it('expires on its own', function () {
    $acme = previewTenant();

    $this->get(ThemePreview::linkFor($acme, previewTheme()));
    $this->get('/r/'.$acme->slug)->assertDontSee(PREVIEW_BASE_MARKER, false);

    // A forgotten tab must not still be showing the wrong design tomorrow.
    $this->travel(ThemePreview::TTL_MINUTES + 1)->minutes();

    $this->get('/r/'.$acme->slug)->assertOk()->assertSee(PREVIEW_BASE_MARKER, false);
});

/*
|--------------------------------------------------------------------------
| Only their own staff can start one
|--------------------------------------------------------------------------
*/

it('lets a reseller start a preview of their own site', function () {
    $acme = previewTenant();

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/preview', ['theme_id' => previewTheme()->id])
        ->assertRedirectContains('/theme-preview/'.previewTheme()->id);
});

it('refuses to mint a link for a theme that is not theirs', function () {
    $acme = previewTenant();
    $beta = previewTenant('preview-beta');

    $theirs = Theme::create([
        'reseller_id' => $beta->id,
        'name' => 'Beta House Style',
        'slug' => 'beta-house-style',
        'template' => 'dignified',
        'tokens' => [],
        'is_published' => true,
    ]);

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/preview', ['theme_id' => $theirs->id])
        ->assertSessionHasErrors('theme_id');
});

it('refuses to mint a link for anyone not signed in', function () {
    $acme = previewTenant();

    $this->post('/reseller/theme/preview', ['theme_id' => previewTheme()->id])
        ->assertRedirect(route('login'));
});
