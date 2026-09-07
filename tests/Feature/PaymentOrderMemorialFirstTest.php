<?php

use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Creating a payment order is memorial-led: a super-admin searches any memorial, and the user is
 * the memorial's owner.
 *
 * The form's two fields were wired into a feedback loop -- picking a memorial filled the user,
 * and filling the user cleared the memorial -- so `memorial_id` posted empty and every submission
 * failed "The memorial id field is required." These cover the two things the server must
 * guarantee for the fixed flow: the options endpoint offers any memorial (not only the current
 * admin's) and carries the owner id for the auto-fill, and the store accepts an order a
 * super-admin raises against someone else's memorial.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('user', 'web');

    $this->admin = User::factory()->create(['name' => 'Site Admin', 'email' => 'admin@example.test']);
    $this->admin->assignRole('super-admin');

    $this->family = User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);
    $this->family->assignRole('user');

    $this->memorial = Memorial::factory()->create([
        'user_id' => $this->family->id,
        'full_name' => 'John Doe',
        'reseller_id' => null,
    ]);
});

$freePlan = fn () => SubscriptionPlan::create([
    'name' => 'Free', 'slug' => 'free-'.uniqid(), 'price' => 0, 'is_active' => true,
]);

it('offers any memorial to a super-admin and carries the owner id for the auto-fill', function () {
    // A memorial owned by someone other than the admin, searched with no user filter, must
    // still come back -- and must carry user_id so the form can fill the owner in.
    $res = $this->actingAs($this->admin)
        ->getJson(route('settings.payment-orders.options', ['type' => 'memorials', 'q' => 'John']))
        ->assertOk();

    $row = collect($res->json('results'))->firstWhere('id', $this->memorial->id);

    expect($row)->not->toBeNull('the memorial was not offered')
        ->and($row['user_id'])->toBe($this->family->id)
        ->and($row['label'])->toBe('John Doe');
});

it('lets a super-admin create an order against another user\'s memorial', function () use ($freePlan) {
    $plan = $freePlan();

    $this->actingAs($this->admin)
        ->post(route('settings.payment-orders.store'), [
            'memorial_id' => $this->memorial->id,
            'user_id' => $this->family->id,       // as the auto-fill supplies it
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PaymentOrder::where('memorial_id', $this->memorial->id)
        ->where('user_id', $this->family->id)->exists())->toBeTrue();
});

it('still rejects a user who does not own the chosen memorial', function () use ($freePlan) {
    // The guard the auto-fill makes unreachable by hand must still hold if the pair is posted
    // mismatched directly.
    $stranger = User::factory()->create();
    $plan = $freePlan();

    $this->actingAs($this->admin)
        ->post(route('settings.payment-orders.store'), [
            'memorial_id' => $this->memorial->id,
            'user_id' => $stranger->id,
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertSessionHas('error', 'Memorial must belong to the selected user.');

    expect(PaymentOrder::where('memorial_id', $this->memorial->id)->exists())->toBeFalse();
});

it('renders the create form with Memorial before User', function () {
    $html = $this->actingAs($this->admin)->get(route('settings.payment-orders'))->assertOk()->getContent();

    // The create form is the last card on the page (the existing-orders table above it also
    // renders memorial_id in its edit rows), so its memorial field is the LAST occurrence. The
    // helper line under the auto-filled User field is plain HTML unique to this form.
    $helper = strpos($html, "Selected automatically from the memorial");
    $createMemorial = strrpos($html, 'name="memorial_id"');

    expect($helper)->not->toBeFalse('the auto-filled User helper text is missing')
        ->and($createMemorial)->not->toBeFalse()
        ->and($createMemorial)->toBeLessThan($helper, 'Memorial field should render before the auto-filled User field');
});
