<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerPayment;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function billedReseller(array $tierAttributes = []): Reseller
{
    Role::findOrCreate('reseller', 'web');

    $tier = ResellerTier::create(array_merge([
        'name' => 'Professional', 'slug' => 'pro-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 500, 'memorial_profile_allowance' => 10,
        'price_per_additional_profile' => 25, 'storage_limit_gb' => null,
        'feature_embedding' => false, 'feature_domain_routing' => false,
        'feature_business_analytics' => false, 'is_active' => true,
    ], $tierAttributes));

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme Funeral Home', 'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id]);

    return $reseller->load('tier');
}

function billingAdmin(): User
{
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    return $admin;
}

it('charges the annual price with no overage inside the allowance', function () {
    $reseller = billedReseller();
    Memorial::factory()->count(10)->create(['reseller_id' => $reseller->id]);

    expect($reseller->overageProfiles())->toBe(0)
        ->and($reseller->overageAmount())->toBe(0.0)
        ->and($reseller->amountDue())->toBe(500.0);
});

it('adds overage for profiles beyond the allowance', function () {
    $reseller = billedReseller();
    Memorial::factory()->count(13)->create(['reseller_id' => $reseller->id]);

    expect($reseller->overageProfiles())->toBe(3)
        ->and($reseller->overageAmount())->toBe(75.0)
        ->and($reseller->amountDue())->toBe(575.0);
});

it('never charges overage on an unlimited tier', function () {
    $reseller = billedReseller(['memorial_profile_allowance' => null]);
    Memorial::factory()->count(50)->create(['reseller_id' => $reseller->id]);

    expect($reseller->overageProfiles())->toBe(0)
        ->and($reseller->amountDue())->toBe(500.0);
});

it('distinguishes never-invoiced from due and overdue', function () {
    $reseller = billedReseller();
    expect($reseller->billingStatus())->toBe(Reseller::BILLING_NOT_STARTED)
        ->and($reseller->daysUntilRenewal())->toBeNull();

    $reseller->update(['billing_period_end' => now()->addDays(200)]);
    expect($reseller->fresh()->billingStatus())->toBe(Reseller::BILLING_ACTIVE);

    $reseller->update(['billing_period_end' => now()->addDays(10)]);
    expect($reseller->fresh()->billingStatus())->toBe(Reseller::BILLING_DUE_SOON);

    $reseller->update(['billing_period_end' => now()->subDay()]);
    expect($reseller->fresh()->billingStatus())->toBe(Reseller::BILLING_OVERDUE);
});

it('records a first payment and starts a one-year period from today', function () {
    $reseller = billedReseller();

    $this->actingAs(billingAdmin())
        ->post('http://localhost/settings/resellers/'.$reseller->id.'/payments', [
            'amount' => 500,
            'method' => ResellerPayment::METHOD_TRANSFER,
            'paid_at' => now()->toDateString(),
            'reference' => 'TRF-001',
        ])->assertRedirect();

    $reseller->refresh();

    expect($reseller->billing_period_start->toDateString())->toBe(now()->startOfDay()->toDateString())
        ->and($reseller->billing_period_end->toDateString())->toBe(now()->startOfDay()->addYear()->toDateString())
        ->and($reseller->billingStatus())->toBe(Reseller::BILLING_ACTIVE);

    $payment = $reseller->payments()->first();
    expect((float) $payment->amount)->toBe(500.0)
        ->and($payment->reference)->toBe('TRF-001')
        ->and($payment->tier_name)->toBe('Professional');
});

it('renews from the old end date so paying late does not shift the anniversary', function () {
    $reseller = billedReseller();
    $renewal = now()->addDays(10)->startOfDay();
    $reseller->update(['billing_period_end' => $renewal]);

    // Paid 10 days before renewal: the new period must start at the renewal date, not
    // today, or every early payment would quietly shorten the year they paid for.
    $this->actingAs(billingAdmin())
        ->post('http://localhost/settings/resellers/'.$reseller->id.'/payments', [
            'amount' => 500,
            'method' => ResellerPayment::METHOD_TRANSFER,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

    expect($reseller->fresh()->billing_period_end->toDateString())
        ->toBe($renewal->copy()->addYear()->toDateString());
});

it('restarts from today when the previous period already lapsed', function () {
    $reseller = billedReseller();
    $reseller->update(['billing_period_end' => now()->subMonths(3)]);

    $this->actingAs(billingAdmin())
        ->post('http://localhost/settings/resellers/'.$reseller->id.'/payments', [
            'amount' => 500,
            'method' => ResellerPayment::METHOD_MANUAL,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

    // A lapsed reseller gets a fresh year, not one backdated into the past.
    expect($reseller->fresh()->billing_period_end->toDateString())
        ->toBe(now()->startOfDay()->addYear()->toDateString());
});

it('snapshots the tier so history survives a later price change', function () {
    $reseller = billedReseller();
    Memorial::factory()->count(12)->create(['reseller_id' => $reseller->id]);

    $this->actingAs(billingAdmin())
        ->post('http://localhost/settings/resellers/'.$reseller->id.'/payments', [
            'amount' => 550,
            'method' => ResellerPayment::METHOD_TRANSFER,
            'paid_at' => now()->toDateString(),
        ])->assertRedirect();

    $reseller->tier->update(['name' => 'Renamed', 'annual_price' => 9999]);

    $payment = $reseller->payments()->first();
    expect($payment->tier_name)->toBe('Professional')
        ->and((float) $payment->tier_annual_price)->toBe(500.0)
        ->and($payment->overage_profiles)->toBe(2)
        ->and((float) $payment->overage_amount)->toBe(50.0);
});

it('lets an admin filter the roster down to overdue resellers', function () {
    $overdue = billedReseller();
    $overdue->update(['billing_period_end' => now()->subWeek()]);
    $overdue->update(['name' => 'Lapsed Chapel']);

    $current = billedReseller();
    $current->update(['billing_period_end' => now()->addMonths(6), 'name' => 'Paid Up Chapel']);

    $this->actingAs(billingAdmin())
        ->get('http://localhost/settings/resellers?status=overdue')
        ->assertOk()
        ->assertSee('Lapsed Chapel')
        ->assertDontSee('Paid Up Chapel');
});
