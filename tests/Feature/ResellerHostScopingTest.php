<?php

use App\Models\Memorial;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Only two routes ever declared a domain, so every other route — /login, /pricing, /about,
 * /find-memorial — matched any Host and ran with no tenant at all. On acme.foreverloved.com that
 * served the platform's logo on the login screen a reseller's clients use, and the platform's
 * pricing page on the reseller's own domain. ResolveResellerByHost closes that.
 */
function hostTenant(string $slug = 'acme', ?string $customDomain = null): Reseller
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
        'primary_color' => '#0f766e',
    ], $customDomain ? [
        'custom_domain' => $customDomain,
        'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
        'custom_domain_verified_at' => now(),
    ] : []));

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function resellerHost(Reseller $reseller): string
{
    return 'http://'.$reseller->slug.'.'.config('reseller.domain');
}

/*
|--------------------------------------------------------------------------
| Branding on routes that never declared a domain
|--------------------------------------------------------------------------
*/

it('brands the login page on a reseller host', function () {
    $acme = hostTenant();

    // The screen this reseller's own clients sign in on.
    $this->get(resellerHost($acme).'/login')
        ->assertOk()
        ->assertSee('--color-brand-500: #0f766e', false);
});

it('leaves the platform login page unbranded by any tenant', function () {
    hostTenant();

    $this->get('http://localhost/login')
        ->assertOk()
        ->assertDontSee('--color-brand-500: #0f766e', false);
});

it('brands a reseller host page on their verified custom domain', function () {
    $acme = hostTenant('acme', 'memorials.acmefuneral.test');

    $this->get('http://memorials.acmefuneral.test/login')
        ->assertOk()
        ->assertSee('--color-brand-500: #0f766e', false);
});

it('ignores an unverified custom domain', function () {
    // Anyone can point a hostname at us; serving a tenant for one that has not proven DNS
    // ownership would let them impersonate a reseller.
    $acme = hostTenant();
    $acme->update([
        'custom_domain' => 'memorials.acmefuneral.test',
        'custom_domain_status' => Reseller::DOMAIN_UNVERIFIED,
    ]);

    $this->get('http://memorials.acmefuneral.test/login')
        ->assertDontSee('--color-brand-500: #0f766e', false);
});

it('ignores a host that is not a reseller at all', function () {
    hostTenant();

    $this->get('http://not-a-reseller.'.config('reseller.domain').'/login')
        ->assertOk()
        ->assertDontSee('--color-brand-500: #0f766e', false);
});

it('does not treat a multi-label subdomain as a reseller', function () {
    hostTenant();

    // a.b.foreverloved.com is not a reseller address, and "b" must not be matched out of it.
    $this->get('http://x.acme.'.config('reseller.domain').'/login')
        ->assertOk()
        ->assertDontSee('--color-brand-500: #0f766e', false);
});

/*
|--------------------------------------------------------------------------
| Platform marketing pages do not belong on a reseller's domain
|--------------------------------------------------------------------------
*/

it('redirects the platform marketing pages to the reseller front page', function () {
    $acme = hostTenant();

    foreach (['about', 'pricing', 'contact', 'find-memorial'] as $path) {
        $this->get(resellerHost($acme).'/'.$path)->assertRedirect('/');
    }
});

it('still serves the platform marketing pages on the platform host', function () {
    hostTenant();

    $this->get('http://localhost/pricing')->assertOk();
    $this->get('http://localhost/about')->assertOk();
});

it('keeps auth and legal pages working on a reseller host', function () {
    // Their clients sign in here, and a memorial site with no legal pages is worse than one
    // pointing at the platform's.
    $acme = hostTenant();

    $this->get(resellerHost($acme).'/login')->assertOk();
    $this->get(resellerHost($acme).'/privacy-policy')->assertOk();
    $this->get(resellerHost($acme).'/terms-of-use')->assertOk();
});

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

it('drops the platform marketing nav on a reseller front page', function () {
    $acme = hostTenant();

    $this->get(resellerHost($acme).'/')
        ->assertOk()
        ->assertDontSee('>About<', false)
        ->assertDontSee('>Pricing<', false)
        ->assertDontSee('>Find Memorial<', false)
        ->assertDontSee('>Contact<', false);
});

it('keeps the platform marketing nav on the platform home page', function () {
    hostTenant();

    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('>About<', false)
        ->assertSee('>Pricing<', false);
});

it('keeps the platform nav for a reseller user browsing the platform site', function () {
    // They have a tenant, and they see their branding — that is deliberate and predates this.
    // But they are on OUR site, where links to About and Pricing are simply correct.
    $acme = hostTenant();

    $this->actingAs($acme->owner)
        ->get('http://localhost/')
        ->assertOk()
        ->assertSee('>Pricing<', false);
});

it('points the reseller front page home link at their own site', function () {
    $acme = hostTenant();

    // route('home') resolves against APP_URL, so following it would leave their domain.
    $this->get(resellerHost($acme).'/')
        ->assertOk()
        ->assertSee('href="'.$acme->publicBaseUrl().'"', false);
});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

it('scopes memorial search to the reseller on their own host', function () {
    $acme = hostTenant('acme');
    $beta = hostTenant('beta');

    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'acme-one', 'full_name' => 'Searchable Acmeperson',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => $beta->id, 'slug' => 'beta-one', 'full_name' => 'Searchable Betaperson',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => null, 'slug' => 'direct-one', 'full_name' => 'Searchable Directperson',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $response = $this->getJson(resellerHost($acme).'/api/search/memorials?q=Searchable')->assertOk();

    $names = collect($response->json('results'))->pluck('name');

    expect($names)->toContain('Searchable Acmeperson')
        ->and($names)->not->toContain('Searchable Betaperson')
        ->and($names)->not->toContain('Searchable Directperson');
});

it('scopes memorial search to the platform on the platform host', function () {
    $acme = hostTenant('acme');

    Memorial::factory()->create([
        'reseller_id' => $acme->id, 'slug' => 'acme-one', 'full_name' => 'Searchable Acmeperson',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);
    Memorial::factory()->create([
        'reseller_id' => null, 'slug' => 'direct-one', 'full_name' => 'Searchable Directperson',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $names = collect($this->getJson('http://localhost/api/search/memorials?q=Searchable')->assertOk()->json('results'))->pluck('name');

    expect($names)->toContain('Searchable Directperson')
        ->and($names)->not->toContain('Searchable Acmeperson');
});

it('withholds the platform admin-defined menus on a reseller site', function () {
    // The blades' own fallbacks are tenant-aware, but an admin-defined menu bypasses them
    // entirely — and there is no way to tell a "Pricing" item apart from one the reseller
    // would legitimately want, so the platform's menus are withheld wholesale.
    $acme = hostTenant();

    $header = Menu::create(['location' => Menu::LOCATION_HEADER, 'label' => 'Header']);
    MenuItem::create([
        'menu_id' => $header->id, 'label' => 'Platform Pricing', 'route_name' => 'pricing', 'sort_order' => 0,
    ]);

    $footer = Menu::create(['location' => Menu::LOCATION_FOOTER_QUICK, 'label' => 'Quick']);
    MenuItem::create([
        'menu_id' => $footer->id, 'label' => 'Platform Directory', 'route_name' => 'memorial.directory', 'sort_order' => 0,
    ]);

    $this->get(resellerHost($acme).'/')
        ->assertOk()
        ->assertDontSee('Platform Pricing')
        ->assertDontSee('Platform Directory');

    // ...and still render on the platform's own site.
    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('Platform Pricing')
        ->assertSee('Platform Directory');
});

it('keeps create-memorial reachable on a reseller host', function () {
    // Their clients self-serve here, and the signup flow already scopes plans per reseller.
    $acme = hostTenant();

    $this->get(resellerHost($acme).'/create-memorial/step-1')->assertOk();
});

it('does not leak a tenant from a previous request onto the platform host', function () {
    // The container is rebuilt per request under PHP-FPM, but persists under a long-running
    // worker and in tests. A leaked tenant would serve one reseller's branding, memorials and
    // search results on the platform's own host.
    $acme = hostTenant();

    $this->get(resellerHost($acme).'/')->assertOk();

    $this->get('http://localhost/')
        ->assertOk()
        ->assertSee('>Pricing<', false)
        ->assertDontSee('--color-brand-500: #0f766e', false);
});
