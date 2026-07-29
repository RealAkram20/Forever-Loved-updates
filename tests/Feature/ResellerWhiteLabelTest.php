<?php

use App\Models\Memorial;
use App\Models\Reseller;
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

it('gives a reseller memorial its subdomain address, not the platform one', function () {
    $acme = whiteLabelTenant('acme');
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->id, 'slug' => 'jane-doe']);

    expect($memorial->publicUrl())
        ->toContain('acme.'.config('reseller.domain').'/jane-doe')
        ->not->toContain('//localhost');
});

it('prefers a verified custom domain over the subdomain', function () {
    $acme = whiteLabelTenant('acme', 'memorials.acmefuneral.test');
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->id, 'slug' => 'jane-doe']);

    expect($memorial->publicUrl())->toContain('memorials.acmefuneral.test/jane-doe');
});

it('falls back to the subdomain while a custom domain is unverified', function () {
    $acme = whiteLabelTenant('acme');
    $acme->update([
        'custom_domain' => 'memorials.acmefuneral.test',
        'custom_domain_status' => Reseller::DOMAIN_UNVERIFIED,
    ]);
    $memorial = Memorial::factory()->create(['reseller_id' => $acme->fresh()->id, 'slug' => 'jane-doe']);

    // Handing out an unverified host would send families to a domain that does not resolve.
    expect($memorial->fresh()->publicUrl())->toContain('acme.'.config('reseller.domain'));
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
