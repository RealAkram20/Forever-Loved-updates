<?php

use App\Exceptions\ResellerCapacityExceededException;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MemorialCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The single implementation every creation path now shares. Before it existed there were
 * three copies of this logic and three slug generators that numbered collisions
 * differently, so which door a memorial came through changed what it ended up being.
 */
beforeEach(function () {
    Role::findOrCreate('user', 'web');
    $this->service = app(MemorialCreationService::class);
    $this->owner = User::factory()->create(['reseller_id' => null]);
});

it('composes the name, title and slug from the parts', function () {
    $memorial = $this->service->create($this->owner, [
        'first_name' => 'Jane', 'middle_name' => 'Amara', 'last_name' => 'Doe', 'theme' => 'free',
    ]);

    expect($memorial->full_name)->toBe('Jane Amara Doe')
        ->and($memorial->title)->toBe('In Loving Memory of Jane Amara Doe')
        ->and($memorial->slug)->toBe('jane-amara-doe')
        ->and($memorial->completion_status)->toBe(Memorial::COMPLETION_PENDING)
        ->and($memorial->is_public)->toBeTrue();
});

it('numbers slug collisions from -1', function () {
    foreach (range(1, 3) as $ignored) {
        $this->service->create($this->owner, ['first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free']);
    }

    expect(Memorial::orderBy('id')->pluck('slug')->all())->toBe(['jane-doe', 'jane-doe-1', 'jane-doe-2']);
});

it('excludes a memorial from its own collision check', function () {
    $memorial = $this->service->create($this->owner, ['first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free']);

    // Regenerating for the same record must not push it to jane-doe-1.
    expect($this->service->uniqueSlug('Jane Doe', $memorial->id))->toBe('jane-doe');
});

it('persists the repeater relations', function () {
    $memorial = $this->service->create($this->owner, [
        'first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free',
        'companies' => [['company_name' => 'Acme Ltd']],
        'co_founders' => [['name' => 'Sam Partner']],
        'children' => [['child_name' => 'Peter', 'birth_year' => 1975], ['child_name' => '']],
        'spouses' => [['spouse_name' => 'John', 'marriage_start_year' => 1970, 'marriage_end_year' => 2010]],
        'parents' => [['parent_name' => 'Grace', 'relationship_type' => 'adoptive']],
        'siblings' => [['sibling_name' => 'Ruth']],
        'education' => [['institution_name' => 'Makerere', 'start_year' => 1968, 'end_year' => 1972, 'degree' => 'BA']],
    ]);

    expect($memorial->notableCompanies()->count())->toBe(1)
        ->and($memorial->coFounders()->count())->toBe(1)
        // The blank row is dropped rather than stored as an empty child.
        ->and($memorial->children()->count())->toBe(1)
        ->and($memorial->spouses()->first()->marriage_end_year)->toBe(2010)
        ->and($memorial->parents()->first()->relationship_type)->toBe('adoptive')
        ->and($memorial->siblings()->count())->toBe(1)
        ->and($memorial->education()->first()->degree)->toBe('BA');
});

it('generates a template biography only when none was written', function () {
    $written = $this->service->create($this->owner, [
        'first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free',
        'biography' => '<p>Her own words.</p>',
    ]);

    expect($written->biography)->toBe('<p>Her own words.</p>');

    $blank = $this->service->create($this->owner, [
        'first_name' => 'John', 'last_name' => 'Roe', 'theme' => 'free', 'biography' => '   ',
    ]);

    // Something was written for them — a memorial published with an empty story
    // reads as abandoned.
    expect($blank->biography)->not->toBeEmpty();
});

it('throws when the tenant is out of allowance, and not before', function () {
    Role::findOrCreate('reseller', 'web');

    $tier = ResellerTier::create([
        'name' => 'One', 'slug' => 'one-'.uniqid(), 'sort_order' => 0, 'annual_price' => 10,
        'memorial_profile_allowance' => 1, 'price_per_additional_profile' => 1,
        'storage_limit_gb' => 1, 'is_active' => true,
    ]);

    $staff = User::factory()->create();
    $staff->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Solo', 'slug' => 'solo-'.substr(uniqid(), -8), 'owner_user_id' => $staff->id,
        'reseller_tier_id' => $tier->id, 'status' => Reseller::STATUS_ACTIVE,
    ]);

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    $this->service->create($client, ['first_name' => 'First', 'last_name' => 'Fit', 'theme' => 'free']);

    expect(fn () => $this->service->create($client, ['first_name' => 'Second', 'last_name' => 'Over', 'theme' => 'free']))
        ->toThrow(ResellerCapacityExceededException::class);

    expect(Memorial::count())->toBe(1);
});

it('leaves an untenanted owner unmetered', function () {
    foreach (range(1, 4) as $i) {
        $this->service->create($this->owner, ['first_name' => "Name{$i}", 'last_name' => 'Platform', 'theme' => 'free']);
    }

    expect(Memorial::count())->toBe(4);
});

it('stamps a free plan as a never-ending active subscription', function () {
    $plan = SubscriptionPlan::create([
        'name' => 'Free', 'slug' => 'free-'.uniqid(), 'price' => 0, 'interval' => 'monthly',
        'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true,
    ]);

    $memorial = $this->service->create($this->owner, ['first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free'], $plan);
    $subscription = $this->service->attachPlanSubscription($memorial, $plan);

    expect($subscription->status)->toBe('active')
        ->and($subscription->ends_at)->toBeNull()
        ->and($subscription->payment_gateway)->toBeNull()
        ->and($memorial->fresh()->plan)->toBe(Memorial::PLAN_FREE)
        ->and($memorial->fresh()->user_subscription_id)->toBe($subscription->id);
});

it('dates an offline paid subscription by the plan interval', function () {
    $plan = SubscriptionPlan::create([
        'name' => 'Yearly', 'slug' => 'yearly-'.uniqid(), 'price' => 120, 'interval' => 'yearly',
        'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true,
    ]);

    $memorial = $this->service->create($this->owner, ['first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free'], $plan);
    $subscription = $this->service->attachPlanSubscription($memorial, $plan, 'offline', 'reseller-intake');

    expect($subscription->payment_gateway)->toBe('offline')
        ->and($subscription->payment_reference)->toBe('reseller-intake')
        ->and($subscription->ends_at->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($memorial->fresh()->plan)->toBe(Memorial::PLAN_PAID);
});

it('lets an explicit override win over the owner\'s own tenant', function () {
    Role::findOrCreate('reseller', 'web');
    $staff = User::factory()->create();
    $staff->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Override', 'slug' => 'override-'.substr(uniqid(), -8),
        'owner_user_id' => $staff->id, 'status' => Reseller::STATUS_ACTIVE,
    ]);

    $memorial = $this->service->create(
        $this->owner,
        ['first_name' => 'Jane', 'last_name' => 'Doe', 'theme' => 'free'],
        null,
        ['reseller_id' => $reseller->id, 'status' => Memorial::STATUS_ACTIVE]
    );

    expect($memorial->reseller_id)->toBe($reseller->id)
        ->and($memorial->status)->toBe(Memorial::STATUS_ACTIVE);
});
