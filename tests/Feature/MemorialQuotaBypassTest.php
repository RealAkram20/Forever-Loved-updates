<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The tier's memorial allowance is what a reseller is billed on, so it has to hold at
 * every door. It used to be checked in exactly one controller — the reseller intake
 * screen — while /memorials/create sat behind plain `auth` with no check at all. Staff
 * or a client could walk past their own quota there, and Memorial's creating hook still
 * stamped reseller_id, so the overage was billed but never blocked.
 */
function cappedTenant(int $allowance = 1): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create([
        'name' => 'Capped', 'slug' => 'capped-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 199, 'memorial_profile_allowance' => $allowance,
        'price_per_additional_profile' => 5, 'storage_limit_gb' => 10, 'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Capped Funeral Home', 'slug' => 'capped-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/** A payload the platform's own /memorials/create accepts. */
function platformPayload(array $overrides = []): array
{
    return array_merge(['first_name' => 'Over', 'last_name' => 'Quota', 'theme' => 'free'], $overrides);
}

it('blocks a reseller client from creating past the allowance on the platform form', function () {
    $reseller = cappedTenant(1);

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    // Fill the allowance.
    Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $reseller->id, 'slug' => 'already-here',
        'title' => 'In Loving Memory of Already Here', 'full_name' => 'Already Here',
        'first_name' => 'Already', 'last_name' => 'Here', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->actingAs($client)
        ->post('http://localhost/memorials', platformPayload())
        ->assertSessionHas('error');

    expect(Memorial::where('full_name', 'Over Quota')->exists())->toBeFalse()
        ->and(Memorial::where('reseller_id', $reseller->id)->count())->toBe(1);

    // And the page they land on actually says so. Flashing a message the view never
    // renders is the same as saying nothing: the form reappears and looks broken.
    // The GET first is what a browser does, and what gives back() somewhere to return to.
    $this->actingAs($client)->get('http://localhost/memorials/create')->assertOk();

    $this->actingAs($client)
        ->followingRedirects()
        ->post('http://localhost/memorials', platformPayload())
        ->assertOk()
        ->assertSee('memorial profiles included in the', false);
});

it('blocks reseller staff using the platform form once the allowance is gone', function () {
    $reseller = cappedTenant(1);
    $staff = $reseller->owner;

    Memorial::create([
        'user_id' => $staff->id, 'reseller_id' => $reseller->id, 'slug' => 'first-one',
        'title' => 'In Loving Memory of First One', 'full_name' => 'First One',
        'first_name' => 'First', 'last_name' => 'One', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->actingAs($staff)
        ->post('http://localhost/memorials', platformPayload())
        ->assertSessionHas('error');

    expect(Memorial::where('reseller_id', $reseller->id)->count())->toBe(1);
});

it('lets a reseller client create while the allowance still has room', function () {
    $reseller = cappedTenant(5);

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    $this->actingAs($client)->post('http://localhost/memorials', platformPayload())->assertRedirect();

    expect(Memorial::where('full_name', 'Over Quota')->first()?->reseller_id)->toBe($reseller->id);
});

it('leaves platform users unmetered', function () {
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create(['reseller_id' => null]);
    $user->assignRole('user');

    $this->actingAs($user)->post('http://localhost/memorials', platformPayload())->assertRedirect();

    expect(Memorial::where('full_name', 'Over Quota')->first()?->reseller_id)->toBeNull();
});

it('turns a self-serve signup away when the reseller allowance is used up', function () {
    $reseller = cappedTenant(1);

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $reseller->id, 'slug' => 'the-only-one',
        'title' => 'In Loving Memory of The Only', 'full_name' => 'The Only',
        'first_name' => 'The', 'last_name' => 'Only', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $plan = SubscriptionPlan::create([
        'name' => 'Their Free', 'slug' => 'their-free-'.uniqid(), 'price' => 0, 'interval' => 'monthly',
        'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1,
        'is_active' => true, 'reseller_id' => $reseller->id,
    ]);

    $session = ['memorial_signup' => [
        'first_name' => 'Wizard', 'last_name' => 'Attempt', 'plan_id' => $plan->id,
    ]];

    $this->actingAs($client)
        ->withSession($session)
        ->get('http://localhost/create-memorial/complete')
        ->assertRedirect(route('memorial.create.step3'))
        ->assertSessionHas('error');

    expect(Memorial::where('full_name', 'Wizard Attempt')->exists())->toBeFalse();

    // Step 3 must show it. Its Alpine `error` only ever holds checkout-fetch messages,
    // so a family would otherwise loop on the same page pressing the same button.
    $this->actingAs($client)
        ->withSession($session)
        ->followingRedirects()
        ->get('http://localhost/create-memorial/complete')
        ->assertOk()
        ->assertSee('has reached its memorial allowance', false);
});
