<?php

/**
 * Suspending a reseller closes their site and leaves the memorials open.
 *
 * Suspension is a sanction against a business. The families whose memorials that business
 * hosts have done nothing, and a memorial is not leverage — so the two halves are tested
 * separately and neither is allowed to drift into the other. A regression in the first
 * direction lets a suspended business keep trading; a regression in the second takes a
 * grieving family's page down over somebody else's invoice.
 */

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function suspendableTenant(string $status = Reseller::STATUS_ACTIVE): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Bereaved & Co',
        'slug' => 'bereaved',
        'owner_user_id' => $owner->id,
        'status' => $status,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function suspendableMemorial(Reseller $reseller): Memorial
{
    return Memorial::factory()->create([
        'reseller_id' => $reseller->id,
        'original_reseller_id' => $reseller->id,
        'user_id' => $reseller->owner_user_id,
        'slug' => 'wilson-mubiru',
        'first_name' => 'Wilson',
        'last_name' => 'Mubiru',
        'full_name' => 'Wilson Mubiru',
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);
}

function tenantHost(Reseller $reseller): string
{
    return 'http://'.$reseller->slug.'.'.config('reseller.domain');
}

it('serves the whole site while the reseller is active', function () {
    $reseller = suspendableTenant();
    suspendableMemorial($reseller);
    $host = tenantHost($reseller);

    $this->get($host.'/')->assertOk();
    $this->get($host.'/wilson-mubiru')->assertOk();
});

it('closes the front page and the marketing pages once suspended', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    suspendableMemorial($reseller);
    $host = tenantHost($reseller);

    // 503 rather than 404: a suspension is meant to end, and 404 tells a search engine the
    // funeral home never existed.
    foreach (['/', '/about', '/contact', '/find-memorial'] as $path) {
        $this->get($host.$path)->assertStatus(503);
    }
});

it('keeps the memorial reachable by its link while suspended', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    suspendableMemorial($reseller);

    $this->get(tenantHost($reseller).'/wilson-mubiru')
        ->assertOk()
        ->assertSee('Wilson', false);
});

it('keeps the memorial reachable on the development path address too', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    suspendableMemorial($reseller);

    // /r/{slug} binds the tenant later than the web group runs, which is exactly why the gate
    // could not live in one middleware and had to be in all three resolvers.
    $this->get('/r/'.$reseller->slug.'/wilson-mubiru')->assertOk();
    $this->get('/r/'.$reseller->slug)->assertStatus(503);
});

it('does not lock the platform site when a reseller is suspended', function () {
    suspendableTenant(Reseller::STATUS_SUSPENDED);

    // No tenant is bound here, so the gate must not fire.
    $this->get('/')->assertOk();
});

it('says nothing about why the site is closed', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    $html = $this->get(tenantHost($reseller).'/')->assertStatus(503)->getContent();

    // A visitor is not owed a business's account status, and a reseller's billing trouble is
    // not for publishing on their own front door.
    expect(strtolower($html))->not->toContain('suspend')
        ->and(strtolower($html))->not->toContain('unpaid')
        // The one thing the page is for: the memorial link still works.
        ->and(strtolower($html))->toContain('memorial');
});

it('leaves signing in open so a family is not locked out of their own memorial', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    suspendableMemorial($reseller);

    // The memorial's owner is usually a family member, not the funeral home. The reseller's
    // own staff are stopped at the dashboard by EnsureResellerActive, which is the guard that
    // should be doing it.
    $this->get(tenantHost($reseller).'/login')->assertOk();
});

it('closes a CMS page that shares the memorial route', function () {
    $reseller = suspendableTenant(Reseller::STATUS_SUSPENDED);
    suspendableMemorial($reseller);

    // The trap that made the first version of this wrong. showForReseller answers one route
    // with two different things: a memorial if the slug names one, otherwise the reseller's
    // CMS page of that name. So /services arrives under a route called memorial.public.* and
    // is a marketing page — allowing the route by name left it serving 200 on a suspended
    // site. The gate has to resolve the slug, not trust the route.
    \App\Models\Page::create([
        'reseller_id' => $reseller->id,
        'slug' => 'services',
        'title' => 'Our Services',
        'is_published' => true,
        'layout' => ['version' => 1, 'widgets' => [[
            'id' => 'w_services01',
            'type' => 'heading',
            'order' => 0,
            'props' => ['text' => 'Our Services'],
        ]]],
    ]);

    $host = tenantHost($reseller);

    $this->get($host.'/services')->assertStatus(503);
    // ...and the memorial beside it is still open, on the same route.
    $this->get($host.'/wilson-mubiru')->assertOk();
});

it('does not let another tenant memorial hold a suspended site open', function () {
    $suspended = suspendableTenant(Reseller::STATUS_SUSPENDED);

    $otherOwner = \App\Models\User::factory()->create();
    $other = Reseller::create([
        'name' => 'Other Home',
        'slug' => 'other-home',
        'owner_user_id' => $otherOwner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    Memorial::factory()->create([
        'reseller_id' => $other->id,
        'user_id' => $otherOwner->id,
        'slug' => 'someone-elses',
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);

    // The slug exists, but not here. Scoped to the tenant, or one reseller's memorial would
    // prop open every suspended site that happened to be asked for it.
    $this->get(tenantHost($suspended).'/someone-elses')->assertStatus(503);
});