<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AppearanceHelper;
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

    private const FONT_EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    public function index()
    {
        return view('pages.settings.appearance', [
            'title' => 'Appearance',
            'googleFonts' => config('google-fonts', []),
            'customFonts' => AppearanceHelper::customFonts(),
            'settings' => SystemSetting::getByGroup('appearance'),
        ]);
    }

    public function update(Request $request)
    {
        $hex = ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        $rules = [
            'appearance.font_body' => ['nullable', 'string', 'max:80'],
            'appearance.font_heading' => ['nullable', 'string', 'max:80'],
        ];
        foreach (self::TEXT_COLOR_KEYS as $key) {
            $rules[$key] = $hex;
        }

        $request->validate($rules, [
            'regex' => 'The :attribute must be a valid hex color, e.g. #1f2937.',
        ]);

        $validFamilies = array_merge(
            [''],
            AppearanceHelper::googleFamilies(),
            array_column(AppearanceHelper::customFonts(), 'name')
        );

        foreach (['appearance.font_body', 'appearance.font_heading'] as $key) {
            $family = AppearanceHelper::sanitizeFamily($request->input($key, ''));
            if (! in_array($family, $validFamilies, true)) {
                return back()->withErrors([$key => 'Pick a font from the list or upload it first.'])->withInput();
            }
            SystemSetting::set($key, $family);
        }

        foreach (self::TEXT_COLOR_KEYS as $key) {
            SystemSetting::set($key, (string) $request->input($key, ''));
        }

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
