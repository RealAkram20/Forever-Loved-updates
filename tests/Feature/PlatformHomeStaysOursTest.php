<?php

use App\Models\Memorial;
use App\Models\Page;
use App\Models\Reseller;
use App\Models\User;
use App\Services\PageLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Our front page is ours, even when the person reading it works for a reseller.
 *
 * ThemeSetting draws a line its own docblock spells out: **branding** follows `tenant()` — the
 * signed-in user's reseller, so a funeral home's staff see their own colours and logo wherever
 * they go — while **content** follows `siteTenant()`, which is the host. Whose site is this,
 * not who is looking at it.
 *
 * PageController::home() was the one page that used tenant() for content. So a reseller's own
 * staff, or an admin viewing as them, opening our marketing home page were served *the
 * reseller's* front page instead: their hero, their About, their memorials, inside our layout.
 * They had no way to read ours at all. Every sibling on the same controller — about, pricing,
 * contact, privacy, terms — already used siteTenant(); home simply never did.
 *
 * The reverse is asserted here too. A fix that swung the other way would take a reseller's own
 * front page off their own site, which is worse.
 */
const PLATFORM_HOME_MARKER = 'A page only this funeral home would publish';

function platformHomeTenant(): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => 'ufs-home',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/** Give the reseller a front page of their own, with something unmistakably theirs on it. */
function giveResellerHomeLayout(Reseller $reseller): void
{
    // Built from the widget's own defaults so the document stays valid as its rules change —
    // a hand-written props array here would fail validation the next time one is added.
    $document = app(PageLayoutService::class)->validateDocumentFromArray([
        'widgets' => [[
            'type' => 'heading',
            'props' => array_merge(
                \App\PageBuilder\Widgets\HeadingWidget::defaultProps(),
                ['text' => PLATFORM_HOME_MARKER],
            ),
        ]],
    ]);

    $page = Page::where('reseller_id', $reseller->id)
        ->where('slug', Page::SLUG_VISITOR_HOME)
        ->firstOrFail();

    $page->layout = $document;
    $page->save();

    Page::clearSlugCache(Page::SLUG_VISITOR_HOME, $reseller->id);
}

it('serves the platform home to a reseller staff member, not their own', function () {
    $ufs = platformHomeTenant();
    giveResellerHomeLayout($ufs);

    // Signed in as their owner, on *our* host. Branding may follow them; the page must not.
    $this->actingAs($ufs->owner)
        ->get('/')
        ->assertOk()
        ->assertDontSee(PLATFORM_HOME_MARKER, false);
});

it('still serves the reseller their own front page on their own site', function () {
    // The other half. Fixing the leak by always serving the platform's home would have taken
    // a funeral home's front page off their own address.
    $ufs = platformHomeTenant();
    giveResellerHomeLayout($ufs);

    $this->get('/r/'.$ufs->slug)
        ->assertOk()
        ->assertSee(PLATFORM_HOME_MARKER, false);
});

it('shows our memorials on our home page, not the reseller own', function () {
    // The same mistake reached the "popular memorials" list, which is scoped by the same
    // variable — so our front page advertised a reseller's memorials to their staff.
    $ufs = platformHomeTenant();

    $theirs = Memorial::factory()->create([
        'reseller_id' => $ufs->id,
        'user_id' => $ufs->owner_user_id,
        'first_name' => 'Tenant',
        'last_name' => 'Only',
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->actingAs($ufs->owner)
        ->get('/')
        ->assertOk()
        ->assertDontSee($theirs->full_name, false);
});

it('keeps a signed-out visitor on the platform home too', function () {
    // The bug needed a signed-in reseller user to appear, so this is the control: the page
    // nobody disputed has to keep working.
    $ufs = platformHomeTenant();
    giveResellerHomeLayout($ufs);

    $this->get('/')
        ->assertOk()
        ->assertDontSee(PLATFORM_HOME_MARKER, false);
});
