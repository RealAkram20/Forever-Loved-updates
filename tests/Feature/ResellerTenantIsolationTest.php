<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Cross-tenant isolation. Each of these pins a hole that was open in review — they are
 * regression guards, not hypotheticals.
 */
function makeTenant(string $slug): Reseller
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

function clientOf(?Reseller $reseller, string $email): User
{
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create(['email' => $email, 'reseller_id' => $reseller?->id]);
    $user->assignRole('user');

    return $user;
}

it('keeps reseller plans off the public pricing page', function () {
    $acme = makeTenant('acme');

    SubscriptionPlan::create(['name' => 'Platform Basic', 'slug' => 'platform-basic', 'price' => 10,
        'interval' => 'monthly', 'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true]);
    SubscriptionPlan::create(['name' => 'Acme Private Package', 'slug' => 'acme-private', 'price' => 99,
        'interval' => 'monthly', 'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 2, 'is_active' => true,
        'reseller_id' => $acme->id]);

    // A reseller's pricing is their own business, not the platform's shop window.
    $this->get('http://localhost/pricing')
        ->assertOk()
        ->assertSee('Platform Basic')
        ->assertDontSee('Acme Private Package');
});

it('shows a reseller client their reseller plans, not the platform ones', function () {
    $acme = makeTenant('acme');
    $client = clientOf($acme, 'family@example.test');

    // The upgrade list only renders with payments on and a memorial to upgrade.
    \App\Models\SystemSetting::set('payments.enabled', '1');
    Memorial::factory()->create(['user_id' => $client->id, 'reseller_id' => $acme->id]);

    SubscriptionPlan::create(['name' => 'Platform Basic', 'slug' => 'platform-basic', 'price' => 10,
        'interval' => 'monthly', 'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true]);
    SubscriptionPlan::create(['name' => 'Acme Package', 'slug' => 'acme-package', 'price' => 99,
        'interval' => 'monthly', 'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 2, 'is_active' => true,
        'reseller_id' => $acme->id]);

    $this->actingAs($client)->get('http://localhost/subscription')
        ->assertOk()
        ->assertSee('Acme Package')
        ->assertDontSee('Platform Basic');
});

it('refuses a signup plan belonging to another tenant', function () {
    $acme = makeTenant('acme');
    $outsider = clientOf(null, 'outsider@example.test');

    // A free plan is the dangerous case: no payment step stands between the request and
    // the entitlement, so an unscoped id check hands over another tenant's limits.
    $acmeFree = SubscriptionPlan::create(['name' => 'Acme Free', 'slug' => 'acme-free', 'price' => 0,
        'interval' => 'monthly', 'memorial_limit' => 99, 'storage_limit_mb' => 99000, 'sort_order' => 1,
        'is_active' => true, 'reseller_id' => $acme->id]);

    $this->actingAs($outsider)
        ->withSession(['memorial_signup' => ['first_name' => 'Jane']])
        ->post('http://localhost/create-memorial/step-3', ['plan_id' => $acmeFree->id])
        ->assertSessionHasErrors('plan_id');
});

it('stops a reseller attaching a memorial to an outside account by email', function () {
    $acme = makeTenant('acme');
    $beta = makeTenant('beta');

    $betaClient = clientOf($beta, 'betafamily@example.test');

    // Acme submits Beta's client's email. Before the fix this created a memorial inside
    // that person's account, under Acme's control and branding.
    $this->actingAs($acme->owner)
        ->post('http://localhost/reseller/memorials', [
            'client_name' => 'Beta Family',
            'client_email' => 'betafamily@example.test',
            'full_name' => 'Stolen Memorial',
        ])
        ->assertSessionHas('error');

    expect(Memorial::where('full_name', 'Stolen Memorial')->exists())->toBeFalse()
        ->and($betaClient->fresh()->memorials()->count())->toBe(0);
});

it('lets a reseller reuse the email of their own existing client', function () {
    $acme = makeTenant('acme');
    clientOf($acme, 'ourfamily@example.test');

    $this->actingAs($acme->owner)
        ->post('http://localhost/reseller/memorials', [
            'client_name' => 'Our Family',
            'client_email' => 'ourfamily@example.test',
            'full_name' => 'Jane Doe',
        ])
        ->assertRedirect();

    expect(Memorial::where('full_name', 'Jane Doe')->first()?->reseller_id)->toBe($acme->id);
});

it('stops reseller staff editing the owner account through the clients endpoint', function () {
    $acme = makeTenant('acme');

    $staff = User::factory()->create(['reseller_id' => $acme->id]);
    $staff->assignRole('reseller');

    // The owner is not a client. Changing their email here, then triggering a password
    // reset, would be a complete takeover of the tenant.
    $this->actingAs($staff)
        ->put('http://localhost/reseller/clients/'.$acme->owner_user_id, [
            'name' => 'Hijacked',
            'email' => 'attacker@example.test',
        ])
        ->assertForbidden();

    expect($acme->owner->fresh()->email)->not->toBe('attacker@example.test');
});

it('counts only this reseller memorials against a client', function () {
    $acme = makeTenant('acme');
    $beta = makeTenant('beta');
    $client = clientOf($acme, 'family@example.test');

    Memorial::factory()->create(['user_id' => $client->id, 'reseller_id' => $acme->id]);
    Memorial::factory()->create(['user_id' => $client->id, 'reseller_id' => $beta->id]);
    Memorial::factory()->create(['user_id' => $client->id, 'reseller_id' => null]);

    $this->actingAs($acme->owner)->get('http://localhost/reseller/clients')->assertOk();

    $listed = User::where('reseller_id', $acme->id)
        ->withCount(['memorials' => fn ($q) => $q->where('reseller_id', $acme->id)])
        ->find($client->id);

    expect($listed->memorials_count)->toBe(1);
});
