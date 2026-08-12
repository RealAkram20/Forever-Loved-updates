<?php

use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Models\Notification;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Reseller intake now runs through the same MemorialCreationService as the platform's own
 * form. These pin the behaviour that used to differ: the full field set is recorded, the
 * client is resolved without silently ignoring what staff typed, and the chosen plan is a
 * real subscription rather than an implied free fallback.
 */
function intakeReseller(array $tierAttributes = []): User
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create(array_merge([
        'name' => 'Intake', 'slug' => 'intake-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 199, 'memorial_profile_allowance' => 5,
        'price_per_additional_profile' => 5, 'storage_limit_gb' => 10, 'is_active' => true,
    ], $tierAttributes));

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Intake Funeral Home',
        'slug' => 'intake-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $owner->fresh();
}

function intakePlan(?int $resellerId, float $price = 0, string $name = 'Free'): SubscriptionPlan
{
    return SubscriptionPlan::create([
        'name' => $name, 'slug' => strtolower($name).'-'.uniqid(), 'price' => $price,
        'interval' => 'monthly', 'memorial_limit' => 1, 'storage_limit_mb' => 100,
        'sort_order' => 1, 'is_active' => true, 'reseller_id' => $resellerId,
    ]);
}

/** A complete intake payload, minus whatever the test overrides. */
function intakePayload(array $overrides = []): array
{
    return array_merge([
        'client_name' => 'Mary Doe',
        'client_email' => 'mary@example.test',
        'first_name' => 'Jane',
        'middle_name' => 'Amara',
        'last_name' => 'Doe',
        'theme' => 'free',
        'short_description' => 'Loving mother and teacher',
        'primary_profession' => 'Teacher',
        'date_of_birth' => '1950-04-02',
        'date_of_passing' => '2024-01-15',
        'birth_country' => 'Uganda',
        'children' => [['child_name' => 'Peter', 'birth_year' => 1975]],
        'education' => [['institution_name' => 'Makerere', 'start_year' => 1968, 'end_year' => 1972, 'degree' => 'BA']],
    ], $overrides);
}

it('records the full memorial, not just a name and two dates', function () {
    $owner = intakeReseller();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload())
        ->assertRedirect(route('reseller.memorials'));

    $memorial = Memorial::where('full_name', 'Jane Amara Doe')->firstOrFail();

    // The parts, not just the joined name: the platform edit form requires first and
    // last, so an intake that only stored full_name left the client unable to save.
    expect($memorial->first_name)->toBe('Jane')
        ->and($memorial->middle_name)->toBe('Amara')
        ->and($memorial->last_name)->toBe('Doe')
        ->and($memorial->primary_profession)->toBe('Teacher')
        ->and($memorial->birth_country)->toBe('Uganda')
        ->and($memorial->reseller_id)->toBe($owner->reseller_id)
        ->and($memorial->status)->toBe(Memorial::STATUS_ACTIVE)
        // Relations the old intake had no fields for at all.
        ->and($memorial->children()->count())->toBe(1)
        ->and($memorial->education()->first()->institution_name)->toBe('Makerere');
});

it('creates the client passwordless and invites them', function () {
    $owner = intakeReseller();

    $this->actingAs($owner)->post('http://localhost/reseller/memorials', intakePayload())->assertRedirect();

    $client = User::where('email', 'mary@example.test')->firstOrFail();

    expect($client->password)->toBeNull()
        ->and($client->hasRole('user'))->toBeTrue()
        ->and($client->reseller_id)->toBe($owner->reseller_id)
        ->and(Memorial::where('full_name', 'Jane Amara Doe')->first()->user_id)->toBe($client->id);

    expect(Notification::where('user_id', $client->id)->where('type', 'account_invite')->exists())->toBeTrue();
});

it('assigns to an existing client by id without renaming them', function () {
    $owner = intakeReseller();

    $existing = User::factory()->create(['name' => 'Mary Doe', 'email' => 'existing@example.test', 'reseller_id' => $owner->reseller_id]);
    $existing->assignRole('user');

    $this->actingAs($owner)->post('http://localhost/reseller/memorials', intakePayload([
        'client_id' => $existing->id,
        'client_name' => null,
        'client_email' => null,
    ]))->assertRedirect();

    expect(Memorial::where('full_name', 'Jane Amara Doe')->first()->user_id)->toBe($existing->id)
        ->and($existing->fresh()->name)->toBe('Mary Doe')
        ->and(User::where('reseller_id', $owner->reseller_id)->whereHas('roles', fn ($q) => $q->where('name', 'user'))->count())->toBe(1);

    // Still told: the notice names the memorial, so an existing client learns a page has
    // been made for their family instead of only new accounts hearing anything.
    expect(Notification::where('user_id', $existing->id)->where('type', 'account_invite')->exists())->toBeTrue();
});

it('refuses to hand a memorial to a staff account picked by id', function () {
    $owner = intakeReseller();

    // The owner is in the tenant but is not a client. Assigning a family's memorial into
    // a staff account would put it under the wrong person's ownership entirely.
    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload([
            'client_id' => $owner->id, 'client_name' => null, 'client_email' => null,
        ]))
        ->assertSessionHasErrors('client_id');

    expect(Memorial::count())->toBe(0);
});

it('refuses to reuse a staff email as a client', function () {
    $owner = intakeReseller();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload(['client_email' => $owner->email]))
        ->assertSessionHasErrors('client_email');

    expect(Memorial::count())->toBe(0);
});

it('creates no orphan client when the allowance is already gone', function () {
    $owner = intakeReseller(['memorial_profile_allowance' => 0]);

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload())
        ->assertSessionHas('error');

    // Refusing after creating the account would leave a passwordless stranger behind —
    // and the retry would then find it and skip the invitation.
    expect(User::where('email', 'mary@example.test')->exists())->toBeFalse()
        ->and(Memorial::count())->toBe(0);
});

it('refuses a client belonging to another reseller', function () {
    $owner = intakeReseller();
    $other = intakeReseller();

    $outsider = User::factory()->create(['reseller_id' => $other->reseller_id]);
    $outsider->assignRole('user');

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload(['client_id' => $outsider->id, 'client_name' => null, 'client_email' => null]))
        ->assertSessionHasErrors('client_id');

    expect(Memorial::count())->toBe(0);
});

it('refuses another tenant\'s plan', function () {
    $owner = intakeReseller();
    $other = intakeReseller();
    $foreignPlan = intakePlan($other->reseller_id, 50, 'Foreign');

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload(['plan_id' => $foreignPlan->id]))
        ->assertSessionHasErrors('plan_id');

    expect(Memorial::count())->toBe(0);
});

it('records a chosen paid plan as an active offline subscription', function () {
    $owner = intakeReseller();
    $paid = intakePlan($owner->reseller_id, 120, 'Premium');

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload(['plan_id' => $paid->id]))
        ->assertRedirect();

    $memorial = Memorial::where('full_name', 'Jane Amara Doe')->firstOrFail();
    $subscription = UserSubscription::where('memorial_id', $memorial->id)->firstOrFail();

    expect($memorial->plan)->toBe(Memorial::PLAN_PAID)
        ->and($memorial->subscription_plan_id)->toBe($paid->id)
        ->and($memorial->user_subscription_id)->toBe($subscription->id)
        ->and($subscription->status)->toBe('active')
        // Marked offline so reseller revenue reports can tell staff-recorded
        // arrangements apart from gateway-collected ones.
        ->and($subscription->payment_gateway)->toBe('offline')
        ->and($subscription->payment_reference)->toBe('reseller-intake')
        ->and($subscription->ends_at)->not->toBeNull()
        // The entitlements the plan was chosen for are live immediately.
        ->and(PlanLimitsHelper::getEffectivePlan($memorial)?->id)->toBe($paid->id);
});

it('records a chosen free plan as a never-ending subscription', function () {
    $owner = intakeReseller();
    $free = intakePlan($owner->reseller_id, 0, 'Free');

    $this->actingAs($owner)->post('http://localhost/reseller/memorials', intakePayload(['plan_id' => $free->id]))->assertRedirect();

    $subscription = UserSubscription::firstOrFail();

    expect($subscription->ends_at)->toBeNull()
        ->and($subscription->payment_gateway)->toBeNull()
        ->and(Memorial::first()->plan)->toBe(Memorial::PLAN_FREE);
});

it('needs either a picked client or a new one', function () {
    $owner = intakeReseller();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/memorials', intakePayload(['client_name' => null, 'client_email' => null]))
        ->assertSessionHasErrors(['client_id', 'client_name', 'client_email']);

    expect(Memorial::count())->toBe(0);
});

it('shows the create screen the full form and the reseller\'s own plans', function () {
    $owner = intakeReseller();
    intakePlan($owner->reseller_id, 0, 'Reseller Free');
    intakePlan(null, 25, 'Platform Only');

    $this->actingAs($owner)->get('http://localhost/reseller/memorials/create')
        ->assertOk()
        // Sections that only exist because the form is shared with /memorials/create.
        ->assertSee('Biography Summary')
        ->assertSee('Family Relationships')
        ->assertSee('Education')
        ->assertSee('Reseller Free')
        ->assertDontSee('Platform Only');
});
