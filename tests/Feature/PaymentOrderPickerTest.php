<?php

use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The two fields that decide who is being granted what.
 *
 * They are one decision made in two selects, and nothing kept them agreeing: the server refuses
 * a mismatched pair with "Memorial must belong to the selected user", which is the right guard
 * and the wrong moment — you find out after submitting, and the message describes a problem you
 * did not think you had.
 *
 * Worse, an admin could not select themselves at all: the list filtered them out, so granting on
 * a memorial you own meant naming somebody else as the user and getting that same misleading
 * message back.
 *
 * A `different:$admin->id` rule sat next to the filter and looked like the real control. It was
 * not one — `different:` names another field, so `different:5` compared user_id against a
 * request field literally called "5" and passed every time for want of one. Which is why
 * reverting the controller does not make the first test below fail: only the list ever blocked
 * anything. The rule went anyway, so nobody reads it as a guard that exists.
 */
function pickerAdmin(): User
{
    SystemSetting::set('payments.enabled', true);
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('user', 'web');

    $admin = User::factory()->create(['name' => 'Rio Akram']);
    $admin->assignRole('super-admin');

    return $admin;
}

function pickerPlan(): SubscriptionPlan
{
    return SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-'.uniqid(),
        'price' => 25,
        'is_active' => true,
    ]);
}

it('lets an admin grant a plan on a memorial they own themselves', function () {
    // The failure that started this. The admin owns the memorial, picks themselves, and used to
    // be told the memorial belonged to somebody else.
    $admin = pickerAdmin();
    $plan = pickerPlan();
    $memorial = Memorial::factory()->create(['user_id' => $admin->id, 'reseller_id' => null]);

    $this->actingAs($admin)
        ->post('/settings/payment-orders', [
            'user_id' => $admin->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('error');

    expect(PaymentOrder::where('memorial_id', $memorial->id)->exists())->toBeTrue()
        ->and($memorial->fresh()->subscription_plan_id)->toBe($plan->id);
});

it('marks the admin as You and puts them first', function () {
    $admin = pickerAdmin();

    // Somebody alphabetically ahead of the admin, so "first" means the ordering rule and not
    // luck of the alphabet.
    User::factory()->create(['name' => 'Aaron Someone']);

    $results = $this->actingAs($admin)
        ->getJson('/settings/payment-orders/options?type=users')
        ->assertOk()
        ->json('results');

    expect($results[0]['id'])->toBe($admin->id)
        ->and($results[0]['label'])->toContain('(You)')
        // And nobody else is labelled as you.
        ->and(collect($results)->filter(fn ($r) => str_contains($r['label'], '(You)'))->count())->toBe(1);
});

it('finds a user by name or email rather than making you scroll', function () {
    $admin = pickerAdmin();
    User::factory()->create(['name' => 'Grace Nakato', 'email' => 'grace@example.test']);
    User::factory()->create(['name' => 'Peter Okello', 'email' => 'peter@example.test']);

    $byName = $this->actingAs($admin)->getJson('/settings/payment-orders/options?type=users&q=Nakato')->json('results');
    $byEmail = $this->actingAs($admin)->getJson('/settings/payment-orders/options?type=users&q=peter@')->json('results');

    expect($byName)->toHaveCount(1)
        ->and($byName[0]['label'])->toBe('Grace Nakato')
        ->and($byEmail)->toHaveCount(1)
        ->and($byEmail[0]['sub'])->toBe('peter@example.test');
});

it('offers only the memorials the chosen person owns', function () {
    // This is what makes the pair safe. Narrowed to one owner, the mismatch the server rejects
    // cannot be assembled by hand.
    $admin = pickerAdmin();
    $grace = User::factory()->create(['name' => 'Grace Nakato']);

    $hers = Memorial::factory()->create(['user_id' => $grace->id, 'full_name' => 'Her Person', 'reseller_id' => null]);
    Memorial::factory()->create(['user_id' => $admin->id, 'full_name' => 'Another Person', 'reseller_id' => null]);

    $results = $this->actingAs($admin)
        ->getJson('/settings/payment-orders/options?type=memorials&user_id='.$grace->id)
        ->assertOk()
        ->json('results');

    expect($results)->toHaveCount(1)
        ->and($results[0]['id'])->toBe($hers->id);
});

it('carries the owner back with a memorial so the user field can fill itself in', function () {
    $admin = pickerAdmin();
    $grace = User::factory()->create(['name' => 'Grace Nakato']);
    $memorial = Memorial::factory()->create(['user_id' => $grace->id, 'full_name' => 'Her Person', 'reseller_id' => null]);

    $results = $this->actingAs($admin)
        ->getJson('/settings/payment-orders/options?type=memorials&q=Her Person')
        ->assertOk()
        ->json('results');

    expect($results[0]['user_id'])->toBe($grace->id)
        ->and($results[0]['sub'])->toBe('Grace Nakato');

    // And a memorial the admin owns says so, for the same reason the user list does.
    $own = Memorial::factory()->create(['user_id' => $admin->id, 'full_name' => 'Own Person', 'reseller_id' => null]);

    $mine = $this->actingAs($admin)
        ->getJson('/settings/payment-orders/options?type=memorials&q=Own Person')
        ->json('results');

    expect($mine[0]['sub'])->toContain('(You)')
        ->and($mine[0]['id'])->toBe($own->id);
});

it('renders both fields as searchable and wires them to the endpoint', function () {
    // The screen itself, not just the endpoint behind it. Two comboboxes, both pointed at the
    // search route, and none of the old render-every-row selects left behind.
    $admin = pickerAdmin();

    $html = $this->actingAs($admin)->get('/settings/payment-orders')->assertOk()->getContent();

    // @js() JSON-encodes the route, which escapes the slashes — so the endpoint appears as
    // `payment-orders\/options` in the source. Unescaped before looking for it.
    $plain = str_replace('\/', '/', $html);

    expect(substr_count($html, 'optionSearch('))->toBe(2)
        ->and(str_contains($plain, 'payment-orders/options'))->toBeTrue()
        ->and(str_contains($html, 'name="user_id"'))->toBeTrue()
        ->and(str_contains($html, 'name="memorial_id"'))->toBeTrue()
        // The create form's two giant selects are gone; what is left is the inline edit rows.
        ->and(str_contains($html, 'Select memorial...'))->toBeFalse();
});

it('keeps the picker away from customers', function () {
    // It answers with names, emails and every memorial on the platform.
    $customer = User::factory()->create();
    $customer->assignRole(Role::findOrCreate('user', 'web'));

    $this->actingAs($customer)->getJson('/settings/payment-orders/options?type=users')->assertForbidden();
});

it('still refuses a pair that does not belong together', function () {
    // The picker makes the mismatch hard to assemble; it does not make the server trust the
    // request. Both halves stay.
    $admin = pickerAdmin();
    $grace = User::factory()->create();
    $stranger = User::factory()->create();
    $plan = pickerPlan();
    $memorial = Memorial::factory()->create(['user_id' => $stranger->id, 'reseller_id' => null]);

    $this->actingAs($admin)
        ->post('/settings/payment-orders', [
            'user_id' => $grace->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertSessionHas('error');

    expect(PaymentOrder::count())->toBe(0);
});
