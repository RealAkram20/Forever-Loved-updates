@extends('layouts.app')

{{--
    The reseller's own Appearance page. Replaces the old Branding page, which offered a logo,
    a favicon and one primary colour — so a white-labeled memorial page still rendered the
    platform's buttons, page background, fonts, CTA banner and dark theme.

    The colour sections are the same partial the platform admin page uses, so the two forms
    can never drift. What differs is deliberate: nothing here is required (blank = inherit the
    platform's value), and font *uploads* stay admin-only because the catalogue is shared.
--}}

@section('content')
    <x-common.page-breadcrumb pageTitle="Appearance" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="mb-1 text-sm font-medium text-red-700 dark:text-red-400">Please fix the following:</p>
            <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $customNames = array_column($customFonts, 'name');
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 dark:bg-gray-900/80 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden';
        $overrideCount = count($overrides);
    @endphp

    <div class="space-y-6">
        {{-- Where their pages live, and how to embed one. Kept at the top because it is the
             thing a reseller most often comes here to copy. --}}
        <x-common.component-card title="Your Address" desc="Where your clients' visitors see your branded memorial pages.">
            <x-common.reseller-address :reseller="$reseller" class="max-w-md" />

            @if ($reseller->tier?->feature_embedding)
                {{-- The hand-edit-a-placeholder snippet used to live here, and pasting it
                     verbatim produced a 404 on the embedder's site. The builder writes a
                     correct snippet from real memorials; this just points at it. --}}
                <div class="mt-5">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Embed memorials on your own website</p>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Show a single memorial, a hand-picked set, or your whole directory on any site you own — the builder writes the copy-paste snippet for you.
                    </p>
                    <a href="{{ route('reseller.embed') }}" class="btn btn-secondary btn-sm">Open the embed builder</a>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Embedding isn't included in your current plan.</p>
            @endif
        </x-common.component-card>

        {{-- Assets. A separate form from the colours below because it is multipart, and
             because a reseller changing a logo should not have to re-submit 30 colours. --}}
        {{-- Plain "&", not "&amp;": component-card escapes the prop on output, so a
             pre-escaped entity here renders visibly as "&amp;" in the heading. --}}
        <x-common.component-card title="Logo & Favicon" desc="Shown on your dashboard, your clients' memorial pages, and in the browser tab.">
            <form action="{{ route('reseller.appearance.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @foreach ([
                        ['field' => 'logo', 'column' => 'logo_path', 'label' => 'Logo', 'hint' => 'Used on light backgrounds. PNG, JPG or WebP, up to 2 MB.', 'box' => 'bg-white', 'square' => false],
                        ['field' => 'logo_dark', 'column' => 'logo_dark_path', 'label' => 'Dark-mode logo', 'hint' => 'Optional. Without it your main logo is used in dark mode, which can be unreadable if it is dark artwork.', 'box' => 'bg-gray-900', 'square' => false],
                        ['field' => 'favicon', 'column' => 'favicon_path', 'label' => 'Favicon', 'hint' => 'The small square browser-tab icon. Falls back to your logo.', 'box' => 'bg-white', 'square' => true],
                    ] as $asset)
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $asset['label'] }}</label>

                            <div class="mb-3 flex h-20 items-center justify-center overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 {{ $asset['box'] }} p-3">
                                @if ($reseller->{$asset['column']})
                                    {{-- Sized with inline max-height/max-width rather than utility
                                         classes: an uploaded logo can be any pixel size, and a
                                         class that is missing from the compiled CSS build leaves
                                         the image at natural size, overflowing the whole row.
                                         The favicon is shown at its real 32px square so it reads
                                         as a tab icon instead of a stretched banner. --}}
                                    <img src="{{ \App\Helpers\StorageHelper::publicUrl($reseller->{$asset['column']}) }}"
                                        alt="Current {{ $asset['label'] }}"
                                        style="{{ $asset['square'] ? 'height:32px;width:32px;object-fit:contain;' : 'max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;' }}" />
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">Not set</span>
                                @endif
                            </div>

                            <input type="file" name="{{ $asset['field'] }}" accept="image/png,image/jpeg,image/webp{{ $asset['field'] === 'favicon' ? ',image/x-icon,.ico' : '' }}"
                                class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-400" />
                            @error($asset['field']) <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror

                            @if ($reseller->{$asset['column']})
                                <label class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <input type="checkbox" name="remove_{{ $asset['field'] }}" value="1"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-600" />
                                    Remove this
                                </label>
                            @endif

                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $asset['hint'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- SVG is deliberately absent from the accepted types: it can carry script, is
                     stored on the public disk and served same-origin, and renders as branding
                     on the memorial pages of the families this reseller serves. --}}
                <p class="text-xs text-gray-400 dark:text-gray-500">PNG, JPG and WebP only — SVG uploads aren't accepted for security reasons.</p>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-md">Save assets</button>
                </div>
            </form>
        </x-common.component-card>

        {{-- Colours and fonts. One page-wide Light/Dark mode drives every section; both modes'
             fields stay in the DOM (x-show, not x-if) so the hidden mode still submits. --}}
        <form action="{{ route('reseller.appearance.update') }}" method="POST" class="space-y-6" x-data="{ mode: 'light' }">
            @csrf
            @method('PUT')

            <x-common.component-card title="Fonts" desc="Applied across your dashboard and your clients' memorial pages. Leave on the theme default to change nothing.">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @foreach ([['key' => 'font_body', 'label' => 'Body font', 'hint' => 'Paragraphs, buttons, forms — everything that is not a heading.'],
                               ['key' => 'font_heading', 'label' => 'Heading font', 'hint' => 'All page and section headings.']] as $picker)
                        @php $current = old('appearance.'.$picker['key'], $settings['appearance.'.$picker['key']] ?? ''); @endphp
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $picker['key'] }}">{{ $picker['label'] }}</label>
                            @include('pages.settings.partials.font-select', ['id' => $picker['key'], 'name' => 'appearance['.$picker['key'].']', 'current' => $current])
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $picker['hint'] }}</p>
                            <p data-font-preview class="mt-3 rounded-lg border border-gray-100 bg-gray-50/60 px-4 py-3 text-base text-gray-800 dark:border-gray-800 dark:bg-white/[0.02] dark:text-white/90">
                                In loving memory — every life tells a story. AaBbCc 0123456789
                            </p>
                        </div>
                    @endforeach

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="default_theme">Default appearance</label>
                        @php $defaultTheme = old('branding.default_theme', $settings['branding.default_theme'] ?? 'light'); @endphp
                        <select id="default_theme" name="branding[default_theme]" class="{{ $inputClass }}">
                            <option value="light" @selected($defaultTheme === 'light')>Light mode</option>
                            <option value="dark" @selected($defaultTheme === 'dark')>Dark mode</option>
                        </select>
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                            Used for visitors who have not toggled the theme yet. Anyone who already chose keeps their preference.
                        </p>
                    </div>
                </div>

                <p class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4 text-xs text-gray-500 dark:text-gray-400">
                    Need a font that isn't listed? Ask your platform admin to upload it — uploaded fonts are shared with every business on the platform, so they're added centrally.
                </p>
            </x-common.component-card>

            <x-common.component-card title="Colours" desc="Your brand colours, used across your dashboard and your clients' memorial pages. Use the tabs to set each mode.">
                {{-- Anything left matching the platform's value is not stored as yours, so a
                     later platform-wide change still reaches you. Said plainly, because
                     otherwise "why did my colour change?" has no discoverable answer. --}}
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        @if ($overrideCount)
                            You've customised <span class="font-medium text-gray-800 dark:text-white/90">{{ $overrideCount }}</span>
                            {{ \Illuminate\Support\Str::plural('setting', $overrideCount) }}. Anything you leave matching the
                            platform's value keeps following it if the platform changes.
                        @else
                            You're currently inheriting every colour and font from the platform. Change any of them below to make it yours.
                        @endif
                    </p>
                    @if ($overrideCount)
                        <button type="submit" form="reseller-appearance-reset" class="btn btn-secondary btn-sm"
                            onclick="return confirm('Reset every colour and font back to the platform defaults? Your logo and favicon are not affected.')">
                            Reset to platform defaults
                        </button>
                    @endif
                </div>

                @include('pages.settings.appearance._colors')
            </x-common.component-card>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-md">Save appearance</button>
            </div>
        </form>

        {{-- Outside the form above, since a nested form is invalid HTML and the reset button
             is rendered inside that form's markup via form="". --}}
        <form id="reseller-appearance-reset" action="{{ route('reseller.appearance.reset') }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </div>

    @include('pages.settings.appearance._font-preview-script')
@endsection
