<?php

namespace App\Http\Controllers\Reseller;

use App\Helpers\AppearanceHelper;
use App\Helpers\AppearanceKeys;
use App\Helpers\BrandingHelper;
use App\Helpers\ThemeSetting;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A reseller's own appearance: logo, favicon, and the same colour and font set the platform
 * admin controls globally. Replaces the old Branding page, which offered a logo, a favicon
 * and a single primary colour — so a white-labeled memorial page still rendered the
 * platform's buttons, page background, fonts, CTA banner and dark theme.
 *
 * Mirrors Admin\AppearanceController deliberately: same key vocabulary (AppearanceKeys),
 * same hex validation, same font allow-list. Two differences, both intentional:
 *
 *  - Values land in ResellerSetting, never SystemSetting. A reseller writing a platform
 *    setting would retheme every other tenant and the platform's own site.
 *  - Nothing is required. An untouched or cleared field means "inherit the platform's",
 *    which is why a reseller can reset back to a working palette at any time.
 *
 * Font *uploads* stay admin-only — the catalogue is shared, and accepting font files here
 * would mean every reseller could add fonts to a store all of them read from.
 */
class AppearanceController extends Controller
{
    public function edit(Request $request)
    {
        $reseller = $request->user()->reseller;

        return view('pages.reseller.appearance', [
            'title' => 'Appearance',
            'reseller' => $reseller,
            'googleFonts' => config('google-fonts', []),
            'customFonts' => AppearanceHelper::customFonts(),
            // Field values: this reseller's own choices layered over what they inherit, so an
            // untouched colour picker shows the colour actually in force rather than black.
            // update() then stores only what differs from the platform, so rendering an
            // inherited value here does not silently convert "inherit" into "copy".
            'settings' => array_merge($platform = array_merge(
                SystemSetting::getByGroup('branding'),
                SystemSetting::getByGroup('appearance'),
            ), $overrides = $this->resellerValues($reseller)),
            'platform' => $platform,
            // Which keys are genuinely theirs, so each field can say "Custom" or "Inherited"
            // instead of leaving the reseller guessing which colours they actually control.
            'overrides' => $overrides,
        ]);
    }

    /**
     * This reseller's own choices, keyed by setting key — reseller_settings rows *plus* the
     * column-backed keys ThemeSetting resolves from `resellers` directly.
     *
     * The columns must be included or the form contradicts the site: primary_color has been a
     * column since the reseller program shipped, so reading only reseller_settings showed the
     * platform's colour in the picker while the reseller's own rendered everywhere.
     */
    private function resellerValues(Reseller $reseller): array
    {
        $out = [];

        foreach (ThemeSetting::columnAliases() as $key => $column) {
            if (filled($reseller->{$column})) {
                $out[$key] = $reseller->{$column};
            }
        }

        foreach (ResellerSetting::allFor($reseller->id) as $key => $row) {
            $out[$key] = SystemSetting::castValue((string) ($row['value'] ?? ''), $row['type'] ?? 'string');
        }

        return $out;
    }

    public function update(Request $request)
    {
        $reseller = $request->user()->reseller;

        // Nothing is `required` here, unlike the admin form: a blank field means inherit.
        $hex = ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $rules = ['branding.default_theme' => ['nullable', 'in:light,dark']];

        foreach (AppearanceKeys::fontKeys() as $key) {
            $rules[$key] = ['nullable', 'string', 'max:80'];
        }
        foreach (AppearanceKeys::allColorKeys() as $key) {
            $rules[$key] = $hex;
        }
        foreach (AppearanceKeys::BRANDING_PERCENT_KEYS as $key) {
            $rules[$key] = ['nullable', 'integer', 'between:0,100'];
        }

        $request->validate($rules, [
            'regex' => 'The :attribute must be a valid hex color, e.g. #1f2937.',
        ]);

        // Same allow-list the admin form enforces: a family name is interpolated into a
        // stylesheet URL and a <style> block, so an arbitrary string must never get through.
        $validFamilies = array_merge(
            [''],
            AppearanceHelper::googleFamilies(),
            array_column(AppearanceHelper::customFonts(), 'name'),
        );

        foreach (AppearanceKeys::fontKeys() as $key) {
            $family = AppearanceHelper::sanitizeFamily($request->input($key, ''));

            if (! in_array($family, $validFamilies, true)) {
                return back()->withErrors([$key => 'Pick a font from the list.'])->withInput();
            }
        }

        $this->saveUploads($request, $reseller);

        // resellerWritable() is the gate, not the request: these keys share one namespace with
        // every other settings group, and nothing here should be able to write payments.* or
        // oauth.* by renaming a form field.
        foreach (AppearanceKeys::resellerWritable() as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $this->normalise($key, $request->input($key));

            // Null means "no value" — a cleared colour, a blank percentage. There is no such
            // thing as a blank colour, so this reverts to inheriting the platform's. Anything
            // identical to what they would inherit is likewise not an override: storing it
            // would pin them to today's platform palette, so a later platform-wide change
            // would leave their pages on the old colour with nothing explaining why.
            $clear = $value === null || (string) $value === (string) SystemSetting::get($key, '');

            // Column-backed keys (primary_color) must be written to the column ThemeSetting
            // actually reads, not to a reseller_settings row the resolver would never consult.
            if ($column = ThemeSetting::columnAliases()[$key] ?? null) {
                $reseller->{$column} = $clear ? null : $value;
                $reseller->save();

                continue;
            }

            if ($clear) {
                ResellerSetting::forget($reseller->id, $key);

                continue;
            }

            ResellerSetting::set($reseller->id, $key, $value);
        }

        return redirect()->route('reseller.appearance')
            ->with('success', 'Appearance saved. Reload any open pages to see it.');
    }

    /**
     * One submitted field to a storable value, or null to mean "clear the override".
     *
     * Sanitised here even though validation already ran: every one of these ends up
     * interpolated into a <style> block or a stylesheet URL, and "the request passed
     * validation" is not the same guarantee as "the stored bytes are a literal hex colour".
     */
    private function normalise(string $key, mixed $value): int|string|null
    {
        if (in_array($key, AppearanceKeys::fontKeys(), true)) {
            // '' is a real choice for a font — the theme's own default, even when the platform
            // has picked something. Kept as a value rather than treated as "unset".
            return AppearanceHelper::sanitizeFamily($value);
        }

        if (blank($value)) {
            return null;
        }

        if (in_array($key, AppearanceKeys::BRANDING_PERCENT_KEYS, true)) {
            return BrandingHelper::clampPercent($value);
        }

        if (in_array($key, AppearanceKeys::allColorKeys(), true)) {
            $hex = BrandingHelper::sanitizeHex($value, '');

            return $hex === '' ? null : $hex;
        }

        return (string) $value;
    }

    /**
     * Logo, dark logo and favicon. Explicit mime allow-list rather than the `image` rule,
     * which permits SVG — an SVG can carry script, is stored on the public disk and served
     * same-origin, and is rendered as branding on this reseller's pages *and* their clients'
     * memorials, so accepting one is stored XSS against the families they serve.
     */
    private function saveUploads(Request $request, Reseller $reseller): void
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'logo_dark' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,webp,ico|max:512',
        ]);

        $uploads = [
            'logo' => 'logo_path',
            'logo_dark' => 'logo_dark_path',
            'favicon' => 'favicon_path',
        ];

        foreach ($uploads as $field => $column) {
            if ($request->boolean("remove_{$field}") && $reseller->{$column}) {
                Storage::disk('public')->delete($reseller->{$column});
                $reseller->{$column} = null;
            }

            if (! $request->hasFile($field)) {
                continue;
            }

            if ($reseller->{$column} && Storage::disk('public')->exists($reseller->{$column})) {
                Storage::disk('public')->delete($reseller->{$column});
            }

            $reseller->{$column} = $request->file($field)->store('reseller-branding', 'public');
        }

        $reseller->save();
    }

    /**
     * Drop every override so this reseller inherits the platform palette again.
     *
     * Uploaded assets are deliberately untouched: "my colours went wrong, put them back" is
     * a different intention from "delete my logo", and re-uploading a logo is a bigger ask
     * than re-picking a colour.
     */
    public function reset(Request $request)
    {
        $reseller = $request->user()->reseller;

        // Only what this page owns. Every reseller setting shares one table, so forgetAll()
        // here also deleted the business's phone numbers, address, opening hours, map embed
        // and social links — none of which this button mentions, and none of which could be
        // recovered. A funeral home pressed "reset colours" and lost how families reach them.
        ResellerSetting::forgetKeys($reseller->id, AppearanceKeys::resellerWritable());

        // primary_color is a colour like any other, just stored in a column — leaving it set
        // would make "reset every colour" quietly untrue for the most visible one on the site.
        $reseller->primary_color = null;
        $reseller->save();

        return redirect()->route('reseller.appearance')
            ->with('success', 'Colours and fonts reset to the platform defaults. Your logo and favicon are unchanged.');
    }
}
