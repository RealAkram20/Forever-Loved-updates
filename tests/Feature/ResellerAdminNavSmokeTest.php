<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('super-admin', 'web');
    Role::findOrCreate('reseller', 'web');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->tier = ResellerTier::create([
        'name' => 'Professional',
        'slug' => 'professional',
        'sort_order' => 0,
        'annual_price' => 499,
        'memorial_profile_allowance' => 50,
        'price_per_additional_profile' => 9.99,
        'storage_limit_gb' => 25,
        'feature_embedding' => true,
        'feature_domain_routing' => true,
        'feature_business_analytics' => false,
        'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $this->reseller = Reseller::create([
        'name' => 'Acme Funeral Home',
        'slug' => 'acme',
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $this->tier->id,
        'status' => Reseller::STATUS_ACTIVE,
        'custom_domain' => 'memorials.acme.test',
        'custom_domain_status' => Reseller::DOMAIN_UNVERIFIED,
    ]);
    $owner->update(['reseller_id' => $this->reseller->id, 'original_reseller_id' => $this->reseller->id]);
});

it('renders the reseller roster', function () {
    // .env's APP_URL is a localhost subdirectory, so no {slug}.{base} address can be served
    // here. The roster must show the address that works and say the rest is temporary —
    // printing acme.foreverloved.com would be handing an admin a dead URL to pass on.
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/resellers')
        ->assertOk()
        ->assertSee('Acme Funeral Home')
        ->assertSee('r/acme')
        ->assertSee('Subdomain routing is not available in this environment')
        ->assertSee('New Reseller');
});

it('shows real subdomain addresses on the roster once deployed correctly', function () {
    config(['app.url' => 'https://'.config('reseller.domain')]);

    $this->actingAs($this->admin)
        ->get('http://localhost/settings/resellers')
        ->assertOk()
        ->assertSee('acme.'.config('reseller.domain'))
        ->assertDontSee('Subdomain routing is not available in this environment');
});

it('renders the per-reseller detail page', function () {
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/resellers/'.$this->reseller->id)
        ->assertOk()
        ->assertSee('Acme Funeral Home')
        ->assertSee('Partnership')
        ->assertSee('memorials.acme.test');
});

it('renders the pricing page with tiers', function () {
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/reseller-pricing')
        ->assertOk()
        ->assertSee('Professional')
        ->assertSee('Embeddable memorial widget');
});

it('renders the reseller settings page', function () {
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/reseller-settings')
        ->assertOk()
        ->assertSee('Additional reserved subdomains')
        ->assertSee(config('reseller.domain'));
});

it('redirects the retired custom-domains url to reseller settings', function () {
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/domains')
        ->assertRedirect(route('settings.reseller-settings'));
});

it('saves program settings and applies extra reserved slugs', function () {
    $this->actingAs($this->admin)
        ->put('http://localhost/settings/reseller-settings', [
            'custom_domains_enabled' => '1',
            'target_host' => 'edge.example.test',
            'default_tier_id' => $this->tier->id,
            'reserved_slugs' => 'Blog, status  help',
        ])
        ->assertRedirect();

    expect(Reseller::reservedSlugs())->toContain('blog', 'status', 'help', 'www');

    // A newly created reseller picks up the configured default tier.
    $this->actingAs($this->admin)->post('http://localhost/settings/resellers', [
        'name' => 'Beta Chapel',
        'slug' => 'beta',
        'reseller_tier_id' => '',
        'owner_name' => 'Beta Owner',
        'owner_email' => 'beta@example.test',
    ])->assertRedirect();

    expect(Reseller::where('slug', 'beta')->first()->reseller_tier_id)->toBe($this->tier->id);
});

it('scopes the users list to one reseller', function () {
    $direct = User::factory()->create(['name' => 'Direct Customer']);
    $staff = User::factory()->create(['name' => 'Acme Staffer', 'reseller_id' => $this->reseller->id]);

    // Unfiltered: both are visible, each tagged with its owner.
    $this->actingAs($this->admin)->get('http://localhost/users')
        ->assertOk()
        ->assertSee('Direct Customer')
        ->assertSee('Acme Staffer')
        ->assertSee('Acme Funeral Home');

    $this->actingAs($this->admin)->get('http://localhost/users?reseller='.$this->reseller->id)
        ->assertOk()
        ->assertSee('Acme Staffer')
        ->assertDontSee('Direct Customer');

    $this->actingAs($this->admin)->get('http://localhost/users?reseller=direct')
        ->assertOk()
        ->assertSee('Direct Customer')
        ->assertDontSee('Acme Staffer');
});

it('defaults the plans list to platform-owned plans', function () {
    $platformPlan = SubscriptionPlan::create([
        'name' => 'Platform Premium', 'slug' => 'platform-premium', 'price' => 20, 'interval' => 'monthly',
        'memorial_limit' => 5, 'storage_limit_mb' => 500, 'sort_order' => 1, 'is_active' => true,
    ]);
    SubscriptionPlan::create([
        'name' => 'Acme Package', 'slug' => 'acme-package', 'price' => 30, 'interval' => 'monthly',
        'memorial_limit' => 5, 'storage_limit_mb' => 500, 'sort_order' => 2, 'is_active' => true,
        'reseller_id' => $this->reseller->id,
    ]);

    // No param: the reseller's own client-facing plans must not bleed into the
    // platform's list, since an admin does not manage them from here.
    $this->actingAs($this->admin)->get('http://localhost/settings/plans')
        ->assertOk()
        ->assertSee('Platform Premium')
        ->assertDontSee('Acme Package');

    $this->actingAs($this->admin)->get('http://localhost/settings/plans?reseller='.$this->reseller->id)
        ->assertOk()
        ->assertSee('Acme Package')
        ->assertDontSee('Platform Premium');

    expect($platformPlan->reseller_id)->toBeNull();
});

it('tags memorials and payment orders with their owning reseller', function () {
    Memorial::factory()->create([
        'full_name' => 'Jane Doe',
        'reseller_id' => $this->reseller->id,
    ]);

    $this->actingAs($this->admin)->get('http://localhost/memorials')
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('Acme Funeral Home');

    $this->actingAs($this->admin)->get('http://localhost/settings/payment-orders')
        ->assertOk()
        ->assertSee('Owner');
});

it('rejects a reseller slug that an admin has reserved', function () {
    $this->actingAs($this->admin)->put('http://localhost/settings/reseller-settings', [
        'custom_domains_enabled' => '0',
        'reserved_slugs' => 'blog',
    ]);

    $this->actingAs($this->admin)->post('http://localhost/settings/resellers', [
        'name' => 'Blog Co',
        'slug' => 'blog',
        'owner_name' => 'Blog Owner',
        'owner_email' => 'blog@example.test',
    ])->assertSessionHasErrors('slug');
});
