<?php

use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\SiteContactDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Two things a business should be able to say about itself without help.
 *
 * The line under the logo in the footer was `branding.tagline`, a platform setting readable
 * only from our own admin screens. A reseller had no field for it, so a funeral home's footer
 * carried our marketing — "Celebrate lives that matter" — under their logo.
 *
 * The map was worse. It took a pasted <iframe>, and a Google embed's `pb=` parameter runs to
 * hundreds of characters; one arrived truncated and rendered "Invalid 'pb' parameter" where
 * the business's location should have been. Nobody should have to get that right by hand when
 * they have already typed the address two fields above.
 */
function footerTenant(string $slug = 'ufs-footer'): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/**
 * Just the footer.
 *
 * The platform tagline also reaches a reseller page through the share-meta tags, which is a
 * separate leak on a separate surface — asserting against the whole document would fail for
 * that reason and hide whether the footer itself is right.
 *
 * @return string
 */
function footerOf(string $html): string
{
    return preg_match("#<footer.*</footer>#s", $html, $m) ? $m[0] : "";
}

/*
|--------------------------------------------------------------------------
| The footer line
|--------------------------------------------------------------------------
*/

it('saves a footer description a reseller types', function () {
    $ufs = footerTenant();

    $this->actingAs($ufs->owner)->put('/reseller/settings', [
        'name' => $ufs->name,
        'contact_email' => 'info@ufs.co.ug',
        'tagline' => 'Providing dignified funeral services with compassion, respect and professionalism.',
    ])->assertSessionHasNoErrors();

    expect(ResellerSetting::allFor($ufs->id)['branding.tagline']['value'] ?? null)
        ->toBe('Providing dignified funeral services with compassion, respect and professionalism.');
});

it('shows their line on their site, not ours', function () {
    SystemSetting::set('branding.tagline', 'Celebrate lives that matter');

    $ufs = footerTenant();
    ResellerSetting::set($ufs->id, 'branding.tagline', 'Compassion, respect and professionalism.');

    $footer = footerOf($this->get('/r/'.$ufs->slug)->assertOk()->getContent());

    expect($footer)->toContain('Compassion, respect and professionalism.')
        ->and($footer)->not->toContain('Celebrate lives that matter');
});

it('shows no line at all rather than ours when they have written none', function () {
    // Our marketing under a funeral home's logo is worse than an empty space.
    SystemSetting::set('branding.tagline', 'Celebrate lives that matter');

    $ufs = footerTenant('ufs-noline');

    $footer = footerOf($this->get('/r/'.$ufs->slug)->assertOk()->getContent());

    expect($footer)->not->toContain('Celebrate lives that matter');
});

it('keeps our own copy on our own site', function () {
    // Ours describes us, and belongs on our footer only. The base footer renders
    // branding.footer_description rather than the tagline — a separate platform setting.
    expect(footerOf($this->get('/')->assertOk()->getContent()))
        ->toContain('Creating the digital home for every life story');
});

it('clears the line when the field is emptied', function () {
    $ufs = footerTenant();
    ResellerSetting::set($ufs->id, 'branding.tagline', 'Something they no longer want');

    $this->actingAs($ufs->owner)->put('/reseller/settings', [
        'name' => $ufs->name,
        'contact_email' => null,
        'tagline' => '',
    ]);

    expect(ResellerSetting::allFor($ufs->id))->not->toHaveKey('branding.tagline');
});

/*
|--------------------------------------------------------------------------
| The map
|--------------------------------------------------------------------------
*/

it('draws a map from the address alone', function () {
    $ufs = footerTenant('ufs-map');
    app()->instance(Reseller::class, $ufs);
    \App\Helpers\ThemeSetting::markResolvedFromRequest();

    ResellerSetting::set($ufs->id, SiteContactDetails::ADDRESS, "Plot 123, Kampala Road\nP.O. Box 5678, Kampala");

    $url = SiteContactDetails::mapEmbedUrl();

    // The keyless embed form, so nobody needs a Maps Platform key to show where they are.
    expect($url)->toContain('maps.google.com/maps?q=')
        ->and($url)->toContain('output=embed')
        // Street level. Left to itself Google zoomed out to the whole country for an address
        // it could not pin, which shows a family nothing about where to bring flowers.
        ->and($url)->toContain('z=16')
        // The address is a textarea; its newlines have to collapse into one query.
        ->and($url)->not->toContain('%0A')
        ->and($url)->toContain('Kampala');
});

it('still prefers an embed the business pasted', function () {
    // The escape hatch: a pinned entrance or a satellite layer is theirs to choose.
    $ufs = footerTenant('ufs-map2');
    app()->instance(Reseller::class, $ufs);
    \App\Helpers\ThemeSetting::markResolvedFromRequest();

    ResellerSetting::set($ufs->id, SiteContactDetails::ADDRESS, 'Plot 123, Kampala Road');
    ResellerSetting::set($ufs->id, SiteContactDetails::MAP_EMBED, 'https://www.google.com/maps/embed?pb=!1m18!2m3');

    expect(SiteContactDetails::mapEmbedUrl())->toBe('https://www.google.com/maps/embed?pb=!1m18!2m3');
});

it('falls back to the address when a pasted embed is unusable', function () {
    // A truncated paste is exactly what produced "Invalid 'pb' parameter" on a live site.
    // Better to show the right place from the address than a broken frame.
    $ufs = footerTenant('ufs-map3');
    app()->instance(Reseller::class, $ufs);
    \App\Helpers\ThemeSetting::markResolvedFromRequest();

    ResellerSetting::set($ufs->id, SiteContactDetails::ADDRESS, 'Plot 123, Kampala Road');
    ResellerSetting::set($ufs->id, SiteContactDetails::MAP_EMBED, 'https://evil.example.com/embed');

    expect(SiteContactDetails::mapEmbedUrl())->toContain('maps.google.com/maps?q=');
});

it('shows no map when there is no address and no embed', function () {
    $ufs = footerTenant('ufs-map4');
    app()->instance(Reseller::class, $ufs);
    \App\Helpers\ThemeSetting::markResolvedFromRequest();

    expect(SiteContactDetails::mapEmbedUrl())->toBeNull();
});
