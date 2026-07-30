<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use App\Support\PostAuthRedirect;
use App\Support\ResellerAuthUrls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // New-signup notifications fan out to admins, so the role must exist for registration.
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function makeReseller(array $attributes = []): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create([
        'name' => 'Starter',
        'slug' => 'tier-'.substr(uniqid(), -8),
        'sort_order' => 0,
        'annual_price' => 199,
        'memorial_profile_allowance' => 50,
        'price_per_additional_profile' => 5,
        'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create(array_merge([
        'name' => 'Acme Funeral Home',
        'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ], $attributes));

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

// ── Auth reachable on the reseller's own space (path fallback) ────────────────────────────

it('serves a login page on the reseller path fallback with a tenant-scoped form action', function () {
    $reseller = makeReseller();

    $this->get('http://localhost/r/'.$reseller->slug.'/login')
        ->assertOk()
        // The form posts back into the reseller's space, not the platform login.
        ->assertSee('/r/'.$reseller->slug.'/login', false);
});

it('logs a user in from the reseller path login', function () {
    $reseller = makeReseller();
    $user = User::factory()->create([
        'reseller_id' => $reseller->id,
        'password' => Hash::make('secret-pass-123'),
    ]);
    $user->assignRole('user');

    $this->post('http://localhost/r/'.$reseller->slug.'/login', [
        'email' => $user->email,
        'password' => 'secret-pass-123',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('ties a registration on the reseller site to that reseller', function () {
    $reseller = makeReseller();

    $this->post('http://localhost/r/'.$reseller->slug.'/register', [
        'name' => 'Jane Family',
        'email' => 'jane.family@example.test',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect();

    $user = User::where('email', 'jane.family@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->reseller_id)->toBe($reseller->id)
        ->and($user->original_reseller_id)->toBe($reseller->id)
        ->and($user->hasRole('user'))->toBeTrue();
});

it('does not tie a platform registration to any reseller', function () {
    $this->post('http://localhost/register', [
        'name' => 'Platform Person',
        'email' => 'platform.person@example.test',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertRedirect();

    $user = User::where('email', 'platform.person@example.test')->first();
    expect($user->reseller_id)->toBeNull();
});

// ── "Designated place" after auth ────────────────────────────────────────────────────────

it('sends a reseller user to their own host dashboard when host routing is available', function () {
    config(['app.url' => 'http://foreverloved.com', 'reseller.domain' => 'foreverloved.com']);

    $reseller = makeReseller();
    $user = User::factory()->create(['reseller_id' => $reseller->id]);

    expect(PostAuthRedirect::url($user))
        ->toBe('http://'.$reseller->slug.'.foreverloved.com/dashboard');
});

it('sends a reseller user on a verified custom domain to that domain after auth', function () {
    config(['app.url' => 'http://foreverloved.com', 'reseller.domain' => 'foreverloved.com']);

    $reseller = makeReseller([
        'custom_domain' => 'memorials.acme.test',
        'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
        'custom_domain_verified_at' => now(),
    ]);
    $user = User::factory()->create(['reseller_id' => $reseller->id]);

    expect(PostAuthRedirect::url($user))->toBe('http://memorials.acme.test/dashboard');
});

it('sends a platform user to the shared dashboard', function () {
    $user = User::factory()->create(['reseller_id' => null]);

    expect(PostAuthRedirect::url($user))->toBe(route('dashboard', absolute: false));
});

it('falls back to the shared dashboard when host routing is unavailable', function () {
    // Subdirectory install: no host routing, so there is no tenant-scoped dashboard URL.
    config(['app.url' => 'http://localhost/Forever', 'reseller.domain' => 'foreverloved.com']);

    $reseller = makeReseller();
    $user = User::factory()->create(['reseller_id' => $reseller->id]);

    expect(PostAuthRedirect::url($user))->toBe(route('dashboard', absolute: false));
});

it('leaves platform auth links unchanged when there is no tenant', function () {
    expect(ResellerAuthUrls::login())->toBe(route('login'))
        ->and(ResellerAuthUrls::register())->toBe(route('register'));
});
