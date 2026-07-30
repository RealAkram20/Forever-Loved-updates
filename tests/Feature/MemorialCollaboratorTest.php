<?php

use App\Models\Memorial;
use App\Models\MemorialCollaborator;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function inviteTo(Memorial $memorial, array $payload): \Illuminate\Testing\TestResponse
{
    return test()->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', $payload);
}

it('lets an admin invite a collaborator who can then edit the memorial', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    $res = test()->actingAs($admin)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'helper@example.test',
        'role' => 'editor',
    ])->assertStatus(201)->assertJson(['success' => true]);

    $invitee = User::where('email', 'helper@example.test')->first();
    expect($invitee)->not->toBeNull()
        ->and($invitee->hasRole('user'))->toBeTrue();

    $collab = MemorialCollaborator::where('memorial_id', $memorial->id)->where('user_id', $invitee->id)->first();
    expect($collab)->not->toBeNull()
        ->and($collab->role)->toBe('editor')
        ->and($collab->accepted_at)->not->toBeNull()
        // canBeEditedBy already honours accepted editors, so the invitee can now manage it.
        ->and($memorial->fresh()->canBeEditedBy($invitee))->toBeTrue();
});

it('scopes a new invitee to the memorial reseller', function () {
    Role::findOrCreate('reseller', 'web');
    $tier = ResellerTier::create(['name' => 'T', 'slug' => 't-'.uniqid(), 'annual_price' => 1, 'price_per_additional_profile' => 1, 'is_active' => true]);
    $reseller = Reseller::create(['name' => 'Acme', 'slug' => 'acme-'.substr(uniqid(), -6), 'owner_user_id' => User::factory()->create()->id, 'reseller_tier_id' => $tier->id, 'status' => Reseller::STATUS_ACTIVE]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id, 'reseller_id' => $reseller->id]);

    test()->actingAs($admin)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'family@example.test', 'role' => 'editor',
    ])->assertStatus(201);

    expect(User::where('email', 'family@example.test')->first()->reseller_id)->toBe($reseller->id);
});

it('lets reseller staff invite for their own tenant memorial, bypassing the plan gate', function () {
    $tier = ResellerTier::create(['name' => 'T', 'slug' => 't-'.uniqid(), 'annual_price' => 1, 'price_per_additional_profile' => 1, 'is_active' => true]);
    $owner = User::factory()->create();
    $owner->assignRole('reseller');
    $reseller = Reseller::create(['name' => 'Acme', 'slug' => 'acme-'.substr(uniqid(), -6), 'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id, 'status' => Reseller::STATUS_ACTIVE]);
    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id, 'reseller_id' => $reseller->id]);

    test()->actingAs($owner->fresh())->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'family@example.test', 'role' => 'editor',
    ])->assertStatus(201);
});

it('blocks an owner whose plan does not include advanced privacy', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    // No plan → canUseAdvancedPrivacy is false.
    $memorial = Memorial::factory()->create(['user_id' => $owner->id, 'subscription_plan_id' => null]);

    test()->actingAs($owner)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'helper@example.test', 'role' => 'editor',
    ])->assertStatus(403);
});

it('lets an owner invite when their plan includes advanced privacy', function () {
    $plan = SubscriptionPlan::create([
        'name' => 'Premium', 'slug' => 'premium-'.uniqid(), 'price' => 0, 'is_active' => true,
        'feature_advanced_privacy' => true,
    ]);
    $owner = User::factory()->create();
    $owner->assignRole('user');
    $memorial = Memorial::factory()->create(['user_id' => $owner->id, 'subscription_plan_id' => $plan->id]);

    test()->actingAs($owner)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'helper@example.test', 'role' => 'editor',
    ])->assertStatus(201);
});

it('refuses a stranger with no rights on the memorial', function () {
    $stranger = User::factory()->create();
    $stranger->assignRole('user');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($stranger)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'helper@example.test', 'role' => 'editor',
    ])->assertStatus(403);
});

it('links an existing user instead of creating a duplicate account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $existing = User::factory()->create(['email' => 'known@example.test']);
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    test()->actingAs($admin)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'known@example.test', 'role' => 'viewer',
    ])->assertStatus(201);

    expect(User::where('email', 'known@example.test')->count())->toBe(1)
        ->and(MemorialCollaborator::where('memorial_id', $memorial->id)->where('user_id', $existing->id)->value('role'))->toBe('viewer');
});

it('will not invite the memorial owner as a collaborator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    test()->actingAs($admin)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'owner@example.test', 'role' => 'editor',
    ])->assertStatus(422);
});

it('updates a role and removes a collaborator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);

    $collabId = test()->actingAs($admin)->postJson('http://localhost/m/'.$memorial->slug.'/collaborators', [
        'email' => 'helper@example.test', 'role' => 'editor',
    ])->json('collaborator.id');

    test()->actingAs($admin)->patchJson('http://localhost/m/'.$memorial->slug.'/collaborators/'.$collabId, [
        'role' => 'viewer',
    ])->assertOk();
    expect(MemorialCollaborator::find($collabId)->role)->toBe('viewer');

    test()->actingAs($admin)->deleteJson('http://localhost/m/'.$memorial->slug.'/collaborators/'.$collabId)->assertOk();
    expect(MemorialCollaborator::find($collabId))->toBeNull();
});

it('requires authentication', function () {
    $memorial = Memorial::factory()->create(['user_id' => User::factory()->create()->id]);
    inviteTo($memorial, ['email' => 'x@example.test', 'role' => 'editor'])->assertUnauthorized();
});
