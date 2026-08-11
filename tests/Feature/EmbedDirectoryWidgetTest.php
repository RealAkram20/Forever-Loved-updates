<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The embeddable directory: /widget/directory renders the Find a Memorial experience
 * chrome-less for an iframe; /widget/directory/results is its JSON. Whose memorials
 * appear follows the serving origin, exactly like the full directory page.
 */
function embedTenant(bool $embedding = true): Reseller
{
    Role::findOrCreate('reseller', 'web');
    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $tier = ResellerTier::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'feature_embedding' => $embedding, 'sort_order' => 1]);

    $reseller = Reseller::create([
        'name' => 'Acme', 'slug' => 'acme', 'status' => Reseller::STATUS_ACTIVE,
        'owner_user_id' => $owner->id, 'reseller_tier_id' => $tier->id,
        'custom_domain' => 'kangaruride.com', 'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
    ]);
    $owner->update(['reseller_id' => $reseller->id]);

    return $reseller;
}

function embedPublicMemorial(array $attrs = []): Memorial
{
    return Memorial::factory()->create(array_merge([
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ], $attrs));
}

it('renders the all-mode directory with search, and picked mode without', function () {
    embedPublicMemorial();

    $all = $this->get('/widget/directory?memorials=all')->assertOk()->getContent();
    expect($all)->toContain('<input type="search"');

    $picked = $this->get('/widget/directory?memorials=some-slug,another-slug')->assertOk()->getContent();
    expect($picked)->not->toContain('<input type="search"')
        ->and($picked)->toContain('data-slugs="some-slug,another-slug"');
});

it('serves only the platform catalogue on the platform origin', function () {
    $acme = embedTenant();
    embedPublicMemorial(['slug' => 'platform-person']);
    embedPublicMemorial(['slug' => 'acme-person', 'reseller_id' => $acme->id]);

    $data = $this->getJson('/widget/directory/results')->assertOk()->json('data');

    expect(collect($data)->pluck('slug'))->toContain('platform-person')
        ->not->toContain('acme-person');
});

it('serves only the reseller catalogue on their origin, slugs filter included', function () {
    $acme = embedTenant();
    embedPublicMemorial(['slug' => 'platform-person']);
    embedPublicMemorial(['slug' => 'acme-person', 'reseller_id' => $acme->id]);
    embedPublicMemorial(['slug' => 'acme-second', 'reseller_id' => $acme->id]);

    $data = $this->getJson('http://kangaruride.com/widget/directory/results')->assertOk()->json('data');
    expect(collect($data)->pluck('slug'))->toContain('acme-person', 'acme-second')
        ->not->toContain('platform-person');

    // A curated set cannot smuggle in a foreign slug: the tenant clause wins.
    $picked = $this->getJson('http://kangaruride.com/widget/directory/results?slugs=acme-person,platform-person')
        ->assertOk()->json('data');
    expect(collect($picked)->pluck('slug'))->toContain('acme-person')->not->toContain('platform-person');
});

it('refuses a reseller origin whose tier lacks embedding', function () {
    embedTenant(embedding: false);

    $this->get('http://kangaruride.com/widget/directory?memorials=all')->assertForbidden();
    $this->getJson('http://kangaruride.com/widget/directory/results')->assertForbidden();
});

it('paginates', function () {
    Memorial::factory()->count(15)->create(['is_public' => true, 'status' => Memorial::STATUS_ACTIVE]);

    $meta = $this->getJson('/widget/directory/results?per_page=6')->assertOk()->json('meta');

    expect($meta['per_page'])->toBe(6)
        ->and($meta['last_page'])->toBeGreaterThanOrEqual(3);
});

it('serves the snippet builder to reseller staff with tenant-scoped search', function () {
    $acme = embedTenant();
    embedPublicMemorial(['slug' => 'acme-person', 'full_name' => 'Acme Person', 'reseller_id' => $acme->id]);
    embedPublicMemorial(['slug' => 'platform-person', 'full_name' => 'Platform Person']);

    $owner = $acme->owner;

    $this->actingAs($owner)->get('http://localhost/reseller/embed')
        ->assertOk()
        ->assertSee('Embed on your website');

    $data = $this->actingAs($owner)->getJson('http://localhost/reseller/embed/search?q=person')->assertOk()->json('data');
    expect(collect($data)->pluck('slug'))->toContain('acme-person')->not->toContain('platform-person');
});
