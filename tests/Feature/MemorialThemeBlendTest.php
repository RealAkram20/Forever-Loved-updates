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
    // Pinned for the same reason blendMemorial() pins: the factory rolls `is_public` at 85%,
    // and a private memorial is not served at all — so this failed about one run in seven for
    // a reason that had nothing to do with templates.
    $memorial = Memorial::factory()->create([
        'reseller_id' => null,
        'user_id' => $owner->id,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);

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

it('frames the portrait in the reseller own two colours', function () {
    // The only frame on the page, directly under the photograph, so it carries the template's
    // crimson and gold rather than the platform's plain white card. Built from the palette, so
    // a reseller who rebrands takes it with them.
    $acme = blendTenant('blend-frame', 'dignified');
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('.memorial-hero__portrait', false)
        ->assertSee('linear-gradient(180deg,', false);
});

it('ends the scene on the template own rule rather than a smear', function () {
    // What "the blend is not clean" was.
    //
    // The platform dissolves the foot of its band with a mask so it can sit on any ground
    // without knowing its colour, and wraps the page in `glass-bg-mesh` — three soft radial
    // washes in indigo, pink and blue. Over a near-black plate the two together produced a
    // grey ramp that ended in a second, faintly lilac one: two edges before the page reached
    // white, where a family should see none.
    //
    // This template knows its own ground, so the band ends on the same gold-then-crimson rule
    // that runs under its footer and its headings.
    $acme = blendTenant('blend-foot', 'dignified');
    $memorial = blendMemorial($acme);

    $html = $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent();

    expect(str_contains($html, 'mask-image: none'))
        ->toBeTrue('the template must turn the platform fade off, not merely draw over it')
        ->and(str_contains($html, 'border-image-source: linear-gradient('))
        ->toBeTrue('the band should end on this template rule')
        ->and(str_contains($html, '.glass-bg-mesh'))
        ->toBeTrue('the platform ambient wash has to be answered, or the second edge comes back');
});

it('leaves the platform fade and wash alone on the base template', function () {
    // The other half of the promise. A reseller not running this template keeps the dissolve,
    // which is what suits a page that has to sit on any brand's ground.
    $acme = blendTenant('blend-foot-basic', null);
    $memorial = blendMemorial($acme);

    $html = $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent();

    expect(str_contains($html, 'mask-image: none'))
        ->toBeFalse('nothing should be switching the platform mask off here')
        ->and(str_contains($html, 'glass-bg-mesh'))
        ->toBeTrue('the platform wash is the platform default and stays');
});

it('gives a template its own flower on the tribute card', function () {
    // Per-file, not all-or-nothing: this template replaces the rose and leaves the candle and
    // the praying hands alone, so the assertion is that the two untouched ones still resolve to
    // the platform's.
    $acme = blendTenant('blend-rose', 'dignified');
    $memorial = blendMemorial($acme);

    $html = $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent();

    expect(str_contains($html, 'images/themes/dignified/tributes/flower.png'))
        ->toBeTrue('the template ships a flower and should be using it')
        ->and(str_contains($html, 'images/tributes/flower.png'))
        ->toBeFalse('and not the platform rose alongside it');
});

it('leaves the platform tribute artwork to the platform', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create([
        'reseller_id' => null,
        'user_id' => $owner->id,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);

    $html = $this->get('/'.$memorial->slug)->assertOk()->getContent();

    expect(str_contains($html, 'images/themes/'))
        ->toBeFalse('our own memorial should reach for no template folder at all');
});

it('falls back to the platform flower for a template that ships none', function () {
    // The same promise the backdrop makes. A template is not required to dress this page, and
    // one that does not must never leave a broken image where the artwork should be.
    $acme = blendTenant('blend-rose-basic', 'basic');
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertSee('images/tributes/flower.png', false);
});

it('makes the falling petals the colours of whichever flower is on the card', function () {
    // The artwork and the petals are one decision. Left apart, a template could swap the rose
    // and still rain the platform's coral-and-purple over it — which reads less as a theme than
    // as a bug, and only shows itself after somebody taps.
    $acme = blendTenant('blend-petals', 'dignified');
    $memorial = blendMemorial($acme);

    $html = $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent();

    expect(str_contains($html, '__tributePetalColours'))->toBeTrue()
        // Sampled off this template's rose: near-black through crimson to gold.
        ->and(str_contains($html, '#5E1A19'))->toBeTrue()
        ->and(str_contains($html, '#E7AA52'))->toBeTrue();
});

it('leaves the platform petals alone on a site running no template', function () {
    $acme = blendTenant('blend-petals-basic', null);
    $memorial = blendMemorial($acme);

    expect(str_contains(
        $this->get('/r/'.$acme->slug.'/'.$memorial->slug)->assertOk()->getContent(),
        '__tributePetalColours'
    ))->toBeFalse();
});

it('leaves the platform portrait mount plain', function () {
    // A reseller not on this template keeps the white card, which is what suits every brand.
    $acme = blendTenant('blend-frame-basic', null);
    $memorial = blendMemorial($acme);

    $this->get('/r/'.$acme->slug.'/'.$memorial->slug)
        ->assertOk()
        ->assertDontSee('.memorial-hero__portrait', false);
});
