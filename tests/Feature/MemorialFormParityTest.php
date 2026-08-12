<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The two creation screens render the same partials, so they cannot drift apart again —
 * which is the whole point of extracting them. This asserts the shared sections appear
 * on both, and that reseller staff can actually reach the editor their new Edit links
 * point at.
 */
function parityStaff(): array
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create([
        'name' => 'Parity', 'slug' => 'parity-'.uniqid(), 'sort_order' => 0, 'annual_price' => 10,
        'memorial_profile_allowance' => 10, 'price_per_additional_profile' => 1,
        'storage_limit_gb' => 5, 'is_active' => true,
    ]);

    $staff = User::factory()->create();
    $staff->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Parity Funeral Home', 'slug' => 'parity-'.substr(uniqid(), -8),
        'owner_user_id' => $staff->id, 'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $staff->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    return [$staff->fresh(), $reseller->fresh(), $client];
}

/** Every section heading that only exists in the shared partials. */
dataset('shared sections', [
    'Identity',
    'Biography Summary',
    'Birth Information',
    'Passed Away',
    'Family Relationships',
    'Education',
    'Settings',
]);

it('renders every shared section on the platform create form', function (string $section) {
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create(['reseller_id' => null]);
    $user->assignRole('user');

    $this->actingAs($user)->get('http://localhost/memorials/create')->assertOk()->assertSee($section);
})->with('shared sections');

it('renders every shared section on the reseller intake form', function (string $section) {
    [$staff] = parityStaff();

    $this->actingAs($staff)->get('http://localhost/reseller/memorials/create')->assertOk()->assertSee($section);
})->with('shared sections');

it('lets reseller staff open the editor their memorial list links to', function () {
    [$staff, $reseller, $client] = parityStaff();

    $memorial = Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $reseller->id, 'slug' => 'client-memorial',
        'title' => 'In Loving Memory of Client Memorial', 'full_name' => 'Client Memorial',
        'first_name' => 'Client', 'last_name' => 'Memorial', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    // The list offers the link…
    $this->actingAs($staff)->get('http://localhost/reseller/memorials')
        ->assertOk()
        ->assertSee(route('memorials.edit', $memorial->slug), false);

    // …and the link works, landing on the same autosave editor the family would use.
    $this->actingAs($staff)->get(route('memorials.edit', $memorial->slug))
        ->assertOk()
        // Cancel returns to their client list, not /memorials, which for staff is empty.
        ->assertSee(route('reseller.memorials'), false);
});

it('keeps another reseller\'s staff out of that editor', function () {
    [, $reseller, $client] = parityStaff();
    [$outsideStaff] = parityStaff();

    $memorial = Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $reseller->id, 'slug' => 'not-yours',
        'title' => 'In Loving Memory of Not Yours', 'full_name' => 'Not Yours',
        'first_name' => 'Not', 'last_name' => 'Yours', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->actingAs($outsideStaff)->get(route('memorials.edit', $memorial->slug))->assertForbidden();
});

it('sends an owner editing their own memorial back to their own list', function () {
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create(['reseller_id' => null]);
    $user->assignRole('user');

    $memorial = Memorial::create([
        'user_id' => $user->id, 'slug' => 'my-own', 'title' => 'In Loving Memory of My Own',
        'full_name' => 'My Own', 'first_name' => 'My', 'last_name' => 'Own',
        'theme' => 'free', 'plan' => 'free', 'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->actingAs($user)->get(route('memorials.edit', $memorial->slug))
        ->assertOk()
        ->assertSee(route('memorials.index'), false);
});
