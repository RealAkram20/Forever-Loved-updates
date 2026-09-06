<?php

use App\Models\Memorial;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Support\JunkUserPurge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

/**
 * Cleaning up after the 2026-09-04 relay, without cleaning up anyone else.
 *
 * The thing to get right is not the deleting; it is the refusing. `users.user_id` cascades
 * onto memorials, subscriptions and payment orders, so a bulk delete that can be pointed at
 * the wrong row takes a family's memorial with it. Most of these tests are about the rows that
 * must survive.
 */
uses(RefreshDatabase::class);

$payload = 'Your account has been inactive for 364 days. To avoid deletion and claim your balance, please sign in and request a withdrawal within 24 hours. For support, join graph.org/UXoRKiiyhc-09-04?auK64r';

beforeEach(function () {
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $this->admin = User::factory()->create(['name' => 'Site Admin']);
    $this->admin->assignRole('super-admin');
});

$junk = fn (string $name, string $email = null) => User::factory()->create([
    'name' => $name,
    'email' => $email ?? fake()->unique()->safeEmail(),
    'password' => null,
]);

it('lists the relay payloads under the suspicious filter and nothing else', function () use ($payload, $junk) {
    $junk($payload, 'victim1@example.test');
    $junk('Claim funds at graph.org/abc?x', 'victim2@example.test');
    $junk('www.evil.example wins', 'victim3@example.test');
    $junk(str_repeat('a', 81), 'victim4@example.test');
    $grace = User::factory()->create(['name' => 'Grace Namutebi', 'email' => 'grace@example.test']);
    $ngugi = User::factory()->create(['name' => "Ngũgĩ wa Thiong'o", 'email' => 'ngugi@example.test']);

    expect(JunkUserPurge::query()->count())->toBe(4);

    $this->actingAs($this->admin)
        ->get(route('users.index', ['suspicious' => 1]))
        ->assertOk()
        ->assertSee('victim1@example.test')
        ->assertSee('victim4@example.test')
        ->assertDontSee('grace@example.test')
        ->assertDontSee('ngugi@example.test')
        // The controls themselves. Asserted here rather than eyeballed, so a refactor of
        // the table cannot silently drop the checkbox column or the bulk form.
        ->assertSee('name="suspicious"', false)
        ->assertSee('id="bulk-ids"', false)
        ->assertSee('form="bulk-ids"', false)
        ->assertSee('Delete all suspicious');
});

it('does not list a suspicious-looking name that owns a memorial', function () use ($junk) {
    // A real person who once pasted a URL into their name still has a page. The memorial
    // condition is part of the definition, not only a guard on the delete.
    $owner = $junk('see www.myshop.example', 'owner@example.test');
    Memorial::factory()->create(['user_id' => $owner->id]);

    expect(JunkUserPurge::query()->pluck('id'))->not->toContain($owner->id);
});

it('deletes the selected junk accounts and says how many', function () use ($payload, $junk) {
    $a = $junk($payload);
    $b = $junk('graph.org/zzz');
    $keep = User::factory()->create(['name' => 'Grace Namutebi']);

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'ids', 'ids' => [$a->id, $b->id]])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Deleted 2 users'));

    expect(User::whereKey([$a->id, $b->id])->count())->toBe(0)
        ->and(User::whereKey($keep->id)->exists())->toBeTrue();
});

it('refuses to delete anyone who owns a memorial, even when explicitly selected', function () use ($junk) {
    // The cascade guard. This is the test that matters.
    $family = $junk('graph.org/looks-like-junk', 'family@example.test');
    $memorial = Memorial::factory()->create(['user_id' => $family->id]);

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'ids', 'ids' => [$family->id]])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'owns memorials'));

    expect(User::whereKey($family->id)->exists())->toBeTrue()
        ->and(Memorial::whereKey($memorial->id)->exists())->toBeTrue();
});

it('refuses anyone with payment history', function () use ($junk) {
    $payer = $junk('graph.org/paid-once');
    // No factories for either model; built the way ManualPaymentIsAdminOnlyTest builds them.
    $plan = \App\Models\SubscriptionPlan::create(['name' => 'Premium', 'slug' => 'premium-'.uniqid(), 'price' => 25, 'is_active' => true]);
    PaymentOrder::forceCreate(['user_id' => $payer->id, 'subscription_plan_id' => $plan->id, 'merchant_reference' => 'TEST-'.uniqid(), 'amount' => 25, 'currency' => 'USD']);

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'ids', 'ids' => [$payer->id]])
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'payment history'));

    expect(User::whereKey($payer->id)->exists())->toBeTrue();
});

it('refuses staff and refuses the actor, and still deletes the rest of the batch', function () use ($junk) {
    $staff = User::factory()->create(['name' => 'graph.org/but-an-admin']);
    $staff->assignRole('admin');
    $junkRow = $junk('graph.org/really-junk');

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'ids', 'ids' => [$staff->id, $this->admin->id, $junkRow->id]])
        ->assertSessionHas('success', function ($msg) {
            return str_contains($msg, 'Deleted 1 user') && str_contains($msg, 'staff') && str_contains($msg, 'your own account');
        });

    expect(User::whereKey($staff->id)->exists())->toBeTrue()
        ->and(User::whereKey($this->admin->id)->exists())->toBeTrue()
        ->and(User::whereKey($junkRow->id)->exists())->toBeFalse();
});

it('deletes everything matching the suspicious filter in scope mode, and nobody else', function () use ($payload, $junk) {
    foreach (range(1, 6) as $i) {
        $junk($payload, "victim{$i}@example.test");
    }
    $grace = User::factory()->create(['name' => 'Grace Namutebi']);

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'scope'])
        ->assertRedirect()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Deleted 6 users'));

    expect(JunkUserPurge::query()->count())->toBe(0)
        ->and(User::whereKey($grace->id)->exists())->toBeTrue()
        ->and(User::whereKey($this->admin->id)->exists())->toBeTrue();
});

it('caps a web request and says how many remain', function () use ($junk) {
    $over = JunkUserPurge::WEB_BATCH + 3;

    // Bulk-insert: one factory call per row would make this the slowest test in the suite.
    User::insert(collect(range(1, $over))->map(fn ($i) => [
        'name' => "graph.org/batch{$i}",
        'email' => "batch{$i}@example.test",
        'password' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all());

    $this->actingAs($this->admin)
        ->post(route('users.bulk-destroy'), ['mode' => 'scope'])
        ->assertSessionHas('success', function ($msg) {
            return str_contains($msg, 'Deleted '.number_format(JunkUserPurge::WEB_BATCH))
                && str_contains($msg, '3 suspicious accounts remain');
        });

    expect(JunkUserPurge::query()->count())->toBe(3);
});

it('is admin-only', function () use ($junk) {
    $row = $junk('graph.org/x');
    $plain = User::factory()->create();
    $plain->assignRole('user');

    $this->actingAs($plain)
        ->post(route('users.bulk-destroy'), ['mode' => 'ids', 'ids' => [$row->id]])
        ->assertForbidden();

    expect(User::whereKey($row->id)->exists())->toBeTrue();
});

it('has a console command whose dry run deletes nothing', function () use ($payload, $junk) {
    $junk($payload);
    $junk('graph.org/two');

    $this->artisan('users:purge-suspicious', ['--dry-run' => true])
        ->expectsOutputToContain('2 suspicious accounts')
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(JunkUserPurge::query()->count())->toBe(2);

    $this->artisan('users:purge-suspicious')
        ->expectsOutputToContain('Deleted 2 users')
        ->assertSuccessful();

    expect(JunkUserPurge::query()->count())->toBe(0);
});
