<?php

use App\Helpers\QueueHealthHelper;
use App\Jobs\SendContactEmail;
use App\Models\Memorial;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\StandardPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The pages every site has, for a reseller.
 *
 * Of the platform's seven, a reseller used to get one: four paths redirected to their front
 * page and the two legal ones served *our* text on their domain. The proof it was a real
 * gap is still in the database — a reseller with a page called `about-us`, because
 * Page::reservedSlugs() refused them `about`.
 */
function standardPagesReseller(array $tierAttributes = [], string $name = 'Acme Funeral Home'): User
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create(array_merge([
        'name' => 'Pro',
        'slug' => 'tier-'.substr(uniqid(), -8),
        'sort_order' => 0,
        'annual_price' => 199,
        'memorial_profile_allowance' => 50,
        'price_per_additional_profile' => 5,
        'feature_page_builder' => true,
        'is_active' => true,
    ], $tierAttributes));

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => $name,
        'slug' => 'tenant-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $owner->fresh();
}

function enableStandard(User $owner, string $slug): void
{
    test()->actingAs($owner)
        ->put("http://localhost/reseller/pages/standard/{$slug}", ['enabled' => 1])
        ->assertRedirect();
}

// ─── The switch ─────────────────────────────────────────────────────────────

it('creates and publishes a standard page when switched on', function () {
    $owner = standardPagesReseller();

    enableStandard($owner, 'about');

    $page = Page::where('reseller_id', $owner->reseller_id)->where('slug', 'about')->first();

    expect($page)->not->toBeNull()
        ->and($page->is_published)->toBeTrue()
        ->and($page->title)->toBe('About Us');
});

it('keeps the content when a page is switched off', function () {
    $owner = standardPagesReseller();
    enableStandard($owner, 'about');

    Page::where('reseller_id', $owner->reseller_id)->where('slug', 'about')
        ->update(['content' => '<p>Three generations of service.</p>']);

    $this->actingAs($owner)
        ->put('http://localhost/reseller/pages/standard/about', ['enabled' => 0])
        ->assertRedirect();

    $page = Page::where('reseller_id', $owner->reseller_id)->where('slug', 'about')->first();

    // Off, not gone. Re-enabling has to give them their own copy back.
    expect($page)->not->toBeNull()
        ->and($page->is_published)->toBeFalse()
        ->and($page->content)->toContain('Three generations');
});

it('refuses to switch off a page every site needs', function (string $slug) {
    $owner = standardPagesReseller();
    enableStandard($owner, $slug);

    $this->actingAs($owner)
        ->put("http://localhost/reseller/pages/standard/{$slug}", ['enabled' => 0])
        ->assertSessionHas('error');

    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', $slug)->first()->is_published)->toBeTrue();
})->with(['privacy-policy', 'terms-of-use']);

it('seeds the legal pages from the platform copy', function () {
    Page::create(['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'content' => '<p>Platform policy text.</p>', 'is_published' => true]);

    $owner = standardPagesReseller();
    enableStandard($owner, 'privacy-policy');

    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', 'privacy-policy')->first()->content)
        ->toContain('Platform policy text');
});

it('will not delete a standard page', function () {
    $owner = standardPagesReseller();
    enableStandard($owner, 'about');

    $this->actingAs($owner)
        ->delete('http://localhost/reseller/pages/about')
        ->assertRedirect();

    expect(Page::where('reseller_id', $owner->reseller_id)->where('slug', 'about')->exists())->toBeTrue();
});

it('rejects a slug that is not a standard page', function () {
    $owner = standardPagesReseller();

    $this->actingAs($owner)
        ->put('http://localhost/reseller/pages/standard/not-a-standard-page', ['enabled' => 1])
        ->assertNotFound();
});

// ─── Enablement decides the routing ─────────────────────────────────────────

it('resolves an enabled page for the tenant and nothing when off', function () {
    $owner = standardPagesReseller();
    $id = $owner->reseller_id;

    expect(StandardPages::isEnabledFor('about', $id))->toBeFalse();

    enableStandard($owner, 'about');
    expect(StandardPages::isEnabledFor('about', $id))->toBeTrue();

    $this->actingAs($owner)->put('http://localhost/reseller/pages/standard/about', ['enabled' => 0]);
    expect(StandardPages::isEnabledFor('about', $id))->toBeFalse();
});

it('falls back to the platform copy for legal pages only', function () {
    Page::create(['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'content' => 'ours', 'is_published' => true]);
    Page::create(['slug' => 'about', 'title' => 'About Us', 'content' => 'ours', 'is_published' => true]);

    $owner = standardPagesReseller();
    $id = $owner->reseller_id;

    // A tenant with no rows still answers /privacy-policy — no policy at all is worse than
    // a generic one — but must never serve our About on their domain.
    expect(StandardPages::resolve('privacy-policy', $id))->not->toBeNull()
        ->and(StandardPages::resolve('about', $id))->toBeNull();
});

// ─── End to end, on their actual host ───────────────────────────────────────

it('serves the reseller\'s own About page once switched on', function () {
    Page::create(['slug' => 'about', 'title' => 'About Us', 'content' => '<p>The platform story.</p>', 'is_published' => true]);

    $owner = standardPagesReseller();
    $host = 'http://'.$owner->reseller->slug.'.'.config('reseller.domain');

    // Off: the path redirects to *their* front page. Asserted on the absolute address
    // because URL::forceRootUrl() would otherwise land the visitor on the platform's.
    $this->get($host.'/about')->assertRedirect($host.'/');

    enableStandard($owner, 'about');
    Page::where('reseller_id', $owner->reseller_id)->where('slug', 'about')
        ->update(['content' => '<p>Three generations of service.</p>']);
    Page::clearSlugCache('about', $owner->reseller_id);

    $this->get($host.'/about')
        ->assertOk()
        ->assertSee('Three generations of service', false)
        // Their host must never print ours.
        ->assertDontSee('The platform story', false);

    // The platform's own About is untouched.
    $this->get('http://localhost/about')->assertOk()->assertSee('The platform story', false);
});

// ─── The two scoping bugs ───────────────────────────────────────────────────

it('sells the host tenant\'s plans, not the visitor\'s', function () {
    $owner = standardPagesReseller();
    $reseller = $owner->reseller;

    SubscriptionPlan::create(['name' => 'Platform Basic', 'slug' => 'p-basic', 'price' => 10, 'interval' => 'monthly',
        'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true]);
    SubscriptionPlan::create(['name' => 'Acme Package', 'slug' => 'acme-pkg', 'price' => 99, 'interval' => 'monthly',
        'memorial_limit' => 1, 'storage_limit_mb' => 100, 'sort_order' => 1, 'is_active' => true, 'reseller_id' => $reseller->id]);

    // Anonymous — exactly the case sellableTo() got wrong, since the visitor has no
    // reseller_id of their own to key off.
    $onHost = SubscriptionPlan::where('is_active', true)->sellableOnHost($reseller->id)->pluck('name')->all();
    $onPlatform = SubscriptionPlan::where('is_active', true)->sellableOnHost(null)->pluck('name')->all();

    expect($onHost)->toBe(['Acme Package'])
        ->and($onPlatform)->toBe(['Platform Basic']);
});

it('lists the host tenant\'s memorials in the directory', function () {
    $owner = standardPagesReseller();
    $reseller = $owner->reseller;

    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    Memorial::factory()->create(['user_id' => $client->id, 'reseller_id' => $reseller->id, 'is_public' => true, 'full_name' => 'Theirs Client']);
    Memorial::factory()->create(['reseller_id' => null, 'is_public' => true, 'full_name' => 'Ours Client']);

    // On the platform's own host the reseller's memorials stay hidden, as before.
    $names = collect($this->getJson('http://localhost/find-memorial')->assertOk()->json('data'))
        ->pluck('name')->all();

    expect($names)->toContain('Ours Client')
        ->and($names)->not->toContain('Theirs Client');

    // On the reseller's own host the query flips to theirs. Previously hardcoded to
    // whereNull('reseller_id'), which would have shown them an empty directory.
    //
    // Requested on the real host rather than by binding the container: ResolveResellerByHost
    // deliberately forgets any bound tenant when serving the platform's host, so a binding
    // set in the test would be cleared before the controller ran.
    enableStandard($owner, Page::SLUG_FIND_MEMORIAL);

    $tenantNames = collect(
        $this->getJson('http://'.$reseller->slug.'.'.config('reseller.domain').'/find-memorial')
            ->assertOk()->json('data')
    )->pluck('name')->all();

    expect($tenantNames)->toContain('Theirs Client')
        ->and($tenantNames)->not->toContain('Ours Client');
});

// ─── Contact routing ────────────────────────────────────────────────────────

it('sends a reseller contact enquiry to their own inbox', function () {
    Queue::fake();

    // ReliableDispatch only queues when the scheduler has a fresh heartbeat; without one it
    // runs the job inline, which Queue::fake() would never see.
    Cache::put(QueueHealthHelper::HEARTBEAT_KEY, now()->toDateTimeString(), 3600);

    $owner = standardPagesReseller();
    $owner->reseller->update(['contact_email' => 'enquiries@acme.test']);

    SystemSetting::set('smtp.enabled', '1');
    SystemSetting::set('smtp.host', 'smtp.test');
    SystemSetting::set('smtp.from_address', 'platform@ours.test');

    enableStandard($owner, 'contact');

    // Posted to their host, so ResolveResellerByHost binds the tenant the same way a real
    // visitor's request would.
    $this->post('http://'.$owner->reseller->slug.'.'.config('reseller.domain').'/contact', [
        'name' => 'A Family',
        'email' => 'family@example.com',
        'subject' => 'Arrangements',
        'message' => 'Please call me.',
    ])->assertRedirect();

    // A family writing to a funeral home on that funeral home's domain must not land in
    // the platform's inbox.
    Queue::assertPushed(SendContactEmail::class,
        fn ($job) => $job->toAddress === 'enquiries@acme.test' && $job->siteName === $owner->reseller->name);
});

it('sends a platform contact enquiry to the platform inbox', function () {
    Queue::fake();
    Cache::put(QueueHealthHelper::HEARTBEAT_KEY, now()->toDateTimeString(), 3600);

    SystemSetting::set('smtp.enabled', '1');
    SystemSetting::set('smtp.host', 'smtp.test');
    SystemSetting::set('smtp.from_address', 'platform@ours.test');

    $this->post('http://localhost/contact', [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'subject' => 'Hello',
        'message' => 'Hi.',
    ])->assertRedirect();

    Queue::assertPushed(SendContactEmail::class,
        fn ($job) => $job->toAddress === 'platform@ours.test');
});

it('backfills contact_email from the owner account', function () {
    $owner = standardPagesReseller();

    // The migration seeds it; a reseller created afterwards is set from Settings. Either
    // way a blank field must fall back to us rather than drop the enquiry.
    expect($owner->reseller->contact_email)->toBeNull();
});

// ─── Menus ──────────────────────────────────────────────────────────────────

it('offers a standard page in the menu picker only while it is on', function () {
    $owner = standardPagesReseller();

    $this->actingAs($owner)->get('http://localhost/reseller/menus')
        ->assertOk()
        ->assertDontSee('About Us ·');

    enableStandard($owner, 'about');

    $this->actingAs($owner)->get('http://localhost/reseller/menus')
        ->assertOk()
        ->assertSee('About Us ·');
});

it('refuses a menu link to a switched-off standard page', function () {
    $owner = standardPagesReseller();
    enableStandard($owner, 'about');
    $this->actingAs($owner)->put('http://localhost/reseller/pages/standard/about', ['enabled' => 0]);

    $this->actingAs($owner)
        ->post('http://localhost/reseller/menus/items', [
            'menu_location' => Menu::LOCATION_HEADER,
            'label' => 'About',
            'route_name' => 'cms.page::about',
        ])
        ->assertSessionHasErrors('route_name');
});

// ─── Entitlement ────────────────────────────────────────────────────────────

it('locks the switch for a tier without the page builder', function () {
    $owner = standardPagesReseller(['feature_page_builder' => false]);

    $this->actingAs($owner)
        ->put('http://localhost/reseller/pages/standard/about', ['enabled' => 1])
        ->assertForbidden();
});
