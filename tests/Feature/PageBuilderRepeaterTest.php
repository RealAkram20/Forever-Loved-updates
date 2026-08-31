<?php

/**
 * The page builder's repeater — the control that edits a services grid's cards.
 *
 * These exist because of a bug that made *every* repeater in the builder render empty: the
 * Alpine component declared `repeaterRows`, `repeaterAdd`, `repeaterSet` and friends twice in
 * one object literal, and the second declaration — which took a string key where the views
 * pass a field object — silently won. `props[fieldObject]` stringifies to
 * `props["[object Object]"]`, so a section with six cards reported "0 of 12" and offered to
 * add a first one.
 *
 * A duplicate key in an object literal is legal JavaScript and throws nothing, so the only
 * thing that catches it is looking. That is what the first two tests do.
 *
 * The existing builder coverage asserted that the editor renders and that the field schema
 * reaches it. Both were true throughout. Nothing asserted that a list which had been *saved*
 * came back into its own editor, which is the one thing a reseller actually does.
 */

use App\Models\Page;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('declares each repeater method exactly once in the builder component', function () {
    $source = file_get_contents(resource_path('js/page-builder-alpine.js'));

    // Method shorthand at the object's own indentation, which is how every member of
    // Alpine.data("pageBuilder") is written.
    preg_match_all('/^    (repeater[A-Za-z]*)\(/m', $source, $matches);

    $counts = array_count_values($matches[1]);
    $duplicated = array_keys(array_filter($counts, fn (int $n) => $n > 1));

    expect($duplicated)->toBe([], 'these repeater methods are declared more than once, and the '
        .'later declaration silently overrides the earlier: '.implode(', ', $duplicated));
});

it('includes the repeater partial only behind its own field-kind guard', function () {
    $renderer = file_get_contents(
        resource_path('views/pages/settings/pages/partials/field-renderer.blade.php')
    );

    // One include, and the line before it must be the guard. An unguarded copy on the end of
    // the file drew a repeater under every field of every widget.
    expect(substr_count($renderer, "@include('pages.settings.pages.partials.field-repeater')"))->toBe(1);

    expect($renderer)->toMatch(
        '/x-if="field\.kind === \'repeater\'">\s*@include\(\'pages\.settings\.pages\.partials\.field-repeater\'\)/'
    );
});

it('hands a saved card list back to the editor that has to edit it', function () {
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $tier = ResellerTier::create([
        'name' => 'Builder tier',
        'slug' => 'builder-tier',
        'feature_page_builder' => true,
    ]);

    $reseller = Reseller::create([
        'name' => 'Repeater Funerals',
        'slug' => 'repeater-funerals',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        'reseller_tier_id' => $tier->id,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    Page::updateOrCreate(
        ['reseller_id' => $reseller->id, 'slug' => Page::SLUG_VISITOR_HOME],
        [
            'title' => 'Home',
            'is_published' => true,
            'layout' => app(\App\Services\PageLayoutService::class)->validateDocumentFromArray([
                'widgets' => [[
                    'type' => 'section_grid',
                    'props' => [
                        'columns' => 3,
                        'items' => [
                            ['icon' => 'hand-heart', 'title' => 'Funeral Arrangements', 'text' => 'Planning and coordination.', 'url' => '/funeral-arrangements'],
                            ['icon' => 'plane', 'title' => 'Repatriation Services', 'text' => 'To or from anywhere.', 'url' => '/repatriation-services'],
                        ],
                    ],
                ]],
            ]),
        ],
    );

    Page::clearSlugCache(Page::SLUG_VISITOR_HOME, $reseller->id);

    $html = $this->actingAs($owner)->get('/reseller/pages/homepage')->assertOk()->getContent();

    // The saved rows must be in the document the editor boots from, and the control that edits
    // them must be described to it. Either missing is the card list arriving uneditable.
    expect($html)->toContain('Funeral Arrangements')
        ->and($html)->toContain('Repatriation Services')
        ->and($html)->toContain('repeater');
});
