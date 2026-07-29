<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Host-header routing is the load-bearing piece of the white-label promise: a reseller's
 * families visit their branded address, not ours. These tests pin the routing so a change
 * to the catch-all domain group cannot silently start serving the wrong tenant — or stop
 * serving the app's own pages.
 */
function tenant(string $slug, ?string $customDomain = null, string $domainStatus = Reseller::DOMAIN_VERIFIED): Reseller
{
    Role::findOrCreate('reseller', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    // custom_domain_status is NOT NULL with a default, so the keys are omitted entirely
    // rather than passed as null when there is no custom domain.
    $reseller = Reseller::create(array_merge([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ], $customDomain ? [
        'custom_domain' => $customDomain,
        'custom_domain_status' => $domainStatus,
        'custom_domain_verified_at' => $domainStatus === Reseller::DOMAIN_VERIFIED ? now() : null,
    ] : []));

    $owner->update(['reseller_id' => $reseller->id]);

    return $reseller;
}

function publicMemorial(?Reseller $reseller, string $slug): Memorial
{
    return Memorial::factory()->create([
        'reseller_id' => $reseller?->id,
        'slug' => $slug,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);
}

it('serves a reseller memorial on their own subdomain', function () {
    $acme = tenant('acme');
    publicMemorial($acme, 'jane-doe');

    $this->get('http://acme.'.config('reseller.domain').'/jane-doe')->assertOk();
});

it('does not serve one reseller memorial on another reseller subdomain', function () {
    $acme = tenant('acme');
    tenant('beta');
    publicMemorial($acme, 'jane-doe');

    // The whole white-label promise fails if Beta's address can serve Acme's families.
    $this->get('http://beta.'.config('reseller.domain').'/jane-doe')->assertNotFound();
});

it('does not serve a direct platform memorial on a reseller subdomain', function () {
    tenant('acme');
    publicMemorial(null, 'jane-doe');

    $this->get('http://acme.'.config('reseller.domain').'/jane-doe')->assertNotFound();
});

it('serves a reseller memorial on their verified custom domain', function () {
    $acme = tenant('acme', 'memorials.acmefuneral.test');
    publicMemorial($acme, 'jane-doe');

    $this->get('http://memorials.acmefuneral.test/jane-doe')->assertOk();
});

it('refuses an unverified custom domain', function () {
    $acme = tenant('acme', 'memorials.acmefuneral.test', Reseller::DOMAIN_UNVERIFIED);
    publicMemorial($acme, 'jane-doe');

    // Serving an unverified domain would let anyone claim a hostname they do not own.
    $this->get('http://memorials.acmefuneral.test/jane-doe')->assertNotFound();
});

it('keeps serving a suspended reseller public pages', function () {
    $acme = tenant('acme');
    $acme->update(['status' => Reseller::STATUS_SUSPENDED]);
    publicMemorial($acme, 'jane-doe');

    // Suspension blocks their dashboard, not the families visiting a published memorial.
    $this->get('http://acme.'.config('reseller.domain').'/jane-doe')->assertOk();
});

it('still routes the app own host normally with the catch-all domain group registered', function () {
    tenant('acme');

    // The Route::domain('{domain}') catch-all must not shadow first-party routes.
    $this->get('http://localhost/login')->assertOk();
});
