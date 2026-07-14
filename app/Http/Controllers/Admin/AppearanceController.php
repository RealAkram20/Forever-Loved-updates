<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AppearanceHelper;
use App\Helpers\BrandingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SystemSetting;

class AppearanceController extends Controller
{
    private const TEXT_COLOR_KEYS = [
        'appearance.text_heading_light', 'appearance.text_heading_dark',
        'appearance.text_body_light', 'appearance.text_body_dark',
        'appearance.text_muted_light', 'appearance.text_muted_dark',
    ];

    /**
     * Branding keys holding a color (moved here from General Settings). They
     * are interpolated into a <style> block, so they are validated as literal
     * hex and never as free-form strings.
     */
    private const BRANDING_COLOR_KEYS = [
        'branding.primary_color', 'branding.secondary_color', 'branding.accent_color',
        'branding.bg_light', 'branding.bg_dark',
        'branding.primary_light', 'branding.primary_dark',
        'branding.accent_light', 'branding.accent_dark',
        'branding.button1_color', 'branding.button1_text_color',
        'branding.button1_color_dark', 'branding.button1_text_color_dark',
        'branding.button2_color', 'branding.button2_text_color',
        'branding.button2_color_dark', 'branding.button2_text_color_dark',
        'branding.cta_bg_light', 'branding.cta_bg_dark',
        'branding.cta_btn1_color', 'branding.cta_btn1_text_color',
        'branding.cta_btn1_color_dark', 'branding.cta_btn1_text_color_dark',
        'branding.cta_btn2_color', 'branding.cta_btn2_text_color',
        'branding.cta_btn2_color_dark', 'branding.cta_btn2_text_color_dark',
    ];

    /** Branding keys holding a 0-100 percentage rather than a color. */
    private const BRANDING_PERCENT_KEYS = [
        'branding.cta_overlay_light', 'branding.cta_overlay_dark',
    ];

    private const FONT_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    public function index()
    {
        return view('pages.settings.appearance', [
            'title' => 'Appearance',
            'googleFonts' => config('google-fonts', []),
            'customFonts' => AppearanceHelper::customFonts(),
            // The color pickers read branding.* keys, the font/text sections appearance.*
            'settings' => array_merge(
                SystemSetting::getByGroup('branding'),
                SystemSetting::getByGroup('appearance')
            ),
        ]);
    }

    public function update(Request $request)
    {
        $hex = ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $rules = [
            'appearance.font_body' => ['nullable', 'string', 'max:80'],
            'appearance.font_heading' => ['nullable', 'string', 'max:80'],
            'branding.default_theme' => ['required', 'in:light,dark'],
        ];
        foreach (array_keys(AppearanceHelper::TYPE_ROLES) as $role) {
            $rules["appearance.role_{$role}_font"] = ['nullable', 'string', 'max:80'];
            $rules["appearance.role_{$role}_color_light"] = $hex;
            $rules["appearance.role_{$role}_color_dark"] = $hex;
        }
        foreach (self::TEXT_COLOR_KEYS as $key) {
            $rules[$key] = $hex;
        }
        foreach (self::BRANDING_COLOR_KEYS as $key) {
            $rules[$key] = $hex;
        }
        foreach (self::BRANDING_PERCENT_KEYS as $key) {
            $rules[$key] = ['nullable', 'integer', 'between:0,100'];
        }
        foreach (['branding.primary_color', 'branding.secondary_color', 'branding.accent_color'] as $key) {
            $rules[$key][0] = 'required';
        }

        $request->validate($rules, [
            'regex' => 'The :attribute must be a valid hex color, e.g. #1f2937.',
        ]);

        $validFamilies = array_merge(
            [''],
            AppearanceHelper::googleFamilies(),
            array_column(AppearanceHelper::customFonts(), 'name')
        );

        $fontKeys = ['appearance.font_body', 'appearance.font_heading'];
        foreach (array_keys(AppearanceHelper::TYPE_ROLES) as $role) {
            $fontKeys[] = "appearance.role_{$role}_font";
        }
        foreach ($fontKeys as $key) {
            $family = AppearanceHelper::sanitizeFamily($request->input($key, ''));
            if (! in_array($family, $validFamilies, true)) {
                return back()->withErrors([$key => 'Pick a font from the list or upload it first.'])->withInput();
            }
            SystemSetting::set($key, $family);
        }

        $colorKeys = self::TEXT_COLOR_KEYS;
        foreach (array_keys(AppearanceHelper::TYPE_ROLES) as $role) {
            $colorKeys[] = "appearance.role_{$role}_color_light";
            $colorKeys[] = "appearance.role_{$role}_color_dark";
        }
        foreach ($colorKeys as $key) {
            SystemSetting::set($key, (string) $request->input($key, ''));
        }

        foreach (self::BRANDING_COLOR_KEYS as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, $request->input($key));
            }
        }
        foreach (self::BRANDING_PERCENT_KEYS as $key) {
            if ($request->has($key)) {
                SystemSetting::set($key, BrandingHelper::clampPercent($request->input($key)));
            }
        }
        SystemSetting::set('branding.default_theme', $request->input('branding.default_theme'));

        return redirect()->route('settings.appearance')->with('success', 'Appearance saved. Reload any open pages to see it.');
    }

    public function storeFont(Request $request)
    {
        $request->validate([
            'font_name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]*$/'],
            'font_file' => ['required', 'file', 'max:5120'],
        ], [
            'font_name.regex' => 'Use only letters, numbers, spaces and hyphens in the font name, e.g. Brand Sans.',
        ]);

        $file = $request->file('font_file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::FONT_EXTENSIONS, true)) {
            return back()->withErrors(['font_file' => 'Upload a .woff2, .woff, .ttf or .otf file.'])->withInput();
        }

        $name = AppearanceHelper::sanitizeFamily($request->input('font_name'));
        $fonts = AppearanceHelper::customFonts();
        if (in_array($name, array_column($fonts, 'name'), true)) {
            return back()->withErrors(['font_name' => "A font named \"{$name}\" already exists. Delete it first or pick another name."])->withInput();
        }

        $path = $file->storeAs('fonts', Str::slug($name).'-'.Str::random(6).'.'.$extension, 'public');

        $fonts[] = ['name' => $name, 'path' => $path, 'format' => $extension];
        SystemSetting::set('appearance.custom_fonts', json_encode($fonts));

        return redirect()->route('settings.appearance')->with('success', "Font \"{$name}\" uploaded. Select it below to use it.");
    }

    public function destroyFont(Request $request, int $index)
    {
        $fonts = AppearanceHelper::customFonts();
        if (! isset($fonts[$index])) {
            return redirect()->route('settings.appearance');
        }

        [$removed] = array_splice($fonts, $index, 1);
        Storage::disk('public')->delete($removed['path']);
        SystemSetting::set('appearance.custom_fonts', json_encode(array_values($fonts)));

        // A deleted font that is still selected would silently fall back —
        // clear the selection so the admin sees the real state.
        foreach (['appearance.font_body', 'appearance.font_heading'] as $key) {
            if (SystemSetting::get($key) === $removed['name']) {
                SystemSetting::set($key, '');
            }
        }

        return redirect()->route('settings.appearance')->with('success', "Font \"{$removed['name']}\" deleted.");
    }
}
