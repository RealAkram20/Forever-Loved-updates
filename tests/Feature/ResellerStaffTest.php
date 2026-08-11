<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function staffReseller(): array
{
    $tier = ResellerTier::create(['name' => 'T', 'slug' => 't-'.uniqid(), 'annual_price' => 1, 'price_per_additional_profile' => 1, 'is_active' => true]);
    $owner = User::factory()->create();
    $owner->assignRole('reseller');
    $reseller = Reseller::create([
        'name' => 'Acme', 'slug' => 'acme-'.substr(uniqid(), -6),
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id, 'status' => Reseller::STATUS_ACTIVE,
    ]);
    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return [$owner->fresh(), $reseller];
}

it('lets the reseller owner invite a staff member as reseller', function () {
    [$owner, $reseller] = staffReseller();

    $this->actingAs($owner)->post('http://localhost/reseller/staff', [
        'name' => 'New Staffer',
        'email' => 'staffer@example.test',
    ])->assertRedirect();

    $staff = User::where('email', 'staffer@example.test')->first();
    expect($staff)->not->toBeNull()
        ->and($staff->reseller_id)->toBe($reseller->id)
        ->and($staff->hasRole('reseller'))->toBeTrue();
});

it('forbids a non-owner staff member from managing staff', function () {
    [$owner, $reseller] = staffReseller();

    $staff = User::factory()->create(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);
    $staff->assignRole('reseller');

    $this->actingAs($staff)->get('http://localhost/reseller/staff')->assertForbidden();
    $this->actingAs($staff)->post('http://localhost/reseller/staff', [
        'name' => 'X', 'email' => 'x@example.test',
    ])->assertForbidden();
});

it('lets the owner remove a staff member but not themselves', function () {
    [$owner, $reseller] = staffReseller();

    $staff = User::factory()->create(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);
    $staff->assignRole('reseller');

    $this->actingAs($owner)->delete('http://localhost/reseller/staff/'.$staff->id)->assertRedirect();
    $staff->refresh();
    expect($staff->reseller_id)->toBeNull()
        ->and($staff->hasRole('reseller'))->toBeFalse();

    // The owner cannot remove themselves.
    $this->actingAs($owner)->delete('http://localhost/reseller/staff/'.$owner->id)->assertForbidden();
    expect($owner->fresh()->reseller_id)->toBe($reseller->id);
});

it('shows the Staff nav item only to the owner', function () {
    [$owner, $reseller] = staffReseller();
    $staff = User::factory()->create(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);
    $staff->assignRole('reseller');

    $this->actingAs($owner)->get('http://localhost/dashboard')->assertOk()->assertSee('/reseller/staff');
    $this->actingAs($staff)->get('http://localhost/dashboard')->assertOk()->assertDontSee('/reseller/staff');
});
