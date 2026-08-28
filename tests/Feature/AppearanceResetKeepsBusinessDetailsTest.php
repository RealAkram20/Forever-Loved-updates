<?php

use App\Helpers\AppearanceKeys;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\User;
use App\Support\SiteContactDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * "Reset colours" must reset colours.
 *
 * Every reseller setting shares one table — the palette and the fonts sit beside the
 * business's phone numbers, postal address, opening hours, map embed and social links. The
 * reset ran ResellerSetting::forgetAll(), which deletes the lot.
 *
 * So a funeral home pressed a button labelled "reset to defaults", was told "Colours and fonts
 * reset to the platform defaults. Your logo and favicon are unchanged", and lost how families
 * reach them: the phone number, the address, the hours, the map. Nothing warned them, nothing
 * could give it back, and the message they were shown said the opposite of what happened.
 *
 * The keys this page owns are already enumerated — AppearanceKeys::resellerWritable() is the
 * same list the form writes through. Reset now deletes exactly those.
 */
function resetTenant(): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => 'ufs-reset',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
        'primary_color' => '#BB1520',
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

/** ResellerSetting has no get(); allFor() is the read side. */
function settingValue(int $resellerId, string $key): ?string
{
    return App\Models\ResellerSetting::allFor($resellerId)[$key]["value"] ?? null;
}

/** The facts a family needs, exactly as the settings form saves them. */
function saveBusinessDetails(Reseller $reseller): array
{
    $details = [
        SiteContactDetails::PHONE => '+256 200 123 456',
        SiteContactDetails::PHONE_ALT => '+256 700 987 654',
        SiteContactDetails::ADDRESS => "Plot 123, Kampala Road\nP.O. Box 5678, Kampala",
        SiteContactDetails::HOURS => "Mon - Fri: 8:00am - 5:00pm\nSat: 9:00am - 1:00pm",
        SiteContactDetails::MAP_EMBED => 'https://www.google.com/maps/embed?pb=!1m18!2m3',
        SiteContactDetails::SOCIAL_FACEBOOK => 'https://facebook.com/ufs',
    ];

    foreach ($details as $key => $value) {
        ResellerSetting::set($reseller->id, $key, $value);
    }

    return $details;
}

it('keeps every contact detail when the appearance is reset', function () {
    $ufs = resetTenant();
    $details = saveBusinessDetails($ufs);

    $this->actingAs($ufs->owner)
        ->delete('/reseller/appearance/reset')
        ->assertRedirect(route('reseller.appearance'));

    // The heart of it. Losing any one of these costs a grieving family a phone call they
    // cannot make.
    foreach ($details as $key => $expected) {
        expect(settingValue($ufs->id, $key))->toBe(
            $expected,
            "resetting the appearance destroyed {$key}"
        );
    }
});

it('still resets the colours and fonts it promises to', function () {
    // The other half: the button has to keep doing its job.
    $ufs = resetTenant();

    ResellerSetting::set($ufs->id, 'branding.secondary_color', '#123456');
    ResellerSetting::set($ufs->id, 'appearance.font_heading', 'Comic Sans MS');

    $this->actingAs($ufs->owner)->delete('/reseller/appearance/reset');

    expect(settingValue($ufs->id, 'branding.secondary_color'))->toBeNull()
        ->and(settingValue($ufs->id, 'appearance.font_heading'))->toBeNull()
        // primary_color lives in a column rather than a settings row, and is a colour like
        // any other — leaving it set would make "reset every colour" quietly untrue for the
        // most visible one on the site.
        ->and($ufs->fresh()->primary_color)->toBeNull();
});

it('deletes nothing outside the keys the appearance page owns', function () {
    // Stated as a rule rather than a list, so a setting added next year is covered without
    // anybody remembering this test exists.
    $ufs = resetTenant();

    ResellerSetting::set($ufs->id, 'something.future_setting', 'kept');
    ResellerSetting::set($ufs->id, 'branding.secondary_color', '#123456');

    $this->actingAs($ufs->owner)->delete('/reseller/appearance/reset');

    $remaining = ResellerSetting::where('reseller_id', $ufs->id)->pluck('key')->all();
    $appearance = AppearanceKeys::resellerWritable();

    expect(array_intersect($remaining, $appearance))->toBe([])
        ->and($remaining)->toContain('something.future_setting');
});

it('leaves another reseller settings alone', function () {
    $ufs = resetTenant();

    $other = Reseller::create([
        'name' => 'Other Home',
        'slug' => 'other-reset',
        'owner_user_id' => User::factory()->create()->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    ResellerSetting::set($other->id, 'branding.secondary_color', '#abcdef');

    $this->actingAs($ufs->owner)->delete('/reseller/appearance/reset');

    expect(settingValue($other->id, 'branding.secondary_color'))->toBe('#abcdef');
});
