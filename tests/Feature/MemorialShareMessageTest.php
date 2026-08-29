<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\MemorialShareMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The words that travel with a memorial link.
 *
 * A bare URL in a WhatsApp thread asks whoever receives it to work out what it is and what they
 * are meant to do with it. This says both, so the person forwarding it does not have to write
 * the message themselves at the worst moment of their year.
 */
function shareMemorial(array $attributes = []): Memorial
{
    return Memorial::factory()->create(array_merge([
        'reseller_id' => null,
        'user_id' => User::factory()->create()->id,
        'full_name' => 'Wilson Ssekandi Mubiru',
        'birth_year' => 1970,
        'death_year' => 2025,
        'gender' => 'male',
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
    ], $attributes));
}

it('writes the message a family would write', function () {
    $message = MemorialShareMessage::for(shareMemorial());

    expect($message)->toContain('In loving memory of Wilson Ssekandi Mubiru (1970–2025)')
        ->and($message)->toContain('Forever loved, Always 🤎')
        // First name only, because that is how somebody speaks about their own father.
        ->and($message)->toContain("light a candle in Wilson's honour")
        ->and($message)->toContain('keep his legacy alive')
        // Paragraphs, not one run-on line — this lands in a chat window.
        ->and(substr_count($message, "\n\n"))->toBe(3);
});

it('uses an en dash between the years, as a headstone does', function () {
    // The compact listings join on a hyphen; this is prose and takes the dash prose uses.
    expect(MemorialShareMessage::for(shareMemorial()))->toContain('(1970–2025)')
        ->and(MemorialShareMessage::for(shareMemorial()))->not->toContain('(1970-2025)');
});

it('says their when nobody recorded a gender', function () {
    // Gender is optional on a memorial. Guessing it wrong in the message a family forwards to
    // everyone who knew them is a worse failure than sounding slightly formal.
    $message = MemorialShareMessage::for(shareMemorial(['gender' => null]));

    expect($message)->toContain('keep their legacy alive')
        ->and($message)->not->toContain('his legacy')
        ->and($message)->not->toContain('her legacy');
});

it('says her for a woman', function () {
    expect(MemorialShareMessage::for(shareMemorial(['gender' => 'female', 'full_name' => 'Grace Nakato'])))
        ->toContain('keep her legacy alive');
});

it('drops the years rather than printing an empty bracket', function () {
    $message = MemorialShareMessage::for(shareMemorial([
        'birth_year' => null, 'death_year' => null,
        'date_of_birth' => null, 'date_of_passing' => null,
    ]));

    expect($message)->toContain('In loving memory of Wilson Ssekandi Mubiru')
        ->and($message)->not->toContain('()');
});

it('keeps our brand line off a reseller family message', function () {
    // "Forever loved, Always" is our name and our tagline in one line. On a funeral home's own
    // site it is somebody else's marketing inside a grieving family's WhatsApp forward — the
    // same leak as the tagline in their footer, on a surface that travels further.
    SystemSetting::set('branding.tagline', 'Celebrate lives that matter');

    Role::findOrCreate('reseller', 'web');
    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Uganda Funeral Services',
        'slug' => 'ufs-share',
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    app()->instance(Reseller::class, $reseller);
    \App\Helpers\ThemeSetting::markResolvedFromRequest();

    $memorial = shareMemorial(['reseller_id' => $reseller->id]);

    // With no line of their own, the message simply goes without one.
    $bare = MemorialShareMessage::for($memorial);
    expect($bare)->not->toContain('Forever loved')
        ->and($bare)->not->toContain('Celebrate lives that matter')
        ->and($bare)->toContain('In loving memory of')
        ->and($bare)->toContain('keep his legacy alive');

    // And with one, it is theirs.
    // set() clears the tenant's settings cache itself, so nothing else is needed here.
    ResellerSetting::set($reseller->id, 'branding.tagline', 'Compassion, respect and professionalism.');

    expect(MemorialShareMessage::for($memorial))
        ->toContain('Compassion, respect and professionalism. 🤎');
});

it('puts the message on the page for the share buttons to send', function () {
    $memorial = shareMemorial();

    $this->get('/'.$memorial->slug)
        ->assertOk()
        ->assertSee('data-share-message', false)
        ->assertSee('In loving memory of Wilson Ssekandi Mubiru', false);
});
