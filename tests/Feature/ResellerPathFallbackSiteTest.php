<?php

use App\Models\Memorial;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A reseller's site under the /r/{slug} path fallback.
 *
 * That fallback exists for environments that cannot do host routing — a subdirectory install
 * or local development — and it was the only part of a reseller's site that no middleware
 * bound a tenant to. Everything host-routed had been fixed; this had not, and the symptoms
 * were the ones a reseller would notice first:
 *
 *  - /r/{slug}/find-memorial had no route, so it fell through to the memorial route, found
 *    no memorial by that name, and served their *page* — whose directory widget then fetched
 *    the platform's endpoint. Their own directory answered with every memorial except theirs.
 *  - "View all" on their front page, and the hero's buttons, resolved through route(), which
 *    URL::forceRootUrl() roots at the platform. Their visitors were walked onto our site.
 *  - The header search box likewise queried the platform's endpoint untenanted.
 */
function pathFallbackReseller(string $slug = 'vandervort-west'): Reseller
{
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    return Reseller::factory()->create(['slug' => $slug]);
}

function aMemorialFor(?Reseller $reseller, string $name): Memorial
{
    $owner = User::factory()->create(['reseller_id' => $reseller?->id]);

    return Memorial::factory()->create([
        'user_id' => $owner->id,
        'reseller_id' => $reseller?->id,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
        'full_name' => $name,
    ]);
}

it('lists the reseller\'s own memorials in their directory, and only theirs', function () {
    $reseller = pathFallbackReseller();
    aMemorialFor($reseller, 'Their Person');
    aMemorialFor(null, 'Platform Person');

    $names = collect(
        $this->getJson('http://localhost/r/vandervort-west/find-memorial')->assertOk()->json('data')
    )->pluck('name')->all();

    expect($names)->toContain('Their Person')
        ->and($names)->not->toContain('Platform Person');
});

it('leaves the platform directory listing the platform\'s memorials', function () {
    $reseller = pathFallbackReseller();
    aMemorialFor($reseller, 'Their Person');
    aMemorialFor(null, 'Platform Person');

    $names = collect(
        $this->getJson('http://localhost/find-memorial')->assertOk()->json('data')
    )->pluck('name')->all();

    expect($names)->toContain('Platform Person')
        ->and($names)->not->toContain('Their Person');
});

it('scopes the header search to the tenant for a visitor who is not signed in', function () {
    $reseller = pathFallbackReseller();
    aMemorialFor($reseller, 'Their Person');
    aMemorialFor(null, 'Platform Person');

    // Not signed in is the case that broke: ThemeSetting::tenant() falls back to the
    // *user's* reseller, so this only ever worked for a reseller's own staff.
    $theirs = collect(
        $this->getJson('http://localhost/r/vandervort-west/api/search/memorials?q=Person')
            ->assertOk()->json('results')
    )->pluck('name')->all();

    $ours = collect(
        $this->getJson('http://localhost/api/search/memorials?q=Person')->assertOk()->json('results')
    )->pluck('name')->all();

    expect($theirs)->toContain('Their Person')->and($theirs)->not->toContain('Platform Person')
        ->and($ours)->toContain('Platform Person')->and($ours)->not->toContain('Their Person');
});

it('keeps every generated link on the reseller\'s front page inside their own site', function () {
    $reseller = pathFallbackReseller();
    aMemorialFor($reseller, 'Their Person');

    $html = $this->get('http://localhost/r/vandervort-west')->assertOk()->getContent();

    preg_match_all('~(?:href="|fetch\(`)((?:https?://)?[^"`?]*(?:find-memorial|search/memorials))~', $html, $matches);
    $links = array_values(array_unique($matches[1]));

    expect($links)->not->toBeEmpty();

    foreach ($links as $link) {
        expect($link)->toContain('/r/vandervort-west/');
    }
});

it('turns visitors away when the reseller has switched their directory off', function () {
    $reseller = pathFallbackReseller();
    aMemorialFor($reseller, 'Their Person');

    Page::where('reseller_id', $reseller->id)
        ->where('slug', Page::SLUG_FIND_MEMORIAL)
        ->update(['is_published' => false]);

    $this->get('http://localhost/r/vandervort-west/find-memorial')
        ->assertRedirect($reseller->publicBaseUrl());
});

it('still serves a memorial whose slug sits alongside the standard paths', function () {
    $reseller = pathFallbackReseller();
    $memorial = aMemorialFor($reseller, 'Their Person');

    // The new routes are registered before /r/{reseller}/{slug}; a real memorial must still
    // win its own address.
    $this->get('http://localhost/r/vandervort-west/'.$memorial->slug)->assertOk();
});
