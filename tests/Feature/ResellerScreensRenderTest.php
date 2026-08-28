<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Every screen a reseller runs their business from has to render.
 *
 * Settings went down with a 500 for a reason no unit test could see: a `use ... as CD` inside
 * a @php block buried in a component slot. Blade compiles a template into nested blocks and
 * PHP only permits an import at the top of a file, so it was a parse error — and parse errors
 * in a view surface at request time, not at boot, so nothing failed until somebody opened the
 * page. The same import at the *top* of a dozen other templates is fine, which is exactly why
 * it looked safe.
 *
 * The class of fault is wider than one keyword: an undefined variable, a renamed helper, a
 * component that lost an argument. All of them are invisible until the page is fetched. So
 * this fetches them.
 *
 * Deliberately shallow — status codes, not content. It is a smoke alarm, not a spec; the
 * pages that need real assertions have their own tests.
 */
function screensTenant(): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    // The builder is a paid capability, and half these screens 403 without it — which would
    // make this suite green while the pages stayed broken.
    $tier = ResellerTier::firstOrCreate(
        ['slug' => 'screens-tier'],
        ['name' => 'Screens', 'sort_order' => 9, 'is_active' => true, 'feature_page_builder' => true],
    );

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => 'ufs-screens',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        'reseller_tier_id' => $tier->id,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

dataset('reseller screens', [
    'settings' => '/reseller/settings',
    'appearance' => '/reseller/appearance',
    'theme' => '/reseller/theme',
    'pages' => '/reseller/pages',
    'menus' => '/reseller/menus',
    'staff' => '/reseller/staff',
    'plans' => '/reseller/plans',
    'clients' => '/reseller/clients',
    'payments' => '/reseller/payments',
    'embed' => '/reseller/embed',
]);

it('renders without a server error', function (string $path) {
    $ufs = screensTenant();

    $response = $this->actingAs($ufs->owner)->get($path);

    // 200 or a redirect is fine — a redirect means the route exists and something declined it
    // deliberately. 500 means the page itself is broken, which is the only thing under test.
    expect($response->status())->not->toBe(500)
        ->and($response->status())->toBeLessThan(500);
})->with('reseller screens');

it('renders the contact form the business fills in', function () {
    // Named separately because this is the page that broke, and because a reseller who cannot
    // open it cannot tell a grieving family where to find them.
    $ufs = screensTenant();

    $this->actingAs($ufs->owner)
        ->get('/reseller/settings')
        ->assertOk()
        ->assertSee('Contact &amp; Location', false)
        ->assertSee('c-phone', false)
        ->assertSee('c-address', false);
});
