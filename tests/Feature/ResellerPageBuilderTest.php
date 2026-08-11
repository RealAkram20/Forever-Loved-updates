<?php

use App\Models\Memorial;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** A reseller owner on a tier with the given entitlements (page builder off unless asked). */
function pageBuilderReseller(array $tierAttributes = []): User
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create(array_merge([
        'name' => 'Starter',
        'slug' => 'tier-'.substr(uniqid(), -8),
        'sort_order' => 0,
        'annual_price' => 199,
        'memorial_profile_allowance' => 50,
        'price_per_additional_profile' => 5,
        'feature_page_builder' => false,
        'is_active' => true,
    ], $tierAttributes));

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Acme Funeral Home',
        'slug' => 'acme-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $owner->fresh();
}

function headingLayout(string $text): array
{
    return ['version' => 1, 'widgets' => [['type' => 'heading', 'props' => ['text' => $text, 'level' => 2]]]];
}

it('locks the page builder for a tier without the feature', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => false]);

    // The index is reachable but shows the pitch, not the editor.
    $this->actingAs($owner)->get('http://localhost/reseller/pages')
        ->assertOk()
        ->assertSee('Not included in your');

    // Every write path is forbidden outright.
    $this->actingAs($owner)->get('http://localhost/reseller/pages/create')->assertForbidden();
    $this->actingAs($owner)->postJson('http://localhost/reseller/pages', [
        'slug' => 'about',
        'title' => 'About',
        'layout' => ['version' => 1, 'widgets' => []],
    ])->assertForbidden();
});

it('does not show the Pages nav item without the feature', function () {
    $without = pageBuilderReseller(['feature_page_builder' => false]);
    $this->actingAs($without)->get('http://localhost/dashboard')
        ->assertOk()
        ->assertDontSee('/reseller/pages');

    $with = pageBuilderReseller(['feature_page_builder' => true]);
    $this->actingAs($with)->get('http://localhost/dashboard')
        ->assertOk()
        ->assertSee('/reseller/pages');
});

it('renders the builder screens for an entitled reseller', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);

    $this->actingAs($owner)->get('http://localhost/reseller/pages')
        ->assertOk()->assertSee('Add page');

    $this->actingAs($owner)->get('http://localhost/reseller/pages/create')
        ->assertOk()->assertSee('__PAGE_BUILDER__', false);

    $page = Page::create([
        'reseller_id' => $owner->reseller_id,
        'slug' => 'story',
        'title' => 'Our Story',
        'layout' => headingLayout('Our story'),
        'is_published' => true,
    ]);

    $this->actingAs($owner)->get('http://localhost/reseller/pages/'.$page->slug.'/edit')
        ->assertOk()->assertSee('Our Story');
});

it('creates a reseller page scoped to the reseller', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);

    $this->actingAs($owner)->postJson('http://localhost/reseller/pages', [
        'slug' => 'our-services',
        'title' => 'Our Services',
        'is_published' => true,
        'layout' => headingLayout('What we offer'),
    ])->assertOk()->assertJson(['success' => true]);

    $page = Page::where('slug', 'our-services')->first();
    expect($page)->not->toBeNull()
        ->and($page->reseller_id)->toBe($owner->reseller_id)
        ->and($page->is_published)->toBeTrue();
});

it('rejects a page slug that collides with one of the reseller memorials', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);
    Memorial::factory()->create(['reseller_id' => $owner->reseller_id, 'slug' => 'jane-doe']);

    $this->actingAs($owner)->postJson('http://localhost/reseller/pages', [
        'slug' => 'jane-doe',
        'title' => 'Jane',
        'layout' => ['version' => 1, 'widgets' => []],
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

it('lets two resellers each own a page with the same slug', function () {
    $a = pageBuilderReseller(['feature_page_builder' => true]);
    $b = pageBuilderReseller(['feature_page_builder' => true]);

    foreach ([$a, $b] as $owner) {
        $this->actingAs($owner)->postJson('http://localhost/reseller/pages', [
            'slug' => 'our-team',
            'title' => 'Our Team',
            'layout' => ['version' => 1, 'widgets' => []],
        ])->assertOk();
    }

    expect(Page::where('slug', 'our-team')->count())->toBe(2);
});

it('will not let a reseller edit another reseller page', function () {
    $a = pageBuilderReseller(['feature_page_builder' => true]);
    $b = pageBuilderReseller(['feature_page_builder' => true]);

    $page = Page::create([
        'reseller_id' => $b->reseller_id,
        'slug' => 'secret',
        'title' => 'Secret',
        'layout' => headingLayout('Hidden'),
        'is_published' => true,
    ]);

    $this->actingAs($a)->get('http://localhost/reseller/pages/'.$page->slug.'/edit')->assertNotFound();
});

it('serves a published reseller page on the reseller host, scoped to that reseller', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);
    $reseller = $owner->reseller;

    Page::create([
        'reseller_id' => $reseller->id,
        'slug' => 'welcome',
        'title' => 'Welcome',
        'layout' => headingLayout('Welcome to Acme'),
        'is_published' => true,
    ]);

    // The path-based tenant route works in every environment (no host routing needed).
    $this->get('http://localhost/r/'.$reseller->slug.'/welcome')
        ->assertOk()
        ->assertSee('Welcome to Acme');

    // A different reseller's host must not serve it.
    $other = pageBuilderReseller(['feature_page_builder' => true])->reseller;
    $this->get('http://localhost/r/'.$other->slug.'/welcome')->assertNotFound();
});

it('does not serve an unpublished reseller page', function () {
    $reseller = pageBuilderReseller(['feature_page_builder' => true])->reseller;

    Page::create([
        'reseller_id' => $reseller->id,
        'slug' => 'draft',
        'title' => 'Draft',
        'layout' => headingLayout('Not ready'),
        'is_published' => false,
    ]);

    $this->get('http://localhost/r/'.$reseller->slug.'/draft')->assertNotFound();
});

// ── Homepage editing ───────────────────────────────────────────────────────────────────

it('opens the homepage editor with a layout to edit', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);

    // The row exists from provisioning, but with no layout — their front page renders the
    // shared branded default until they build one. (Seeding from the platform's layout, when
    // the platform has one, is covered by the next test.)
    $before = Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)->first();
    expect($before)->not->toBeNull()->and($before->hasLayout())->toBeFalse();

    $this->actingAs($owner)->get('http://localhost/reseller/pages/homepage')
        ->assertOk()->assertSee('__PAGE_BUILDER__', false);

    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)->exists())->toBeTrue();
});

it('seeds the homepage from the platform default layout on first open', function () {
    // The platform's default home is itself a widget layout.
    Page::create([
        'reseller_id' => null,
        'slug' => Page::SLUG_VISITOR_HOME,
        'title' => 'Home',
        'layout' => headingLayout('Platform welcome'),
        'is_published' => true,
    ]);

    $owner = pageBuilderReseller(['feature_page_builder' => true]);

    $this->actingAs($owner)->get('http://localhost/reseller/pages/homepage')->assertOk();

    $home = Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)->first();
    expect($home)->not->toBeNull()
        ->and($home->layout['widgets'])->toHaveCount(1)
        ->and($home->layout['widgets'][0]['props']['text'])->toBe('Platform welcome');

    // The reseller's copy gets its own fresh widget id.
    expect($home->layout['widgets'][0]['id'] ?? '')->toMatch('/^w_[a-z0-9]+$/');
});

it('renders a reseller custom homepage layout on their front page', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);
    $reseller = $owner->reseller;

    // Provisioned on creation now, so this sets the layout rather than creating the row.
    Page::where('reseller_id', $reseller->id)->where('slug', Page::SLUG_VISITOR_HOME)
        ->update(['layout' => headingLayout('Welcome to our funeral home')]);
    Page::clearSlugCache(Page::SLUG_VISITOR_HOME, $reseller->id);

    $this->get('http://localhost/r/'.$reseller->slug)
        ->assertOk()
        ->assertSee('Welcome to our funeral home');
});

it('will not delete or re-slug the homepage', function () {
    $owner = pageBuilderReseller(['feature_page_builder' => true]);

    Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)
        ->update(['layout' => headingLayout('Home')]);

    // Delete is refused; the page survives.
    $this->actingAs($owner)->delete('http://localhost/reseller/pages/'.Page::SLUG_VISITOR_HOME)
        ->assertRedirect(route('reseller.pages.index'));
    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)->exists())->toBeTrue();

    // A slug change in the details panel is ignored — it stays the home slug.
    $this->actingAs($owner)->post('http://localhost/reseller/pages/'.Page::SLUG_VISITOR_HOME.'/meta', [
        'title' => 'Home',
        'slug' => 'renamed-home',
        'is_published' => true,
    ])->assertOk();

    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', Page::SLUG_VISITOR_HOME)->exists())->toBeTrue()
        ->and(Page::where('reseller_id', $owner->reseller_id)->where('slug', 'renamed-home')->exists())->toBeFalse();
});

// ── Super-admin impersonation keeps access even when the tier lacks the feature ───────────

it('lets a super-admin logged in as the reseller use the builder despite the tier', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    // Reseller on a tier WITHOUT the page builder.
    $owner = pageBuilderReseller(['feature_page_builder' => false]);

    // Without impersonation: locked and forbidden.
    $this->actingAs($owner)->get('http://localhost/reseller/pages')
        ->assertOk()->assertSee('Not included in your');
    $this->actingAs($owner)->get('http://localhost/reseller/pages/create')->assertForbidden();

    // Logged in AS the reseller (impersonator_id set by the super-admin loginAs flow): full access.
    $this->actingAs($owner)->withSession(['impersonator_id' => $admin->id])
        ->get('http://localhost/reseller/pages')->assertOk()->assertSee('Add page');

    $this->actingAs($owner)->withSession(['impersonator_id' => $admin->id])
        ->get('http://localhost/reseller/pages/create')->assertOk();

    $this->actingAs($owner)->withSession(['impersonator_id' => $admin->id])
        ->get('http://localhost/reseller/pages/homepage')->assertOk();
});

it('does not grant builder access when the impersonator is not a super-admin', function () {
    // A plain user id in the session must not unlock a locked tier.
    $plain = User::factory()->create();
    $owner = pageBuilderReseller(['feature_page_builder' => false]);

    $this->actingAs($owner)->withSession(['impersonator_id' => $plain->id])
        ->get('http://localhost/reseller/pages/create')->assertForbidden();
});

it('does not serve a reseller page on the platform host', function () {
    $reseller = pageBuilderReseller(['feature_page_builder' => true])->reseller;

    Page::create([
        'reseller_id' => $reseller->id,
        'slug' => 'reseller-only',
        'title' => 'Reseller Only',
        'layout' => headingLayout('Tenant page'),
        'is_published' => true,
    ]);

    // On the platform's own host /{slug} is a platform CMS page or a memorial — never a
    // reseller's tenant page.
    $this->get('http://localhost/reseller-only')->assertNotFound();
});
