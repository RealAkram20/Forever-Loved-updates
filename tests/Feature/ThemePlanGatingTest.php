<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use App\Themes\ThemePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Which plan a theme needs, and — mostly — what gating must never do.
 *
 * The shape chosen is a **per-theme minimum tier**: each catalogue row names the lowest tier
 * that may *apply* it. Nothing else is restricted, and that is the whole design:
 *
 *  - A gated theme stays in the gallery and stays previewable. Nobody upgrades for something
 *    they have never been shown, and a card that vanished would make the pricing page argue
 *    for an invisible thing.
 *  - **Gating never moves a live site.** A reseller who is already running a theme keeps it if
 *    their tier later drops. The alternative is a funeral home's site changing design because
 *    a subscription lapsed — found out from a grieving family, not from us. This is the same
 *    promise unpublishing already makes, and it is the one that must not break.
 *  - Ungated is the default and stays it, so shipping this changed nothing for anybody.
 *
 * A reseller's own saved themes are never gated. They are built out of what that reseller was
 * already running, so locking them would take away something they already had.
 */
function gatingTenant(string $slug, ?string $tierSlug): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        'reseller_tier_id' => $tierSlug ? gatingTier($tierSlug)->id : null,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    ThemeCatalogue::sync();

    return $reseller->fresh();
}

/** Tiers are a ladder; sort_order is the rung. */
function gatingTier(string $slug): ResellerTier
{
    $order = ['starter' => 1, 'growth' => 2, 'professional' => 3, 'enterprise' => 4];

    return ResellerTier::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'sort_order' => $order[$slug] ?? 1, 'is_active' => true],
    );
}

function gatedTheme(string $tierSlug): Theme
{
    $theme = Theme::whereNull('reseller_id')->where('template', 'dignified')->firstOrFail();
    $theme->update(['minimum_tier_id' => gatingTier($tierSlug)->id]);

    return $theme->fresh();
}

/*
|--------------------------------------------------------------------------
| The gate itself
|--------------------------------------------------------------------------
*/

it('lets a reseller on the minimum tier apply the theme', function () {
    $acme = gatingTenant('gate-pro', 'professional');
    $theme = gatedTheme('professional');

    // "Minimum" means at or above, not exactly.
    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theme->id])
        ->assertSessionHasNoErrors();

    expect($acme->fresh()->theme_id)->toBe($theme->id);
});

it('lets a reseller above the minimum tier apply it', function () {
    $acme = gatingTenant('gate-ent', 'enterprise');
    $theme = gatedTheme('professional');

    $this->actingAs($acme->owner)->post('/reseller/theme/apply', ['theme_id' => $theme->id]);

    expect($acme->fresh()->theme_id)->toBe($theme->id);
});

it('refuses a reseller below the minimum tier, and says which plan', function () {
    $acme = gatingTenant('gate-starter', 'starter');
    $theme = gatedTheme('professional');

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theme->id])
        ->assertSessionHasErrors('theme_id');

    expect($acme->fresh()->theme_id)->toBeNull()
        // Naming the plan is the difference between an upsell and a bug report.
        ->and(session('errors')->first('theme_id'))->toContain('Professional');
});

it('treats a reseller on no plan at all as below every tier', function () {
    // "Not on a plan" is the most restrictive state, not an exemption from one.
    $acme = gatingTenant('gate-none', null);
    $theme = gatedTheme('starter');

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theme->id])
        ->assertSessionHasErrors('theme_id');
});

it('gates nothing until an admin actually gates something', function () {
    // Shipping the mechanism must change nothing for anybody. Every existing row is ungated,
    // and ungated is what the column defaults to.
    $acme = gatingTenant('gate-default', null);

    foreach (Theme::whereNull('reseller_id')->get() as $theme) {
        expect($theme->minimum_tier_id)->toBeNull()
            ->and($theme->isAvailableTo($acme))->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| What gating must never do
|--------------------------------------------------------------------------
*/

it('never moves a site whose plan drops below the theme it runs', function () {
    $acme = gatingTenant('gate-drop', 'enterprise');
    $theme = Theme::whereNull('reseller_id')->where('template', 'dignified')->firstOrFail();

    $this->actingAs($acme->owner)->post('/reseller/theme/apply', ['theme_id' => $theme->id]);
    expect($acme->fresh()->theme_id)->toBe($theme->id);

    // The plan lapses, and the theme is gated above where they now sit.
    $acme->update(['reseller_tier_id' => gatingTier('starter')->id]);
    $theme->update(['minimum_tier_id' => gatingTier('professional')->id]);

    // Their site is exactly where it was. A funeral home's design must not change because a
    // subscription did — they would hear about it from a family, not from us.
    expect($acme->fresh()->theme_id)->toBe($theme->id)
        ->and($acme->fresh()->templateSlug())->toBe('dignified');

    $this->get('/r/'.$acme->slug)->assertOk();
});

it('keeps a gated theme visible and previewable to a reseller below the line', function () {
    $acme = gatingTenant('gate-look', 'starter');
    $theme = gatedTheme('professional');

    // Still in the gallery — hiding it would ask them to upgrade for something invisible.
    expect(Theme::selectableFor($acme->id)->pluck('id'))->toContain($theme->id);

    $this->actingAs($acme->owner)
        ->get('/reseller/theme')
        ->assertOk()
        ->assertSee('Professional', false);

    // And still previewable, which is how the upgrade makes its own case. A preview cannot
    // change their site whatever their plan.
    $this->actingAs($acme->owner)
        ->post('/reseller/theme/preview', ['theme_id' => $theme->id])
        ->assertSessionHasNoErrors();

    $this->get(ThemePreview::linkFor($acme, $theme));
    $this->get('/r/'.$acme->slug)->assertOk()->assertSee('tpv-bar', false);

    expect($acme->fresh()->theme_id)->toBeNull();
});

it('never gates a reseller own saved theme', function () {
    $acme = gatingTenant('gate-own', null);

    // Built out of what they were already running, so locking it would take away something
    // they already had.
    $theirs = Theme::create([
        'reseller_id' => $acme->id,
        'name' => 'Our House Style',
        'slug' => 'our-house-style',
        'template' => 'dignified',
        'tokens' => [],
        'is_published' => true,
        'minimum_tier_id' => gatingTier('enterprise')->id,
    ]);

    expect($theirs->isAvailableTo($acme))->toBeTrue();

    $this->actingAs($acme->owner)
        ->post('/reseller/theme/apply', ['theme_id' => $theirs->id])
        ->assertSessionHasNoErrors();
});

it('reads a minimum pointing at a deleted tier as ungated', function () {
    $acme = gatingTenant('gate-orphan', null);
    $theme = gatedTheme('professional');

    gatingTier('professional')->delete();

    // nullOnDelete, and the model agrees: losing a restriction is recoverable in the admin
    // screen, while silently locking everyone out is found by support ticket.
    expect($theme->fresh()->minimum_tier_id)->toBeNull()
        ->and($theme->fresh()->isAvailableTo($acme))->toBeTrue();
});

it('survives a catalogue sync without losing what was gated', function () {
    $theme = gatedTheme('professional');

    // sync() rewrites name, template and tokens from the manifest on every deploy. If it
    // reset this too, every deploy would silently unlock the paid themes.
    ThemeCatalogue::sync();

    expect($theme->fresh()->minimum_tier_id)->toBe(gatingTier('professional')->id);
});

/*
|--------------------------------------------------------------------------
| Setting it
|--------------------------------------------------------------------------
*/

it('lets an admin set and clear the minimum plan', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    ThemeCatalogue::sync();
    $theme = Theme::whereNull('reseller_id')->where('template', 'dignified')->firstOrFail();
    $tier = gatingTier('professional');

    $this->actingAs($admin)
        ->post('/settings/themes/'.$theme->id.'/tier', ['minimum_tier_id' => $tier->id])
        ->assertRedirect(route('settings.themes'));

    expect($theme->fresh()->minimum_tier_id)->toBe($tier->id);

    // An empty select means "any plan", not a validation error.
    $this->actingAs($admin)
        ->post('/settings/themes/'.$theme->id.'/tier', ['minimum_tier_id' => '']);

    expect($theme->fresh()->minimum_tier_id)->toBeNull();
});

it('refuses to put a plan minimum on a tenant own theme', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = gatingTenant('gate-admin', null);

    $theirs = Theme::create([
        'reseller_id' => $acme->id,
        'name' => 'Our House Style',
        'slug' => 'our-house-style',
        'template' => 'dignified',
        'tokens' => [],
        'is_published' => true,
    ]);

    // Not ours to price. It is theirs, and it is not in the catalogue.
    $this->actingAs($admin)
        ->post('/settings/themes/'.$theirs->id.'/tier', ['minimum_tier_id' => gatingTier('enterprise')->id])
        ->assertForbidden();
});

it('refuses a reseller trying to set a plan minimum', function () {
    $acme = gatingTenant('gate-notadmin', 'starter');
    ThemeCatalogue::sync();
    $theme = Theme::whereNull('reseller_id')->where('template', 'dignified')->firstOrFail();

    $this->actingAs($acme->owner)
        ->post('/settings/themes/'.$theme->id.'/tier', ['minimum_tier_id' => gatingTier('starter')->id])
        ->assertForbidden();

    expect($theme->fresh()->minimum_tier_id)->toBeNull();
});

it('renders the admin screen with the plan control on it', function () {
    // The POST is covered above; this covers the page that offers it. A blade error in the
    // new control would otherwise only show up when an admin opened the screen.
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $acme = gatingTenant('gate-render', 'starter');
    $theme = gatedTheme('professional');
    $acme->update(['theme_id' => $theme->id]);

    $this->actingAs($admin)
        ->get('/settings/themes')
        ->assertOk()
        ->assertSee('Minimum plan to apply', false)
        // The count that tells an admin what they just did to people already on the theme.
        ->assertSee('below that plan', false);
});
