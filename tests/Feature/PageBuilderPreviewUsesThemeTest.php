<?php

use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\Theme;
use App\Models\User;
use App\PageBuilder\Widgets\SectionBannerWidget;
use App\Themes\ThemeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The builder's live preview has to be the site the reseller is actually publishing.
 *
 * The preview endpoint is on *our* host, so none of the ResolveReseller* middleware runs and
 * ActiveTheme was still on the base template. The harness extends `layouts.visitor`, which a
 * template overrides — so a reseller running Dignified was shown their page in the platform's
 * design: sans-serif where their site is small-caps serif, a rounded crimson button where
 * theirs is a square gold one, no gold rule, and `{theme}/…` images pointing at a directory
 * the base template does not have.
 *
 * They were editing one design and publishing another. A preview that lies is worse than no
 * preview, because it is believed.
 */
function builderPreviewTenant(string $slug, ?string $template): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    // The builder is a paid capability; without it the endpoint 403s and the test would be
    // asserting the gate rather than the preview.
    $tier = ResellerTier::firstOrCreate(
        ['slug' => 'builder-tier'],
        ['name' => 'Builder', 'sort_order' => 9, 'is_active' => true, 'feature_page_builder' => true],
    );

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        'reseller_tier_id' => $tier->id,
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

/** One real banner, built from the widget's own defaults so it stays valid as rules change. */
function builderPreviewPayload(): array
{
    return [
        'version' => 1,
        'widgets' => [[
            'type' => 'section_banner',
            'props' => array_merge(SectionBannerWidget::defaultProps(), [
                'heading' => 'Dignified care. Compassionate service.',
            ]),
        ]],
    ];
}

it('previews a themed reseller page in their own template', function () {
    $acme = builderPreviewTenant('preview-dg', 'dignified');

    // --dg-ink is defined only by Dignified's visitor layout. Its presence proves the preview
    // resolved layouts.visitor through the template rather than through resources/views.
    $this->actingAs($acme->owner)
        ->post('/reseller/pages/preview', builderPreviewPayload())
        ->assertOk()
        ->assertSee('--dg-ink:', false);
});

it('previews a base-template reseller page in the base design', function () {
    // The other half: a template must not leak into a preview for a reseller not running it.
    $acme = builderPreviewTenant('preview-basic', null);

    $this->actingAs($acme->owner)
        ->post('/reseller/pages/preview', builderPreviewPayload())
        ->assertOk()
        ->assertDontSee('--dg-ink:', false);
});

it('shows the same design in the preview as the published page serves', function () {
    // The property that actually matters, stated as a comparison rather than as a marker:
    // whatever the live site does with this widget, the preview must do too.
    $acme = builderPreviewTenant('preview-match', 'dignified');

    $live = $this->get('/r/'.$acme->slug)->assertOk()->getContent();

    $preview = $this->actingAs($acme->owner)
        ->post('/reseller/pages/preview', builderPreviewPayload())
        ->assertOk()
        ->getContent();

    foreach (['--dg-ink:', '--dg-gold:', 't-banner-ruled'] as $marker) {
        expect(str_contains($live, $marker))->toBeTrue("live page is missing {$marker}")
            ->and(str_contains($preview, $marker))->toBeTrue("preview is missing {$marker}");
    }
});

it('still refuses the preview to a reseller whose plan has no builder', function () {
    // The entitlement gate is unchanged by any of this.
    $acme = builderPreviewTenant('preview-locked', 'dignified');
    $acme->tier->update(['feature_page_builder' => false]);

    $this->actingAs($acme->owner)
        ->post('/reseller/pages/preview', builderPreviewPayload())
        ->assertForbidden();
});
