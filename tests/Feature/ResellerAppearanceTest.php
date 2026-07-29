<?php

use App\Helpers\AppearanceHelper;
use App\Helpers\BrandingHelper;
use App\Helpers\ThemeSetting;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A reseller used to control exactly one colour, so their white-labeled pages rendered their
 * logo and brand hue over the platform's buttons, page background, fonts, CTA banner and dark
 * theme. These cover the resolver that fixed that (App\Helpers\ThemeSetting) and the form
 * that writes it, with particular attention to the two ways it could be dangerous: writing
 * outside the appearance namespace, and getting a non-hex value into a <style> block.
 */
function appearanceTenant(string $slug = 'acme'): Reseller
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

/** Bind the tenant the way ResolveReseller / EnsureResellerActive do at runtime. */
function actAsTenant(Reseller $reseller): void
{
    app()->instance(Reseller::class, $reseller);
}

afterEach(function () {
    app()->forgetInstance(Reseller::class);
});

/*
|--------------------------------------------------------------------------
| Resolution
|--------------------------------------------------------------------------
*/

it('falls back to the platform value when the reseller has set nothing', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');
    actAsTenant(appearanceTenant());

    expect(ThemeSetting::get('branding.bg_light'))->toBe('#ffffff');
});

it('prefers the reseller value over the platform one', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');
    actAsTenant($acme->fresh());

    expect(ThemeSetting::get('branding.bg_light'))->toBe('#111827');
});

it('keeps one reseller appearance out of another', function () {
    $acme = appearanceTenant('acme');
    $beta = appearanceTenant('beta');
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');

    actAsTenant($beta->fresh());

    expect(ThemeSetting::get('branding.bg_light'))->not->toBe('#111827');
});

it('leaves the platform untouched by a reseller override', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');

    // No tenant bound — the platform's own pages.
    expect(ThemeSetting::get('branding.bg_light'))->toBe('#ffffff');
});

it('distinguishes an empty override from an absent one', function () {
    // The platform picked a font; the reseller wants the theme default instead. Falling
    // through on empty would make that choice impossible to express.
    SystemSetting::set('appearance.font_body', 'Inter');
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'appearance.font_body', '');
    actAsTenant($acme->fresh());

    expect(ThemeSetting::get('appearance.font_body'))->toBe('');
    expect(AppearanceHelper::bodyFont())->toBe('');
});

it('casts a reseller integer override to an integer', function () {
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.cta_overlay_light', 40);
    actAsTenant($acme->fresh());

    expect(BrandingHelper::ctaOverlayLight())->toBe(40);
});

/*
|--------------------------------------------------------------------------
| What the resolver unlocked: the whole palette, not just one colour
|--------------------------------------------------------------------------
*/

it('themes the dark-mode brand colour per reseller', function () {
    // The sharpest symptom of the old code: dark mode read branding.secondary_color, which
    // had no tenant override at all, so a reseller's brand vanished entirely in dark mode.
    SystemSetting::set('branding.secondary_color', '#1e3a5f');
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.secondary_color', '#7c3aed');
    actAsTenant($acme->fresh());

    expect(BrandingHelper::secondaryColor())->toBe('#7c3aed');
    expect(BrandingHelper::brandColorCss())->toContain('#7c3aed');
});

it('themes buttons and the CTA banner per reseller', function () {
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.button1_color', '#0f766e');
    ResellerSetting::set($acme->id, 'branding.cta_bg_light', '#be123c');
    actAsTenant($acme->fresh());

    $css = BrandingHelper::brandColorCss();

    expect($css)->toContain('--color-btn-primary: #0f766e')
        ->and($css)->toContain('--color-cta-bg: #be123c');
});

it('themes fonts and visitor text colours per reseller', function () {
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'appearance.font_heading', 'Lora');
    ResellerSetting::set($acme->id, 'appearance.text_body_light', '#334155');
    actAsTenant($acme->fresh());

    $css = AppearanceHelper::css(includeTextColors: true);

    expect($css)->toContain("'Lora'")->and($css)->toContain('#334155');
});

it('uses the reseller primary colour from their column, not a settings row', function () {
    // primary_color predates reseller_settings and is still the source of truth for its key.
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#ea580c']);
    actAsTenant($acme->fresh());

    expect(BrandingHelper::primaryColor())->toBe('#ea580c');
});

it('honours the variant argument on logoUrl', function () {
    // $variant was previously accepted and ignored, so 'dark' silently returned the light mark.
    expect(BrandingHelper::logoUrl('dark'))->toBe(BrandingHelper::logoDarkUrl());
});

/*
|--------------------------------------------------------------------------
| The form
|--------------------------------------------------------------------------
*/

it('renders the reseller appearance page', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->get('http://localhost/reseller/appearance')
        ->assertOk()
        ->assertSee('Appearance')
        // Escaped once, not twice: a pre-escaped "&amp;" in the title prop renders as a
        // visible "&amp;" in the heading, which is what this caught the first time.
        ->assertSee('Logo &amp; Favicon', false)
        ->assertDontSee('&amp;amp;', false)
        ->assertSee('inheriting every colour and font', false);
});

it('saves a reseller colour', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => '#111827']])
        ->assertRedirect(route('reseller.appearance'));

    actAsTenant($acme->fresh());
    expect(ThemeSetting::get('branding.bg_light'))->toBe('#111827');
});

it('does not store a value identical to the platform', function () {
    // Storing it would pin the reseller to today's palette, so a later platform-wide change
    // would leave their pages on the old colour with nothing explaining why.
    SystemSetting::set('branding.bg_light', '#ffffff');
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => '#ffffff']]);

    expect(ResellerSetting::has($acme->id, 'branding.bg_light'))->toBeFalse();
});

it('clears an override when a colour is submitted blank', function () {
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => '']]);

    expect(ResellerSetting::has($acme->id, 'branding.bg_light'))->toBeFalse();
});

it('rejects a colour that is not a hex value', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => 'red; } body { display:none']])
        ->assertSessionHasErrors('branding.bg_light');

    expect(ResellerSetting::has($acme->id, 'branding.bg_light'))->toBeFalse();
});

it('rejects a font outside the catalogue', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['appearance' => ['font_body' => 'Not A Real Font']])
        ->assertSessionHasErrors('appearance.font_body');

    expect(ResellerSetting::has($acme->id, 'appearance.font_body'))->toBeFalse();
});

it('refuses to write a setting outside the appearance namespace', function () {
    // These keys share one namespace with payments, SMTP and AI credentials.
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)->put('http://localhost/reseller/appearance', [
        'branding' => ['bg_light' => '#111827'],
        'payments' => ['pesapal_consumer_key' => 'stolen'],
        'appearance' => ['custom_fonts' => '[{"name":"x","path":"y"}]'],
    ]);

    expect(ResellerSetting::has($acme->id, 'payments.pesapal_consumer_key'))->toBeFalse()
        ->and(ResellerSetting::has($acme->id, 'appearance.custom_fonts'))->toBeFalse();
});

it('never lets a reseller write a platform setting', function () {
    SystemSetting::set('branding.bg_light', '#ffffff');
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => '#111827']]);

    expect(SystemSetting::get('branding.bg_light'))->toBe('#ffffff');
});

it('resets every override without touching uploaded assets', function () {
    $acme = appearanceTenant();
    $acme->update(['logo_path' => 'reseller-branding/logo.png']);
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');
    ResellerSetting::set($acme->id, 'appearance.font_body', 'Lora');

    $this->actingAs($acme->owner)
        ->delete('http://localhost/reseller/appearance/reset')
        ->assertRedirect(route('reseller.appearance'));

    expect(ResellerSetting::allFor($acme->id))->toBeEmpty()
        ->and($acme->fresh()->logo_path)->toBe('reseller-branding/logo.png');
});

it('redirects the retired branding url to appearance', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->get('http://localhost/reseller/branding')
        ->assertRedirect(route('reseller.appearance'));
});

it('offers appearance in the reseller sidebar, not branding', function () {
    $acme = appearanceTenant();

    $this->actingAs($acme->owner)
        ->get('http://localhost/dashboard')
        ->assertOk()
        ->assertSee(url('/reseller/appearance'));
});

it('keeps the reseller appearance page away from a non-reseller', function () {
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->get('http://localhost/reseller/appearance')->assertForbidden();
});

it('stops one reseller saving appearance for another', function () {
    $acme = appearanceTenant('acme');
    $beta = appearanceTenant('beta');

    // There is no reseller id in the request at all — it comes from the authenticated user —
    // so this asserts the shape that makes cross-tenant writes impossible by construction.
    $this->actingAs($beta->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['bg_light' => '#111827']]);

    expect(ResellerSetting::has($acme->id, 'branding.bg_light'))->toBeFalse()
        ->and(ResellerSetting::has($beta->id, 'branding.bg_light'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The admin page must still work after the shared-partial extraction
|--------------------------------------------------------------------------
*/

it('still renders the platform appearance page', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('http://localhost/settings/appearance')
        ->assertOk()
        ->assertSee('Colors')
        ->assertSee('CTA Banner')
        ->assertSee('Uploaded fonts');
});

it('still saves platform appearance to system settings', function () {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->put('http://localhost/settings/appearance', [
        'branding' => [
            'primary_color' => '#123456',
            'secondary_color' => '#1e3a5f',
            'accent_color' => '#f59e0b',
            'default_theme' => 'light',
        ],
    ])->assertRedirect(route('settings.appearance'));

    expect(SystemSetting::get('branding.primary_color'))->toBe('#123456');
});

/*
|--------------------------------------------------------------------------
| The embeddable widget — feature_embedding is billable, so it must be theirs
|--------------------------------------------------------------------------
*/

it('renders the embed widget in the reseller palette', function () {
    $acme = appearanceTenant();
    $acme->tier()->associate(ResellerTier::create([
        'name' => 'Pro', 'slug' => 'pro', 'feature_embedding' => true, 'is_active' => true,
    ]))->save();

    ResellerSetting::set($acme->id, 'branding.button1_color', '#0f766e');

    $client = User::factory()->create(['reseller_id' => $acme->id]);
    Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe', 'full_name' => 'Jane Doe',
        'title' => 'x', 'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->get('http://localhost/widget/jane-doe')
        ->assertOk()
        // The widget had no branding hooks at all before: hardcoded fonts, hardcoded #465fff.
        ->assertSee('--color-btn-primary: #0f766e', false)
        ->assertSee('embed-cta', false)
        ->assertDontSee('background:#465fff', false);
});

it('keeps the platform palette on a direct platform embed', function () {
    SystemSetting::set('branding.button1_color', '#465fff');
    $user = User::factory()->create(['reseller_id' => null]);
    Memorial::create([
        'user_id' => $user->id, 'slug' => 'jane-doe', 'full_name' => 'Jane Doe',
        'title' => 'x', 'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->get('http://localhost/widget/jane-doe')
        ->assertOk()
        ->assertSee('--color-btn-primary: #465fff', false);
});

it('does not put the platform logo on a reseller embed', function () {
    // A reseller with no logo of their own must show none, not ours.
    SystemSetting::set('branding.logo_path', 'branding/platform-logo.png');
    $acme = appearanceTenant();
    $acme->tier()->associate(ResellerTier::create([
        'name' => 'Pro', 'slug' => 'pro', 'feature_embedding' => true, 'is_active' => true,
    ]))->save();

    $client = User::factory()->create(['reseller_id' => $acme->id]);
    Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe', 'full_name' => 'Jane Doe',
        'title' => 'x', 'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    $this->get('http://localhost/widget/jane-doe')
        ->assertOk()
        ->assertDontSee('platform-logo.png', false);
});

it('carries a reseller light button colour into dark mode when they set no dark value', function () {
    // The platform has a stored value for every dark key, so a plain lookup would beat the
    // reseller's own light choice — their green in light mode, our blue in dark.
    SystemSetting::set('branding.button1_color', '#465fff');
    SystemSetting::set('branding.button1_color_dark', '#465fff');
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.button1_color', '#0f766e');
    actAsTenant($acme->fresh());

    expect(BrandingHelper::button1ColorDark())->toBe('#0f766e');
});

it('respects an explicit reseller dark button colour', function () {
    $acme = appearanceTenant();
    ResellerSetting::set($acme->id, 'branding.button1_color', '#0f766e');
    ResellerSetting::set($acme->id, 'branding.button1_color_dark', '#134e4a');
    actAsTenant($acme->fresh());

    expect(BrandingHelper::button1ColorDark())->toBe('#134e4a');
});

it('leaves the platform dark button colour alone with no tenant', function () {
    SystemSetting::set('branding.button1_color', '#465fff');
    SystemSetting::set('branding.button1_color_dark', '#1e3a5f');

    expect(BrandingHelper::button1ColorDark())->toBe('#1e3a5f');
});

/*
|--------------------------------------------------------------------------
| primary_color lives in a column, not a settings row
|--------------------------------------------------------------------------
| It predates reseller_settings. The form read only the rows, so a reseller whose colour was
| in the column saw the *platform's* in the picker while their own rendered on the site — and
| saving could not fix it: the posted value matched the platform, so it was discarded as "not
| an override" while the column kept winning. The field was inert.
*/

it('shows the reseller column primary colour in the form, not the platform one', function () {
    SystemSetting::set('branding.primary_color', '#465fff');
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#bddc9c']);

    $this->actingAs($acme->owner)
        ->get('http://localhost/reseller/appearance')
        ->assertOk()
        ->assertSee('#bddc9c', false);
});

it('saves a new primary colour to the column the resolver reads', function () {
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#bddc9c']);

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['primary_color' => '#0f766e']]);

    expect($acme->fresh()->primary_color)->toBe('#0f766e');

    actAsTenant($acme->fresh());
    expect(BrandingHelper::primaryColor())->toBe('#0f766e');
});

it('clears the primary colour column when set back to the platform value', function () {
    SystemSetting::set('branding.primary_color', '#465fff');
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#bddc9c']);

    $this->actingAs($acme->owner)
        ->put('http://localhost/reseller/appearance', ['branding' => ['primary_color' => '#465fff']]);

    expect($acme->fresh()->primary_color)->toBeNull();
});

it('resets the primary colour column too', function () {
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#bddc9c', 'logo_path' => 'reseller-branding/logo.png']);
    ResellerSetting::set($acme->id, 'branding.bg_light', '#111827');

    $this->actingAs($acme->owner)->delete('http://localhost/reseller/appearance/reset');

    // "Reset every colour" would be quietly untrue for the most visible one on the site.
    expect($acme->fresh()->primary_color)->toBeNull()
        ->and($acme->fresh()->logo_path)->toBe('reseller-branding/logo.png');
});

it('drives the accent heading colour from the reseller primary colour', function () {
    // The hero's accent word is text-brand-500, i.e. --color-brand-500, i.e. primary.
    $acme = appearanceTenant();
    $acme->update(['primary_color' => '#bddc9c']);
    actAsTenant($acme->fresh());

    expect(BrandingHelper::brandColorCss())->toContain('--color-brand-500: #bddc9c');
});
