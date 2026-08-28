<?php

use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Our shop window is ours.
 *
 * ThemeSetting::tenant() falls back to the signed-in user's reseller so a funeral home's staff
 * keep their own colours and logo across the dashboard, memorial editing and their
 * notifications. That is deliberate and it stays.
 *
 * It was also reaching our marketing pages. A reseller's staff opening our home page were
 * served our copy, our prices and our call to action wrapped in *their* logo, their colours and
 * their name in the title bar — which tells the reader they are looking at their own company's
 * offer when they are looking at ours. Those same people are the B2C customers we are trying to
 * sell to, so it is the one audience the confusion costs the most.
 *
 * UsePlatformBranding suppresses that one fallback on those pages, and only where no reseller
 * was resolved from the request — so it can never reach a reseller's own site. Half of what is
 * asserted here is that it does not.
 */
const BRAND_HEX = '#7C1D6F';

function brandingTenant(): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => 'ufs-brand',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        // A colour nothing else in the suite uses, so finding it in a page means it came
        // from this reseller and from nowhere else.
        'primary_color' => BRAND_HEX,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/*
|--------------------------------------------------------------------------
| Our pages
|--------------------------------------------------------------------------
*/

dataset('our public pages', ['/', '/about', '/pricing', '/contact', '/find-memorial']);

it('wears our brand on our own pages, even for a reseller staff member', function (string $path) {
    $ufs = brandingTenant();

    $this->actingAs($ufs->owner)
        ->get($path)
        ->assertOk()
        ->assertDontSee(BRAND_HEX, false);
})->with('our public pages');

it('puts our name in the title, not theirs', function () {
    // The detail that gives it away fastest: the browser tab read "Home | Uganda Funeral
    // Services Ltd" on the platform's own front page.
    $ufs = brandingTenant();

    $this->actingAs($ufs->owner)
        ->get('/')
        ->assertOk()
        ->assertDontSee('<title>Home | '.$ufs->name, false);
});

it('looks the same to a reseller staff member as to anyone else', function () {
    // The real test of the rule: two people on the same URL should be reading the same offer.
    $ufs = brandingTenant();

    $anonymous = $this->get('/')->assertOk()->getContent();

    $asStaff = $this->actingAs($ufs->owner)->get('/')->assertOk()->getContent();

    // Not a full-page diff — the two differ legitimately in the account menu and CSRF token.
    // The brand colour is the thing that must not depend on who is reading.
    expect(str_contains($anonymous, BRAND_HEX))->toBeFalse()
        ->and(str_contains($asStaff, BRAND_HEX))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Their pages, and their screens
|--------------------------------------------------------------------------
*/

it('leaves a reseller their own brand on their own site', function () {
    $ufs = brandingTenant();

    // The tenant is bound from the request here, so the middleware steps aside.
    $this->get('/r/'.$ufs->slug)
        ->assertOk()
        ->assertSee(BRAND_HEX, false);
});

it('leaves a reseller their own brand on their own inner pages', function () {
    $ufs = brandingTenant();

    $this->get('/r/'.$ufs->slug.'/about')
        ->assertOk()
        ->assertSee(BRAND_HEX, false);
});

it('leaves a reseller their own brand on a real host', function () {
    $ufs = brandingTenant();

    // ResolveResellerByHost binds the tenant in the web group, before any route middleware —
    // which is what makes the guard inert here despite being attached to this same route.
    $this->get('http://'.$ufs->slug.'.'.config('reseller.domain').'/about')
        ->assertOk()
        ->assertSee(BRAND_HEX, false);
});

it('keeps a reseller their brand on the screens they work in', function () {
    // The reason the fallback exists at all, and the half of it that was never wrong. A
    // funeral home's staff editing a memorial should see their own company's colours.
    $ufs = brandingTenant();

    $this->actingAs($ufs->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(BRAND_HEX, false);
});
