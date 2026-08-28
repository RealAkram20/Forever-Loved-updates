<?php

use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\User;
use App\Support\SiteContactDetails as CD;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The map value is pasted in by a reseller and rendered inside an iframe on their public site.
 * Without a host check that is an invitation to frame anything at all — a credential form
 * wearing their branding, an ad network, a page that harvests the referrer of every grieving
 * visitor who lands on their contact page.
 *
 * So the allow-list is the point of these tests, not the convenience of accepting a pasted
 * <iframe> snippet.
 */
function contactTenant(string $slug = 'acme'): Reseller
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

    return $reseller->fresh();
}

it('accepts a Google Maps embed pasted as a whole iframe snippet', function () {
    $acme = contactTenant();

    $this->actingAs($acme->owner)->put('/reseller/settings/contact', [
        'contact' => [
            CD::MAP_EMBED => '<iframe src="https://www.google.com/maps/embed?pb=!1m18" width="600"></iframe>',
        ],
    ])->assertSessionHasNoErrors();

    app()->instance(Reseller::class, $acme->fresh());

    // Stored as pasted, but read back as the bare src — asking someone to extract that
    // themselves is how a settings field goes unused.
    expect(CD::mapEmbedUrl())->toBe('https://www.google.com/maps/embed?pb=!1m18');

    app()->forgetInstance(Reseller::class);
});

it('accepts an OpenStreetMap embed', function () {
    expect(CD::isAllowedMapUrl('https://www.openstreetmap.org/export/embed.html?bbox=1,2,3,4'))->toBeTrue();
});

it('refuses anything that is not a map', function (string $url) {
    expect(CD::isAllowedMapUrl($url))->toBeFalse();
})->with([
    'a look-alike host' => 'https://www.google.com.evil.test/maps/embed?pb=1',
    'the right host, the wrong path' => 'https://www.google.com/search?q=phishing',
    'plain http' => 'http://www.google.com/maps/embed?pb=1',
    'an unrelated site' => 'https://example.test/embed',
    'a javascript url' => 'javascript:alert(1)',
    'a data url' => 'data:text/html,<script>alert(1)</script>',
]);

it('rejects a bad map at the settings form rather than storing it', function () {
    $acme = contactTenant();

    $this->actingAs($acme->owner)->put('/reseller/settings/contact', [
        'contact' => [CD::MAP_EMBED => 'https://example.test/not-a-map'],
    ])->assertSessionHasErrors('contact.'.CD::MAP_EMBED);

    expect(ResellerSetting::has($acme->id, CD::MAP_EMBED))->toBeFalse();
});

it('stores the contact details a theme renders', function () {
    $acme = contactTenant();

    $this->actingAs($acme->owner)->put('/reseller/settings/contact', [
        'contact' => [
            CD::PHONE => '+256 200 123 456',
            CD::ADDRESS => "Plot 123, Kampala Road\nP.O. Box 5678",
            CD::HOURS => 'Mon - Fri: 8:00am - 5:00pm',
        ],
    ])->assertSessionHasNoErrors();

    app()->instance(Reseller::class, $acme->fresh());

    expect(CD::get(CD::PHONE))->toBe('+256 200 123 456')
        ->and(CD::lines(CD::get(CD::ADDRESS)))->toBe(['Plot 123, Kampala Road', 'P.O. Box 5678']);

    app()->forgetInstance(Reseller::class);
});

it('clears a detail when the field is emptied', function () {
    $acme = contactTenant();
    ResellerSetting::set($acme->id, CD::PHONE, '+256 200 123 456');

    // Blank is a real choice: the row is then not rendered at all, rather than showing an
    // empty line or a stale number.
    $this->actingAs($acme->owner)->put('/reseller/settings/contact', [
        'contact' => [CD::PHONE => ''],
    ])->assertSessionHasNoErrors();

    expect(ResellerSetting::has($acme->id, CD::PHONE))->toBeFalse();
});

it('keeps one reseller\'s contact details off another\'s site', function () {
    $acme = contactTenant('acme');
    $beta = contactTenant('beta');

    ResellerSetting::set($acme->id, CD::PHONE, '+256 111 111 111');

    app()->instance(Reseller::class, $beta->fresh());
    \App\Helpers\ThemeSetting::forgetThemeTokens();

    expect(CD::get(CD::PHONE))->not->toBe('+256 111 111 111');

    app()->forgetInstance(Reseller::class);
});
