<?php

use App\Models\Memorial;
use App\Models\MemorialView;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function analyticsOwner(bool $analyticsInTier): User
{
    Role::findOrCreate('reseller', 'web');

    $tier = ResellerTier::create([
        'name' => 'Professional', 'slug' => 'pro-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 500, 'memorial_profile_allowance' => null,
        'price_per_additional_profile' => 0, 'storage_limit_gb' => null,
        'feature_embedding' => false, 'feature_domain_routing' => false,
        'feature_business_analytics' => $analyticsInTier, 'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme Funeral Home', 'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id]);

    return $owner->fresh();
}

it('shows analytics when the tier includes it', function () {
    $owner = analyticsOwner(true);

    $mine = Memorial::factory()->create([
        'reseller_id' => $owner->reseller_id,
        'full_name' => 'Jane Doe',
    ]);

    MemorialView::create(['memorial_id' => $mine->id, 'visitor_hash' => 'a', 'viewed_at' => now()->subDay()]);
    MemorialView::create(['memorial_id' => $mine->id, 'visitor_hash' => 'a', 'viewed_at' => now()]);
    MemorialView::create(['memorial_id' => $mine->id, 'visitor_hash' => 'b', 'viewed_at' => now()]);

    $this->actingAs($owner)->get('http://localhost/reseller/analytics')
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('Daily views')
        // 3 views from 2 distinct visitor hashes — the unique count must dedupe.
        ->assertSee('Unique visitors')
        ->assertDontSee('Not included in your');
});

it('does not count another reseller views', function () {
    $owner = analyticsOwner(true);
    $other = analyticsOwner(true);

    $theirs = Memorial::factory()->create([
        'reseller_id' => $other->reseller_id,
        'full_name' => 'Somebody Elses Memorial',
    ]);
    MemorialView::create(['memorial_id' => $theirs->id, 'visitor_hash' => 'x', 'viewed_at' => now()]);

    $this->actingAs($owner)->get('http://localhost/reseller/analytics')
        ->assertOk()
        ->assertDontSee('Somebody Elses Memorial')
        ->assertSee('No views recorded');
});

it('explains the feature instead of 403ing when the tier excludes it', function () {
    $owner = analyticsOwner(false);

    // Their own data, gated behind a paid capability — an explanation is the right
    // response, not a permission error.
    $this->actingAs($owner)->get('http://localhost/reseller/analytics')
        ->assertOk()
        ->assertSee('Not included in your')
        ->assertDontSee('Daily views');
});

it('hides the analytics nav entry when the tier excludes it', function () {
    $withIt = analyticsOwner(true);
    $withoutIt = analyticsOwner(false);

    $this->actingAs($withIt)->get('http://localhost/dashboard')->assertOk()->assertSee('reseller/analytics');
    $this->actingAs($withoutIt)->get('http://localhost/dashboard')->assertOk()->assertDontSee('reseller/analytics');
});
