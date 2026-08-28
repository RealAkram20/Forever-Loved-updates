<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A memorial hosted by a reseller should look like it belongs to that business.
 *
 * The opening for that is deliberately one line wide: `pages/memorials/public.blade.php`
 * includes `memorial-theme` if the active template ships one, and resolves the band's backdrop
 * from the template's own image folder if it has one. Everything else on that page — tabs,
 * tributes, gallery, timeline, comments — is the platform's and stays the platform's.
 *
 * So most of what is asserted here is what must NOT happen: a template must not reach any page
 * it was not invited to, and a reseller who is not running one must see exactly what they saw
 * before. This page is somebody's grief with two dozen working parts attached, and the cost of
 * getting it wrong is paid by a family rather than by us.
 */
function blendTenant(string $slug, ?string $template): Reseller
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
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    if ($template !== null) {
        ThemeCatalogue::sync();
        $theme = Theme::whereNull('reseller_id')->where('template', $template)->first();

        if ($theme) {
            $reseller->update(['theme_id' => $theme->id]);
        }
    }

    return $reseller->fresh();
}

/**
 * A memorial anyone can open.
 *
 * `is_public` and the plan are randomised in the factory, and both change what this page
 * renders — a private one is not served at all, and the share button is quota-gated. Pinned
 * here so a failure means the theme layer moved, not that the dice came up differently.
 */
function blendMemorial(Reseller $reseller): Memorial
{
    return Memorial::factory()->create([
        'reseller_id' => $reseller->id,
        'user_id' => $reseller->owner_user_id,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);
}

it('dresses a memorial on a themed reseller in that template backdrop', function () {
    $acme = blendTenant('blend-dg', 'dignified');
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('images/themes/dignified/memorial-backdrop.webp', false)
        ->assertDontSee('images/memorial/hero-backdrop.webp', false);
});

it('leaves a memorial on the base template exactly as it was', function () {
    // The whole promise of the opt-in. Most resellers run the base template and none of this
    // should reach them.
    $acme = blendTenant('blend-basic', null);
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('images/memorial/hero-backdrop.webp', false)
        ->assertDontSee('memorial-backdrop.webp', false)
        // The template stylesheet must not be there at all, not merely be inert.
        ->assertDontSee('--t-memorial-backdrop-position', false);
});

it('leaves the platform its own memorials', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['reseller_id' => null, 'user_id' => $owner->id]);

    $this->get('/'.$memorial->slug)
        ->assertOk()
        ->assertSee('images/memorial/hero-backdrop.webp', false)
        ->assertDontSee('--t-memorial-backdrop-position', false);
});

it('gives the template header the variables it is written against', function () {
    // The bug this found. A memorial page renders <x-home-header /> — on this template, its
    // own header — from layouts/fullscreen-layout, which never defined --dg-ink. The nav bar
    // was drawing white text on an undefined background: invisible, on every memorial hosted
    // by a reseller running this template, and only there.
    $acme = blendTenant('blend-nav', 'dignified');
    $memorial = blendMemorial($acme);

    $html = $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent();

    // The header uses it; the stylesheet has to define it on this page too.
    expect($html)->toContain('bg-[var(--dg-ink)]')
        ->and($html)->toContain('--dg-ink:');
});

it('keeps every working part of the memorial page', function () {
    // A template may set type and colour. It may not cost a family the things they came for,
    // so the tabs and the tribute actions are asserted present on the themed page.
    $acme = blendTenant('blend-parts', 'dignified');
    $memorial = blendMemorial($acme);

    // Deliberately the parts that are always there. The share button is quota-gated and the
    // tribute cards depend on the plan, so asserting those would test the subscription tier
    // rather than the theme.
    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('Gallery', false)
        ->assertSee('Stories/Tributes', false)
        ->assertSee('Biography', false)
        ->assertSee('memorial-hero__portrait', false);
});

it('falls back to the platform backdrop when a template ships none', function () {
    // Templates are not required to dress this page, and one that does not must not leave a
    // broken image where the scene should be.
    $acme = blendTenant('blend-nofile', 'basic');
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('images/memorial/hero-backdrop.webp', false);
});
