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
 * Manual payment is the admin's comp switch, and only the admin's.
 *
 * It exists so somebody at the platform can grant a plan without money moving — a partner
 * memorial, a family in an impossible week, an order that was paid outside the system. Set a
 * manual order to `completed` and the plan activates on the spot.
 *
 * Which is exactly why a customer must never reach it. The customer checkout hardcodes
 * `gateway: 'pesapal'` and offers no alternative, but a hidden option in a form is not a
 * control: anyone can post the field. So the guard is on the server, and these assert it from
 * the outside — by posting the request a curious visitor would post, rather than by reading the
 * branch that is supposed to stop them.
 */
function paymentActors(): array
{
    // Payments are off by default, and the controller checks that before it checks anything
    // else. Without this the customer's manual request is turned away with "Payments are not
    // enabled" — a 400 that looks like the guard working and is not the guard at all.
    SystemSetting::set('payments.enabled', true);

    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('user', 'web');

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $customer = User::factory()->create();
    $customer->assignRole('user');

    return [$admin, $customer];
}

function paidPlan(): SubscriptionPlan
{
    // Created rather than factoried: there is no SubscriptionPlan factory, and every other
    // test in this suite builds one the same way.
    return SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-'.uniqid(),
        'price' => 25,
        'is_active' => true,
    ]);
}

it('refuses a manual order from a customer who asks for one directly', function () {
    // The request a hidden form field cannot prevent.
    [, $customer] = paymentActors();
    $plan = paidPlan();
    $memorial = Memorial::factory()->create(['user_id' => $customer->id, 'reseller_id' => null]);

    $this->actingAs($customer)
        ->postJson('/payment/create-order', [
            'plan_id' => $plan->id,
            'memorial_id' => $memorial->id,
            'payment_gateway' => 'manual',
        ])
        // 403, not merely "some error". The first version of this test passed on a 400 that
        // turned out to be "Payments are not enabled" — the request never reached the guard,
        // and the test would have gone on passing with the guard deleted.
        ->assertStatus(403)
        ->assertJsonPath('error', 'That payment method is not available. Please pay with mobile money or card.');

    // Refused outright rather than quietly downgraded to a pending order somebody has to
    // notice and chase — a silent pending order is how a free plan gets granted by accident.
    expect(PaymentOrder::where('payment_gateway', 'manual')->count())->toBe(0);
});

it('never offers manual in the customer checkout', function () {
    [, $customer] = paymentActors();

    $html = $this->actingAs($customer)->get('/subscription')->assertOk()->getContent();

    // The gateway the checkout commits to, with no branch that could pick the other.
    expect(str_contains($html, "gateway: 'pesapal'"))->toBeTrue()
        ->and(preg_match('#value=["\']manual["\']#', $html))->toBe(0);
});

it('keeps the admin payment-orders screen away from customers', function () {
    [$admin, $customer] = paymentActors();

    $this->actingAs($customer)->get('/settings/payment-orders')->assertForbidden();
    $this->actingAs($admin)->get('/settings/payment-orders')->assertOk();
});

it('lets an admin grant a plan on a memorial with a completed manual order', function () {
    // The other half. A guard that also blocked the admin would be a bug, not caution.
    [$admin, $customer] = paymentActors();
    $plan = paidPlan();
    $memorial = Memorial::factory()->create(['user_id' => $customer->id, 'reseller_id' => null]);

    $this->actingAs($admin)
        ->post('/settings/payment-orders', [
            'user_id' => $customer->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertSessionHasNoErrors();

    $order = PaymentOrder::where('memorial_id', $memorial->id)->first();

    expect($order)->not->toBeNull()
        ->and($order->payment_gateway)->toBe('manual')
        ->and($order->status)->toBe('completed')
        // Granted, not merely recorded: the plan is on the memorial.
        ->and($memorial->fresh()->subscription_plan_id)->toBe($plan->id);
});

it('refuses to grant a plan on a memorial the named user does not own', function () {
    // The admin picks a user and a memorial in two separate fields, and nothing about the UI
    // stops those two disagreeing. Granting anyway would attach a plan to a stranger's page.
    [$admin, $customer] = paymentActors();
    $stranger = User::factory()->create();
    $plan = paidPlan();
    $memorial = Memorial::factory()->create(['user_id' => $stranger->id, 'reseller_id' => null]);

    $this->actingAs($admin)
        ->post('/settings/payment-orders', [
            'user_id' => $customer->id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'payment_gateway' => 'manual',
            'status' => 'completed',
        ])
        ->assertSessionHas('error');

    expect(PaymentOrder::count())->toBe(0)
        ->and($memorial->fresh()->subscription_plan_id)->not->toBe($plan->id);
});
