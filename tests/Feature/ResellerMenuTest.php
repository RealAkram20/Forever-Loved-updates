<?php

use App\Helpers\MenuHelper;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A reseller's own navigation.
 *
 * Menus were unique per location across the whole table, so the only ones that existed were
 * the platform's, and the view composer withheld those on a reseller host rather than put our
 * About and Pricing on their domain. A white-labeled site therefore had a one-item header and
 * no screen anywhere that could change it. These cover the tenant split that fixes it, and
 * the leak it must not reintroduce.
 */
function menuReseller(array $tierAttributes = [], string $name = 'Acme Funeral Home'): User
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

/** The platform's own header menu, with one item pointing at our marketing. */
function platformHeaderMenu(): Menu
{
    $menu = Menu::create(['location' => Menu::LOCATION_HEADER, 'reseller_id' => null]);

    MenuItem::create([
        'menu_id' => $menu->id,
        'label' => 'Platform Pricing',
        'route_name' => 'pricing',
        'sort_order' => 0,
    ]);

    return $menu;
}

// ─── The tenant split ───────────────────────────────────────────────────────

it('lets a reseller build their own header menu', function () {
    $owner = menuReseller();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/menus/items', [
            'menu_location' => Menu::LOCATION_HEADER,
            'label' => 'Our services',
            'url' => '/services',
        ])
        ->assertRedirect();

    $menu = Menu::forLocation(Menu::LOCATION_HEADER, $owner->reseller_id);

    // Appended to the default nav they are provisioned with, not replacing it.
    expect($menu)->not->toBeNull()
        ->and($menu->reseller_id)->toBe($owner->reseller_id)
        ->and($menu->rootMenuItems()->pluck('label')->all())->toContain('Our services');
});

it('keeps one reseller out of another reseller\'s menu', function () {
    $acme = menuReseller([], 'Acme');
    $rival = menuReseller([], 'Rival');

    $this->actingAs($rival)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER,
        'label' => 'Rival link',
        'url' => '/rival',
    ]);

    $rivalItem = MenuItem::where('label', 'Rival link')->firstOrFail();

    // Acme knows the id. Editing, deleting and reordering it must all miss.
    $this->actingAs($acme)
        ->put("http://localhost/reseller/menus/items/{$rivalItem->id}", ['label' => 'Hijacked', 'url' => '/x'])
        ->assertNotFound();

    $this->actingAs($acme)
        ->delete("http://localhost/reseller/menus/items/{$rivalItem->id}")
        ->assertNotFound();

    expect($rivalItem->fresh()->label)->toBe('Rival link');
});

it('does not let a reseller reorder items into their own menu', function () {
    $acme = menuReseller([], 'Acme');
    $rival = menuReseller([], 'Rival');

    $this->actingAs($rival)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER, 'label' => 'Rival link', 'url' => '/rival',
    ]);
    $rivalItem = MenuItem::where('label', 'Rival link')->firstOrFail();
    $rivalMenuId = $rivalItem->menu_id;

    $this->actingAs($acme)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER, 'label' => 'Acme link', 'url' => '/acme',
    ]);

    $this->actingAs($acme)->post('http://localhost/reseller/menus/reorder', [
        'menu_location' => Menu::LOCATION_HEADER,
        'item_ids' => [$rivalItem->id],
    ]);

    expect($rivalItem->fresh()->menu_id)->toBe($rivalMenuId);
});

it('gives each tenant its own menu per location', function () {
    $acme = menuReseller([], 'Acme');
    $rival = menuReseller([], 'Rival');

    foreach ([$acme, $rival] as $owner) {
        $this->actingAs($owner)->post('http://localhost/reseller/menus/items', [
            'menu_location' => Menu::LOCATION_HEADER,
            'label' => 'Home',
            'url' => '/',
        ]);
    }

    // Three header menus now coexist where the old unique(location) allowed one.
    platformHeaderMenu();

    expect(Menu::where('location', Menu::LOCATION_HEADER)->count())->toBe(3);
});

// ─── The leak it must not reintroduce ───────────────────────────────────────

it('never serves the platform menu on a reseller site', function () {
    platformHeaderMenu();
    $owner = menuReseller();
    $reseller = $owner->reseller;

    // Their header is their own provisioned default — never our items, whatever it contains.
    $theirs = Menu::navigationFor(Menu::LOCATION_HEADER, $reseller->id);

    expect($theirs->pluck('label')->all())->not->toContain('Platform Pricing')
        ->and($theirs->pluck('route_name')->all())->not->toContain('pricing')
        // ...while the platform's own site still gets its menu.
        ->and(Menu::navigationFor(Menu::LOCATION_HEADER, null)->pluck('label')->all())->toBe(['Platform Pricing']);
});

it('offers a reseller only their own pages as destinations', function () {
    $owner = menuReseller();

    Page::create(['reseller_id' => $owner->reseller_id, 'slug' => 'our-services', 'title' => 'Our Services', 'is_published' => true]);
    Page::create(['reseller_id' => null, 'slug' => 'platform-only', 'title' => 'Platform Only', 'is_published' => true]);

    $this->actingAs($owner)->get('http://localhost/reseller/menus')
        ->assertOk()
        ->assertSee('Our Services')
        ->assertDontSee('Platform Only')
        // Their own About and Pricing *are* offered now — as their pages, addressed through
        // cms.page. What must never appear is a platform route option, which would put a
        // link to our site in their navigation.
        ->assertDontSee('value="pricing"', false)
        ->assertDontSee('value="about"', false);
});

it('refuses a hand-typed platform page as a menu destination', function () {
    $owner = menuReseller();
    Page::create(['reseller_id' => null, 'slug' => 'platform-only', 'title' => 'Platform Only', 'is_published' => true]);

    $this->actingAs($owner)
        ->post('http://localhost/reseller/menus/items', [
            'menu_location' => Menu::LOCATION_HEADER,
            'label' => 'Sneaky',
            'route_name' => 'cms.page::platform-only',
        ])
        ->assertSessionHasErrors('route_name');

    expect(MenuItem::where('label', 'Sneaky')->exists())->toBeFalse();
});

it('refuses a platform marketing route as a menu destination', function () {
    $owner = menuReseller();

    $this->actingAs($owner)
        ->post('http://localhost/reseller/menus/items', [
            'menu_location' => Menu::LOCATION_HEADER,
            'label' => 'Pricing',
            'route_name' => 'pricing',
        ])
        ->assertSessionHasErrors('route_name');
});

// ─── Entitlement ────────────────────────────────────────────────────────────

it('locks menus for a tier without the page builder', function () {
    $owner = menuReseller(['feature_page_builder' => false]);

    $this->actingAs($owner)->get('http://localhost/reseller/menus')->assertForbidden();

    $this->actingAs($owner)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER, 'label' => 'x', 'url' => '/x',
    ])->assertForbidden();
});

it('shows the Menus entry in the sidebar only when unlocked', function () {
    $unlocked = menuReseller(['feature_page_builder' => true]);
    $locked = menuReseller(['feature_page_builder' => false]);

    $this->actingAs($unlocked);
    expect(collect(MenuHelper::getResellerNavItems())->pluck('name'))->toContain('Menus');

    auth()->logout();
    $this->actingAs($locked);
    expect(collect(MenuHelper::getResellerNavItems())->pluck('name'))->not->toContain('Menus');
});

// ─── The platform's own screen ──────────────────────────────────────────────

it('keeps reseller menus out of the admin menu screen', function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super-admin', 'web');

    platformHeaderMenu();
    $owner = menuReseller();

    $this->actingAs($owner)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER,
        'label' => 'Reseller Only Link',
        'url' => '/theirs',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('http://localhost/settings/menus')
        ->assertOk()
        ->assertSee('Pricing')
        ->assertDontSee('Reseller Only Link');
});

it('stops an admin editing a reseller menu item from the platform screen', function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super-admin', 'web');

    $owner = menuReseller();
    $this->actingAs($owner)->post('http://localhost/reseller/menus/items', [
        'menu_location' => Menu::LOCATION_HEADER, 'label' => 'Theirs', 'url' => '/theirs',
    ]);
    $item = MenuItem::where('label', 'Theirs')->firstOrFail();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->put("http://localhost/settings/menus/items/{$item->id}", ['label' => 'Ours', 'url' => '/ours'])
        ->assertNotFound();

    expect($item->fresh()->label)->toBe('Theirs');
});
