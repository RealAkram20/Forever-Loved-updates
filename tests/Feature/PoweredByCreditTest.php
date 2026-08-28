<?php

use App\Models\Reseller;
use App\Models\SystemSetting;
use App\Models\Theme;
use App\Models\User;
use App\Themes\ThemeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The one line on a white-labeled site that is ours.
 *
 * Everything else in a reseller's footer resolves through ThemeSetting and comes out theirs —
 * that is the whole point of the helper. This credit has to do the exact opposite, and the trap
 * is that the obvious way to write it (BrandingHelper::logoUrl()) is tenant-aware: it would put
 * the funeral home's own logo next to the words "Powered by", on their own site, which is both
 * meaningless and — to anyone who knows both marks — misleading about who runs the platform.
 *
 * The link has the same shape of trap. On a reseller host AppServiceProvider has re-rooted URL
 * generation to their domain, so route('home') would send a visitor back to the page they are
 * already standing on rather than to us.
 */
function creditTenant(string $slug, ?string $template = null): Reseller
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
 * Two logos on disk that are genuinely different files, so "did it print ours or theirs?" is a
 * question the assertions can actually answer. Without real files publicDiskPathUrl() falls back
 * to the same bundled default for both and the interesting failure passes.
 */
function creditLogos(Reseller $reseller): array
{
    Storage::fake('public');
    Storage::disk('public')->put('branding/platform-mark.png', 'ours');
    Storage::disk('public')->put('branding/tenant-mark.png', 'theirs');

    SystemSetting::set('branding.logo_path', 'branding/platform-mark.png');
    SystemSetting::set('branding.logo_dark_path', 'branding/platform-mark.png');
    $reseller->update(['logo_path' => 'branding/tenant-mark.png', 'logo_dark_path' => 'branding/tenant-mark.png']);

    return ['platform-mark.png', 'tenant-mark.png'];
}

it('credits the platform on a reseller site', function () {
    $ufs = creditTenant('credit-basic');
    [$ours] = creditLogos($ufs);

    $html = $this->get('/r/'.$ufs->slug)->assertOk()->getContent();

    expect(str_contains($html, 'Powered by'))->toBeTrue('the credit should appear on a tenant site')
        ->and(str_contains($html, $ours))->toBeTrue('and it should carry our mark');
});

it('credits the platform on a themed reseller site too', function () {
    // The template ships its own footer, so this is a second view that has to include it —
    // exactly the kind of thing a template fork silently drops.
    $ufs = creditTenant('credit-dg', 'dignified');
    [$ours] = creditLogos($ufs);

    $html = $this->get('/r/'.$ufs->slug)->assertOk()->getContent();

    expect(str_contains($html, 'Powered by'))->toBeTrue('the themed footer should credit us as well')
        ->and(str_contains($html, $ours))->toBeTrue('with our mark, not the template default');
});

it('never prints the reseller own logo as the powered-by mark', function () {
    // The failure this exists for. BrandingHelper::logoUrl() resolves through ThemeSetting, so
    // on their host it answers with their file — "Powered by [their own logo]".
    $ufs = creditTenant('credit-mark');
    [$ours, $theirs] = creditLogos($ufs);

    $html = $this->get('/r/'.$ufs->slug)->assertOk()->getContent();

    // Their mark is on the page — it is their header — so this asks a narrower question: what
    // sits inside the credit itself.
    expect(preg_match('#Powered by.*?<img[^>]+src="([^"]+)"#s', $html, $m))->toBe(1)
        ->and(str_contains($m[1], $ours))->toBeTrue('the credit must use the platform mark')
        ->and(str_contains($m[1], $theirs))->toBeFalse('and never the tenant one');
});

it('points the credit at the platform, not back at the site it is on', function () {
    $ufs = creditTenant('credit-link');
    creditLogos($ufs);

    $html = $this->get('/r/'.$ufs->slug)->assertOk()->getContent();

    expect(preg_match('#<a href="([^"]+)"[^>]*>\s*<span[^>]*>Powered by#s', $html, $m))->toBe(1)
        ->and($m[1])->toBe(rtrim((string) config('app.url'), '/'))
        // A visitor here is usually mid-memorial; a footer credit is not worth their place.
        ->and(str_contains($html, 'rel="noopener noreferrer"'))->toBeTrue();
});

it('says nothing on our own site', function () {
    // "Powered by ourselves" is not a sentence.
    expect(str_contains($this->get('/')->assertOk()->getContent(), 'Powered by'))->toBeFalse();
});
