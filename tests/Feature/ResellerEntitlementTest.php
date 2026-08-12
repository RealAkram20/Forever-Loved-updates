<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** Build a reseller on a tier with the given entitlements, and return its owner. */
function resellerOn(array $tierAttributes = []): User
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create(array_merge([
        'name' => 'Starter',
        'slug' => 'starter-'.uniqid(),
        'sort_order' => 0,
        'annual_price' => 199,
        'memorial_profile_allowance' => 2,
        'price_per_additional_profile' => 5,
        'storage_limit_gb' => 10,
        'feature_embedding' => false,
        'feature_domain_routing' => false,
        'feature_business_analytics' => false,
        'is_active' => true,
    ], $tierAttributes));

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme Funeral Home',
        // Unique per call — a test may need two resellers, and slug is unique-indexed.
        'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $owner->fresh();
}

it('reports allowance usage from the tier', function () {
    $owner = resellerOn(['memorial_profile_allowance' => 3]);
    $reseller = $owner->reseller;

    expect($reseller->memorialsRemaining())->toBe(3)
        ->and($reseller->hasMemorialCapacity())->toBeTrue();

    Memorial::factory()->count(3)->create(['reseller_id' => $reseller->id]);

    expect($reseller->memorialsRemaining())->toBe(0)
        ->and($reseller->hasMemorialCapacity())->toBeFalse();
});

it('treats an unlimited allowance and an unassigned tier as uncapped', function () {
    $unlimited = resellerOn(['memorial_profile_allowance' => null])->reseller;
    expect($unlimited->memorialsRemaining())->toBeNull()
        ->and($unlimited->hasMemorialCapacity())->toBeTrue();

    // No tier at all: quotas stay unmetered so a freshly created reseller is usable
    // before an admin assigns one, but features remain denied.
    $tierless = resellerOn();
    $tierless->reseller->update(['reseller_tier_id' => null]);
    $tierless = $tierless->fresh()->reseller;

    expect($tierless->hasMemorialCapacity())->toBeTrue()
        ->and($tierless->tierAllows('domain_routing'))->toBeFalse()
        ->and($tierless->tierAllows('embedding'))->toBeFalse();
});

it('blocks creating a memorial past the tier allowance', function () {
    $owner = resellerOn(['memorial_profile_allowance' => 1]);

    // First one fits.
    $this->actingAs($owner)->post('http://localhost/reseller/memorials', [
        'client_name' => 'First Client',
        'client_email' => 'first@example.test',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'theme' => 'free',
    ])->assertRedirect();

    expect(Memorial::where('reseller_id', $owner->reseller_id)->count())->toBe(1);

    // Second exceeds it, and must not be created even though the request is valid.
    $this->actingAs($owner)->post('http://localhost/reseller/memorials', [
        'client_name' => 'Second Client',
        'client_email' => 'second@example.test',
        'first_name' => 'John',
        'last_name' => 'Roe',
        'theme' => 'free',
    ])->assertSessionHas('error');

    expect(Memorial::where('reseller_id', $owner->reseller_id)->count())->toBe(1)
        ->and(Memorial::where('full_name', 'John Roe')->exists())->toBeFalse();
});

it('refuses a custom domain when the tier does not include routing', function () {
    SystemSetting::set('domains.custom_domains_enabled', '1');
    $owner = resellerOn(['feature_domain_routing' => false]);

    $this->actingAs($owner)
        ->put('http://localhost/reseller/settings/domain', ['custom_domain' => 'memorials.acme.test'])
        ->assertForbidden();

    expect($owner->reseller->fresh()->custom_domain)->toBeNull();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/settings/domain/verify')
        ->assertForbidden();
});

it('allows a custom domain when the tier includes routing', function () {
    SystemSetting::set('domains.custom_domains_enabled', '1');
    $owner = resellerOn(['feature_domain_routing' => true]);

    $this->actingAs($owner)
        ->put('http://localhost/reseller/settings/domain', ['custom_domain' => 'memorials.acme.test'])
        ->assertRedirect();

    expect($owner->reseller->fresh()->custom_domain)->toBe('memorials.acme.test');
});

it('still refuses domain setup while the platform switch is off, tier regardless', function () {
    SystemSetting::set('domains.custom_domains_enabled', '0');
    $owner = resellerOn(['feature_domain_routing' => true]);

    $this->actingAs($owner)
        ->put('http://localhost/reseller/settings/domain', ['custom_domain' => 'memorials.acme.test'])
        ->assertForbidden();
});

it('keeps an already-verified domain serving after a downgrade', function () {
    $owner = resellerOn(['feature_domain_routing' => true]);
    $reseller = $owner->reseller;

    $reseller->update([
        'custom_domain' => 'memorials.acme.test',
        'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
        'custom_domain_verified_at' => now(),
    ]);

    // Downgrade to a tier without routing.
    $reseller->tier->update(['feature_domain_routing' => false]);

    // Public memorial pages on that host must keep resolving — pulling a live domain
    // would dark a published memorial for the family visiting it.
    $memorial = Memorial::factory()->create([
        'reseller_id' => $reseller->id,
        'slug' => 'jane-doe',
        'is_public' => true,
    ]);

    $this->get('http://memorials.acme.test/'.$memorial->slug)->assertOk();
});
