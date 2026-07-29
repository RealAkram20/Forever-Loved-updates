<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
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
    $this->actingAs($this->admin)
        ->get('http://localhost/settings/resellers')
        ->assertOk()
        ->assertSee('Acme Funeral Home')
        ->assertSee('acme.'.config('reseller.domain'))
        ->assertSee('New Reseller');
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
