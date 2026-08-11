<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DomainVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The two ways the domain lifecycle used to shoot itself:
 *
 *  - Re-saving the SAME domain rotated the token, which invalidated the TXT record
 *    already sitting in the reseller's DNS and reset a verified domain to
 *    unverified — un-routing a live site from a button that changed nothing.
 *  - A failed TXT re-check (stale DNS caches lie for their whole TTL) demoted a
 *    verified domain to failed, with the same un-routing consequence.
 */
function lifecycleReseller(): array
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('super-admin', 'web');

    SystemSetting::set('domains.custom_domains_enabled', '1');

    $tier = ResellerTier::create(['name' => 'Pro', 'slug' => 'pro', 'feature_domain_routing' => true, 'sort_order' => 1]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => Reseller::STATUS_ACTIVE,
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'custom_domain' => 'kangaruride.com',
        'custom_domain_token' => 'original-token-in-their-dns',
        'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
        'custom_domain_verified_at' => now(),
    ]);
    $owner->update(['reseller_id' => $reseller->id]);

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    return [$reseller, $owner, $admin];
}

it('treats re-saving the same domain as a no-op on the reseller form', function () {
    [$reseller, $owner] = lifecycleReseller();

    $this->actingAs($owner)
        ->put('http://localhost/reseller/settings/domain', ['custom_domain' => 'kangaruride.com'])
        ->assertRedirect();

    $reseller->refresh();
    expect($reseller->custom_domain_token)->toBe('original-token-in-their-dns')
        ->and($reseller->custom_domain_status)->toBe(Reseller::DOMAIN_VERIFIED);
});

it('treats re-saving the same domain as a no-op on the admin form', function () {
    [$reseller, , $admin] = lifecycleReseller();

    $this->actingAs($admin)
        ->put('http://localhost/settings/resellers/'.$reseller->id.'/custom-domain', ['custom_domain' => 'kangaruride.com'])
        ->assertRedirect();

    $reseller->refresh();
    expect($reseller->custom_domain_token)->toBe('original-token-in-their-dns')
        ->and($reseller->custom_domain_status)->toBe(Reseller::DOMAIN_VERIFIED);
});

it('does not demote a verified domain when a re-check finds nothing', function () {
    [$reseller, $owner, $admin] = lifecycleReseller();

    $this->mock(DomainVerificationService::class, function ($mock) {
        $mock->shouldReceive('verifyTxt')->andReturn(false);
        $mock->shouldReceive('txtHost')->andReturn('_x.kangaruride.com');
        $mock->shouldReceive('generateToken')->andReturn('unused');
    });

    $this->actingAs($owner)->post('http://localhost/reseller/settings/domain/verify')->assertRedirect();
    expect($reseller->fresh()->custom_domain_status)->toBe(Reseller::DOMAIN_VERIFIED);

    $this->actingAs($admin)->post('http://localhost/settings/resellers/'.$reseller->id.'/custom-domain/check')->assertRedirect();
    expect($reseller->fresh()->custom_domain_status)->toBe(Reseller::DOMAIN_VERIFIED);
});

it('still rotates the token when the domain genuinely changes', function () {
    [$reseller, $owner] = lifecycleReseller();

    $this->actingAs($owner)
        ->put('http://localhost/reseller/settings/domain', ['custom_domain' => 'memorials.newbrand.com'])
        ->assertRedirect();

    $reseller->refresh();
    expect($reseller->custom_domain)->toBe('memorials.newbrand.com')
        ->and($reseller->custom_domain_token)->not->toBe('original-token-in-their-dns')
        ->and($reseller->custom_domain_status)->toBe(Reseller::DOMAIN_UNVERIFIED);
});
