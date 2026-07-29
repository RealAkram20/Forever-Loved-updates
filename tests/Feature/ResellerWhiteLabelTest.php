<?php

use App\Helpers\SiteShareMetaHelper;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function whiteLabelTenant(string $slug, ?string $customDomain = null): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create(array_merge([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ], $customDomain ? [
        'custom_domain' => $customDomain,
        'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
        'custom_domain_verified_at' => now(),
    ] : []));

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/**
 * Host-based addresses only exist when the app is actually served from the reseller base
 * domain. The test suite inherits .env, where APP_URL is a localhost subdirectory — so
 * anything asserting a {slug}.{base} address has to say it is testing a correct deployment,
 * otherwise it is asserting an address this environment cannot serve.
 */
function deployedOnBaseDomain(): void
{
    config(['app.url' => 'https://'.config('reseller.domain')]);
}

it('gives a reseller memorial its subdomain address, not the platform one', function () {
    deployedOnBaseDomain();
    $acme = whiteLabelTenant('acme');
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->id, 'slug' => 'jane-doe']);

    expect($memorial->publicUrl())
        ->toContain('acme.'.config('reseller.domain').'/jane-doe')
        ->not->toContain('//localhost');
});

it('prefers a verified custom domain over the subdomain', function () {
    deployedOnBaseDomain();
    $acme = whiteLabelTenant('acme', 'memorials.acmefuneral.test');
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->id, 'slug' => 'jane-doe']);

    expect($memorial->publicUrl())->toContain('memorials.acmefuneral.test/jane-doe');
});

it('falls back to the subdomain while a custom domain is unverified', function () {
    deployedOnBaseDomain();
    $acme = whiteLabelTenant('acme');
    $acme->update([
        'custom_domain' => 'memorials.acmefuneral.test',
        'custom_domain_status' => Reseller::DOMAIN_UNVERIFIED,
    ]);
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->fresh()->id, 'slug' => 'jane-doe']);

    // Handing out an unverified host would send families to a domain that does not resolve.
    expect($memorial->fresh()->publicUrl())->toContain('acme.'.config('reseller.domain'));
});

it('hands out a reachable path address when the app is on a subdirectory', function () {
    // The reported bug: APP_URL is http://localhost/Forever, so acme.foreverloved.com is
    // not an address anything can serve — but it was shown as the reseller's own URL.
    config(['app.url' => 'http://localhost/Forever']);
    $acme = whiteLabelTenant('acme');
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->id, 'slug' => 'jane-doe']);

    expect($acme->usingFallbackAddress())->toBeTrue();
    expect($memorial->publicUrl())
        ->toBe('http://localhost/Forever/r/acme/jane-doe')
        ->not->toContain(config('reseller.domain'));
});

it('hands out a path address when APP_URL host is not the reseller base domain', function () {
    // No subdirectory, but the app does not answer on foreverloved.com, so every minted
    // subdomain is still dead. This is the default state: RESELLER_APP_DOMAIN is unset and
    // config/reseller.php falls back to a hardcoded domain.
    config(['app.url' => 'https://example.test']);
    $acme = whiteLabelTenant('acme');

    expect(Reseller::hostRoutingAvailable())->toBeTrue();
    expect(Reseller::subdomainRoutingAvailable())->toBeFalse();
    expect($acme->publicBaseUrl())->toBe('https://example.test/r/acme');
});

it('still names the intended production host while using a fallback address', function () {
    // The reseller must not be left thinking /r/acme is what they hand to a family.
    config(['app.url' => 'http://localhost/Forever']);
    $acme = whiteLabelTenant('acme');

    expect($acme->publicHost())->toBe('acme.'.config('reseller.domain'));
    expect($acme->publicDisplayAddress())->toBe('localhost/Forever/r/acme');
});

/**
 * Requested absolutely, like the subdomain tests in ResellerDomainRoutingTest. A relative
 * $this->get() is resolved against the URL generator's root, which .env fixes at
 * http://localhost/Forever — and unlike the real front controller, nothing in the test
 * client strips that prefix. Every assertion would then 404 for the wrong reason, including
 * the isolation one, which would pass vacuously.
 */
it('serves a reseller memorial on the path fallback route', function () {
    $acme = whiteLabelTenant('acme');
    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'jane-doe',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    // .env's APP_URL is a localhost subdirectory, so this is the address in use here.
    expect($acme->usingFallbackAddress())->toBeTrue();
    $this->get('http://localhost/r/acme/jane-doe')->assertOk();
});

it('keeps the path fallback route scoped to the right tenant', function () {
    $acme = whiteLabelTenant('acme');
    whiteLabelTenant('beta');
    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'jane-doe',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    // Same isolation the subdomain route enforces — the fallback must not be a way around
    // it. Asserted alongside the 200 so this cannot pass by 404ing on everything.
    $this->get('http://localhost/r/acme/jane-doe')->assertOk();
    $this->get('http://localhost/r/beta/jane-doe')->assertNotFound();
});

it('leaves a direct platform memorial on the platform domain', function () {
    $memorial = Memorial::factory()->create(['reseller_id' => null, 'slug' => 'jane-doe']);

    expect($memorial->publicUrl())
        ->toContain('/jane-doe')
        ->not->toContain(config('reseller.domain'));
});

it('keeps reseller memorials out of the platform public directory', function () {
    $acme = whiteLabelTenant('acme');

    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'acme-client',
        'full_name' => 'Acme Client Memorial', 'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => null, 'slug' => 'platform-one',
        'full_name' => 'Platform Own Memorial', 'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);

    // The directory serves its results as JSON off the same route.
    $body = $this->getJson('http://localhost/find-memorial')->assertOk()->getContent();

    expect($body)->toContain('Platform Own Memorial')
        ->not->toContain('Acme Client Memorial');
});

it('rolls over clients without stranding the reseller own staff', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = whiteLabelTenant('acme');

    $client = User::factory()->create(['reseller_id' => $acme->id, 'original_reseller_id' => $acme->id]);
    $client->assignRole('user');

    $this->actingAs($admin)
        ->post('http://localhost/settings/resellers/'.$acme->id.'/rollover')
        ->assertRedirect();

    // The client detaches; the owner must not, or EnsureResellerActive locks them out of
    // a business that is still active and tells them they are suspended.
    expect($client->fresh()->reseller_id)->toBeNull()
        ->and($acme->owner->fresh()->reseller_id)->toBe($acme->id);
});

it('refuses to impersonate a suspended reseller', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = whiteLabelTenant('acme');
    $acme->update(['status' => Reseller::STATUS_SUSPENDED]);

    // Landing on a 403 whose layout has no "Return to Admin" button is a dead end.
    $this->actingAs($admin)
        ->post('http://localhost/settings/resellers/'.$acme->id.'/login-as')
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(auth()->id())->toBe($admin->id);
});

/*
|--------------------------------------------------------------------------
| The reseller's own front page
|--------------------------------------------------------------------------
| The base address every reseller screen shows and offers to copy had no route at all: it
| 404'd on the path fallback, and on a real subdomain fell through to the *platform's*
| homepage — our logo, our copy, our pricing, on their white-labeled domain.
*/

it('serves the reseller front page on the path fallback', function () {
    $acme = whiteLabelTenant('acme');

    // The reseller front page IS the home page design, wearing their brand.
    $this->get('http://localhost/r/acme')
        ->assertOk()
        ->assertSee('<title>Home | '.$acme->name.'</title>', false);
});

it('serves the reseller front page on their subdomain instead of the platform homepage', function () {
    $acme = whiteLabelTenant('acme');

    $this->get('http://acme.'.config('reseller.domain').'/')
        ->assertOk()
        ->assertSee('<title>Home | '.$acme->name.'</title>', false);
});

it('serves the reseller front page on a verified custom domain', function () {
    $acme = whiteLabelTenant('acme', 'memorials.acmefuneral.test');

    $this->get('http://memorials.acmefuneral.test/')
        ->assertOk()
        ->assertSee('<title>Home | '.$acme->name.'</title>', false);
});

it('still serves the platform homepage on the app own host', function () {
    // The catch-all domain group must not have swallowed our own root.
    whiteLabelTenant('acme');

    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('<title>Home | '.config('app.name').'</title>', false);
});

it('lists only this reseller memorials on their front page', function () {
    $acme = whiteLabelTenant('acme');
    $beta = whiteLabelTenant('beta');

    $mine = Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'acme-client', 'full_name' => 'Acmeclient Person',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => $beta->id, 'slug' => 'beta-client', 'full_name' => 'Betaclient Person',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => null, 'slug' => 'direct-client', 'full_name' => 'Directclient Person',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    // Guards against a vacuous assertion: the home page filters on completion, so confirm
    // this fixture is actually eligible to be listed before asserting the others are absent.
    expect($mine->completion_percentage)->toBeGreaterThanOrEqual(40);

    $this->get('http://localhost/r/acme')
        ->assertOk()
        ->assertSee('Acmeclient Person')
        ->assertDontSee('Betaclient Person')
        ->assertDontSee('Directclient Person');
});

it('keeps reseller memorials off the platform homepage', function () {
    // The platform directory already excludes them because they "belong on the reseller's own
    // branded domain"; the homepage listed every public memorial regardless of owner.
    $acme = whiteLabelTenant('acme');
    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'acme-client', 'full_name' => 'Acmeclient Person',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->get('http://localhost/')->assertOk()->assertDontSee('Acmeclient Person');
});

it('404s the front page for an unknown reseller', function () {
    $this->get('http://localhost/r/no-such-reseller')->assertNotFound();
});

it('presents the reseller business name, not the platform one, on their pages', function () {
    // og:site_name and <title> are the surfaces a family actually forwards.
    SystemSetting::set('branding.app_name', 'Forever Loved');
    $acme = whiteLabelTenant('acme');

    $this->get('http://localhost/r/acme')
        ->assertOk()
        ->assertSee($acme->name)
        ->assertDontSee('Forever Loved');
});

it('still presents the platform name on platform pages', function () {
    SystemSetting::set('branding.app_name', 'Forever Loved');

    expect(SiteShareMetaHelper::appDisplayName())->toBe('Forever Loved');
});

it('puts the reseller name in the browser tab title, not the platform one', function () {
    // The tab title rides along in every bookmark, shared screenshot and history entry.
    $acme = whiteLabelTenant('acme');
    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'jane-doe', 'full_name' => 'Jane Doe',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->get('http://localhost/r/acme/jane-doe')
        ->assertOk()
        ->assertSee('<title>Jane Doe | '.$acme->name.'</title>', false);
});

it('leaves platform page titles unchanged', function () {
    // Fixing the tenant leak must not quietly re-source every platform title.
    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee(config('app.name'), false);
});
