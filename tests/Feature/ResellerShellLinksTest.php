<?php

use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The screens a reseller's client reaches that the theme system does not cover, on the one
 * host where a bare route() still leaks.
 *
 * ThemeConformanceTest asserts "never walks a visitor from a reseller site onto the platform"
 * for the themed public pages. It cannot see these ones: signing in, registering and signing
 * out render the platform's own blades with the reseller's branding painted on, so they are
 * not template-parameterised — but to the person using them they are the same white-labeled
 * site, and they had the same bug.
 *
 * WHY THE FALLBACK HOST AND NOT THE SUBDOMAIN. On a real reseller host,
 * ResolveResellerByHost re-roots the whole URL generator at the request's own host, so
 * route('home') there already answers with the reseller's address — every link on the page
 * is theirs for free. That is deliberate, and it is why this bug class kept looking fixed.
 * The /r/{slug} fallback runs on the *platform's* host and deliberately keeps the platform
 * root, so route('home') and url('/') there resolve to our front page. That is the surface
 * still exposed, and it is the one a subdirectory or development install actually serves.
 *
 * The rule the fixes follow:
 *   route() / url() for application screens that exist on every host,
 *   App\Support\SiteUrl::to() for anything on the tenant's public site — it reads the tenant
 *   from the request either way, so it is correct on both hosts at once.
 *
 * Asserted as "no href equal to the platform root" rather than "href equals the tenant root",
 * on purpose: the next instance will be a link nobody thought to enumerate, and the negative
 * catches it wherever on the page it appears.
 */
function shellTenant(string $slug = 'shell-acme'): Reseller
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

/**
 * The screens outside the theme system that a reseller's client can reach on the path
 * fallback. Kept as a dataset so adding a screen to the list is the whole cost of covering
 * it — and note how short the list is: everything else a client touches (paying, starting a
 * memorial, resetting a password, unsubscribing) has no /r/{slug} route at all, so no tenant
 * is bound and those pages are the platform's on this host. That is a known limit of the
 * fallback, not something SiteUrl can fix; on a real reseller host they are all tenant-bound.
 */
dataset('fallback shell paths', [
    'sign in' => 'login',
    'register' => 'register',
]);

it('never links a reseller client from the shell screens onto the platform', function (string $path) {
    $acme = shellTenant();
    $platformRoot = rtrim(config('app.url'), '/');

    $html = $this->get('/r/'.$acme->slug.'/'.$path)->assertOk()->getContent();

    // The logo and the "Back to home" link. Both were route('home'), which on this host is
    // the platform's front page — so a grieving client clicking the funeral home's own logo
    // was handed to us.
    expect($html)->not->toContain('href="'.$platformRoot.'"');
})->with('fallback shell paths');

it('points those screens at the reseller home instead', function (string $path) {
    $acme = shellTenant();

    // The positive half. Without it the test above passes for a page that links nowhere.
    $html = $this->get('/r/'.$acme->slug.'/'.$path)->assertOk()->getContent();

    expect($html)->toContain('href="'.$acme->publicBaseUrl().'"');
})->with('fallback shell paths');

it('returns a signed-out client to the reseller site they signed out of', function () {
    $acme = shellTenant();

    $client = User::factory()->create(['reseller_id' => $acme->id]);
    $client->assignRole('user');

    // redirect('/') routed through the URL generator, so signing out of a white-labeled site
    // landed the client on ours. Resolved before the session is destroyed, because
    // ThemeSetting::tenant() falls back to the signed-in user's reseller — after logout only
    // the host binding is left to answer with.
    $this->actingAs($client)
        ->post('http://'.$acme->slug.'.'.config('reseller.domain').'/logout')
        ->assertRedirect($acme->publicBaseUrl());
});

it('still returns a signed-out platform user to the platform', function () {
    // The other half of the fix. A reseller's own staff member browsing *our* site is a
    // tenant by ThemeSetting::tenant() but not by siteTenant(), and signing out there must
    // still land on our front page — otherwise fixing the leak would have created its mirror.
    $acme = shellTenant();

    $staff = User::factory()->create(['reseller_id' => $acme->id]);
    $staff->assignRole('user');

    $this->actingAs($staff)
        ->post('/logout')
        ->assertRedirect(url('/'));
});

it('hands SiteUrl the tenant root where url() still hands back ours', function () {
    $acme = shellTenant();
    $platformRoot = rtrim(config('app.url'), '/');

    // The rule itself, asserted once rather than re-derived per page. Every fix above is an
    // instance of it, and so is PaymentController's "Back to Home" on a cancelled payment —
    // which cannot be asserted end-to-end, because there is no /r/{slug} payment route to
    // serve it on the one host where the difference shows. This is that link's real coverage.
    //
    // Ridden on /r/{slug}/login purely because it is a route that binds a tenant on the
    // platform's host; the assertion is about the two helpers, not about the login page.
    $this->get('/r/'.$acme->slug.'/login')->assertOk();

    expect(\App\Support\SiteUrl::to('/'))->toBe($acme->publicBaseUrl())
        ->and(url('/'))->toBe($platformRoot)
        ->and(\App\Support\SiteUrl::to('/'))->not->toBe(url('/'));
});

it('leaves the platform its own shell screens unchanged', function (string $path) {
    // The whole fix is a no-op off a reseller site: SiteUrl::to() falls through to url() when
    // siteTenant() is null. If this drifts, every link above quietly became tenant-shaped on
    // the platform too.
    $platformRoot = rtrim(config('app.url'), '/');

    $html = $this->get('/'.$path)->assertOk()->getContent();

    expect($html)->toContain('href="'.$platformRoot.'"');
})->with('fallback shell paths');
