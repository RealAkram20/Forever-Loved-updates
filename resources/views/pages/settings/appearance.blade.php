@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Appearance" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $customNames = array_column($customFonts, 'name');
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 dark:bg-gray-900/80 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden';
    @endphp

    <div class="space-y-6">
        {{-- One page-wide mode: every Light/Dark section below follows this tab.
             Both modes' fields stay in the DOM (x-show, not x-if) so the inactive
             mode's hidden inputs still submit. --}}
        <form action="{{ route('settings.appearance.update') }}" method="POST" class="space-y-6" x-data="{ mode: 'light' }">
            @csrf
            @method('PUT')

            {{-- Fonts --}}
            <x-common.component-card title="Fonts" desc="Applied across the whole site — visitor pages and the dashboard. Leave on the theme default (Outfit) to change nothing.">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @foreach ([['key' => 'font_body', 'label' => 'Body font', 'hint' => 'Paragraphs, buttons, forms — everything that is not a heading.'],
                               ['key' => 'font_heading', 'label' => 'Heading font', 'hint' => 'All page and section headings (h1–h6).']] as $picker)
                        @php $current = old('appearance.'.$picker['key'], $settings['appearance.'.$picker['key']] ?? ''); @endphp
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $picker['key'] }}">{{ $picker['label'] }}</label>
                            @include('pages.settings.partials.font-select', ['id' => $picker['key'], 'name' => 'appearance['.$picker['key'].']', 'current' => $current])
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $picker['hint'] }}</p>
                            <p data-font-preview class="mt-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-white/[0.02] px-4 py-3 text-base text-gray-800 dark:text-white/90">
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
                            Used for visitors who have not toggled the theme yet. Anyone who already chose light or dark keeps their saved preference.
                        </p>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Colors --}}
            <x-common.component-card title="Colors" desc="Brand, background, button, CTA and text colors. Use the tabs to set each mode — visitors see the light set in light mode and the dark set in dark mode.">

                {{-- Global Light / Dark tabs --}}
                <div class="mb-8 inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800" role="tablist" aria-label="Color mode">
                    <button type="button" @click="mode = 'light'" role="tab" :aria-selected="mode === 'light'"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="mode === 'light'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Light Mode
                    </button>
                    <button type="button" @click="mode = 'dark'" role="tab" :aria-selected="mode === 'dark'"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="mode === 'dark'
                            ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        Dark Mode
                    </button>
                </div>

                {{-- Brand & accent --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Brand &amp; Accent</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Brand colors used across the application, plus highlight colors for badges, icons and emphasis.</p>
                    <div x-show="mode === 'light'" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Primary', 'name' => 'branding[primary_color]', 'dotName' => 'branding.primary_color', 'default' => '#465fff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Light', 'name' => 'branding[primary_light]', 'dotName' => 'branding.primary_light', 'default' => '#465fff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent', 'name' => 'branding[accent_color]', 'dotName' => 'branding.accent_color', 'default' => '#f59e0b'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent Light', 'name' => 'branding[accent_light]', 'dotName' => 'branding.accent_light', 'default' => '#f59e0b'])
                    </div>
                    <div x-show="mode === 'dark'" x-cloak class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Primary', 'name' => 'branding[secondary_color]', 'dotName' => 'branding.secondary_color', 'default' => '#1e3a5f'])
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Dark', 'name' => 'branding[primary_dark]', 'dotName' => 'branding.primary_dark', 'default' => '#1e3a5f'])
                        @include('pages.settings.partials.color-field', ['label' => 'Accent', 'name' => 'branding[accent_dark]', 'dotName' => 'branding.accent_dark', 'default' => '#f59e0b'])
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Background --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Page Background</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">The base page background for the selected mode.</p>
                    <div x-show="mode === 'light'" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[bg_light]', 'dotName' => 'branding.bg_light', 'default' => '#f9fafb'])
                    </div>
                    <div x-show="mode === 'dark'" x-cloak class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[bg_dark]', 'dotName' => 'branding.bg_dark', 'default' => '#101828'])
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Text colors --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Text Colors</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Applied to the visitor-facing site (home, memorial pages, signup). Leave a color empty to keep the theme default.</p>
                    @foreach (['light', 'dark'] as $tcMode)
                        <div x-show="mode === '{{ $tcMode }}'" @if ($tcMode === 'dark') x-cloak @endif class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                            @foreach ([['role' => 'heading', 'label' => 'Headings', 'hint' => 'Titles and section headings.'],
                                       ['role' => 'body', 'label' => 'Body text', 'hint' => 'Paragraphs and standard text.'],
                                       ['role' => 'muted', 'label' => 'Secondary text', 'hint' => 'Captions, timestamps, helper text.']] as $group)
                                @php
                                    $key = "appearance.text_{$group['role']}_{$tcMode}";
                                    $value = old($key, $settings[$key] ?? '');
                                @endphp
                                <div x-data="{ hex: '{{ $value }}' }">
                                    <label class="mb-2 block text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $key }}">{{ $group['label'] }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="hex || '#888888'" @input="hex = $event.target.value"
                                            class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-1"
                                            aria-label="{{ $group['label'] }} ({{ $tcMode }} mode) color picker" />
                                        <input type="text" id="{{ $key }}" name="appearance[text_{{ $group['role'] }}_{{ $tcMode }}]"
                                            x-model="hex" placeholder="Theme default" maxlength="7"
                                            class="{{ $inputClass }}" />
                                        <button type="button" x-show="hex" @click="hex = ''"
                                            class="shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            aria-label="Clear {{ $group['label'] }} {{ $tcMode }} mode color">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $group['hint'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Per-element typography roles --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Text Elements</h4>
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                        Fine-grained control over specific text across the site: pick a font per element,
                        and a color per element for the mode selected in the tabs above. Empty = theme default.
                    </p>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-100 dark:border-gray-800">
                        @foreach ([
                            ['role' => 'title', 'label' => 'Main heading', 'hint' => 'Big page and section headings, e.g. “Honor Your Loved Ones.”'],
                            ['role' => 'accent', 'label' => 'Heading accent line', 'hint' => 'The highlighted heading line, e.g. “Forever Remembered.”'],
                            ['role' => 'lead', 'label' => 'Lead paragraph', 'hint' => 'The intro text under headings.'],
                            ['role' => 'eyebrow', 'label' => 'Eyebrow label', 'hint' => 'The small uppercase label above headings, e.g. “CELEBRATE LIVES THAT MATTER”.'],
                            ['role' => 'cta_title', 'label' => 'CTA banner heading', 'hint' => 'The heading on the call-to-action banner.'],
                            ['role' => 'cta_body', 'label' => 'CTA banner text', 'hint' => 'The supporting text on the call-to-action banner.'],
                        ] as $tr)
                            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[1.2fr_1fr_1fr] lg:items-end">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $tr['label'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $tr['hint'] }}</p>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="role_{{ $tr['role'] }}_font">Font</label>
                                    @include('pages.settings.partials.font-select', [
                                        'id' => 'role_'.$tr['role'].'_font',
                                        'name' => 'appearance[role_'.$tr['role'].'_font]',
                                        'current' => old('appearance.role_'.$tr['role'].'_font', $settings['appearance.role_'.$tr['role'].'_font'] ?? ''),
                                    ])
                                </div>
                                @foreach (['light', 'dark'] as $trMode)
                                    @php
                                        $key = "appearance.role_{$tr['role']}_color_{$trMode}";
                                        $value = old($key, $settings[$key] ?? '');
                                    @endphp
                                    <div x-show="mode === '{{ $trMode }}'" @if ($trMode === 'dark') x-cloak @endif x-data="{ hex: '{{ $value }}' }">
                                        <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400" for="{{ $key }}">
                                            Color — <span x-text="mode === 'dark' ? 'Dark Mode' : 'Light Mode'"></span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" :value="hex || '#888888'" @input="hex = $event.target.value"
                                                class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-1"
                                                aria-label="{{ $tr['label'] }} ({{ $trMode }} mode) color picker" />
                                            <input type="text" id="{{ $key }}" name="appearance[role_{{ $tr['role'] }}_color_{{ $trMode }}]"
                                                x-model="hex" placeholder="Theme default" maxlength="7"
                                                class="{{ $inputClass }}" />
                                            <button type="button" x-show="hex" @click="hex = ''"
                                                class="shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                                aria-label="Clear {{ $tr['label'] }} {{ $trMode }} mode color">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- Buttons --}}
                <div class="mb-8">
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Buttons</h4>
                    <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                        Every button across the site uses these colors. The glow under each button is derived
                        automatically from its background, so it always matches.
                    </p>

                    @include('pages.settings.partials.button-preview', [
                        'buttons' => [
                            ['label' => 'Primary Button', 'class' => 'btn-primary', 'size' => 'btn-lg', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                            ['label' => 'Secondary', 'class' => 'btn-secondary', 'size' => 'btn-lg', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                            ['label' => 'Medium', 'class' => 'btn-primary', 'size' => 'btn-md', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                            ['label' => 'Small', 'class' => 'btn-secondary', 'size' => 'btn-sm', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                        ],
                        'surface' => null,
                    ])

                    <div x-show="mode === 'light'" class="mt-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Background', 'name' => 'branding[button1_color]', 'dotName' => 'branding.button1_color', 'default' => '#465fff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Text', 'name' => 'branding[button1_text_color]', 'dotName' => 'branding.button1_text_color', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Background', 'name' => 'branding[button2_color]', 'dotName' => 'branding.button2_color', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Text', 'name' => 'branding[button2_text_color]', 'dotName' => 'branding.button2_text_color', 'default' => '#374151'])
                        </div>
                    </div>

                    <div x-show="mode === 'dark'" x-cloak class="mt-6">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Background', 'name' => 'branding[button1_color_dark]', 'dotName' => 'branding.button1_color_dark', 'default' => '#465fff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Primary Text', 'name' => 'branding[button1_text_color_dark]', 'dotName' => 'branding.button1_text_color_dark', 'default' => '#ffffff'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Background', 'name' => 'branding[button2_color_dark]', 'dotName' => 'branding.button2_color_dark', 'default' => '#1f2937'])
                            @include('pages.settings.partials.color-field', ['label' => 'Secondary Text', 'name' => 'branding[button2_text_color_dark]', 'dotName' => 'branding.button2_text_color_dark', 'default' => '#d1d5db'])
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700 mb-8" />

                {{-- CTA Banner --}}
                <div>
                    <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">CTA Banner</h4>
                    <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                        The call-to-action banner sits on its own colored background, so its two buttons are
                        styled independently of the site-wide buttons above.
                    </p>

                    @include('pages.settings.partials.button-preview', [
                        'buttons' => [
                            ['label' => 'Get Started Free', 'class' => 'btn-cta-primary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn1_color]', 'text' => 'branding[cta_btn1_text_color]', 'bg_dark' => 'branding[cta_btn1_color_dark]', 'text_dark' => 'branding[cta_btn1_text_color_dark]'],
                            ['label' => 'View Plans', 'class' => 'btn-cta-secondary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn2_color]', 'text' => 'branding[cta_btn2_text_color]', 'bg_dark' => 'branding[cta_btn2_color_dark]', 'text_dark' => 'branding[cta_btn2_text_color_dark]'],
                        ],
                        'surface' => ['light' => 'branding[cta_bg_light]', 'dark' => 'branding[cta_bg_dark]'],
                    ])

                    <div x-show="mode === 'light'" class="mt-6 space-y-6">
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_light]', 'dotName' => 'branding.cta_bg_light', 'default' => '#465fff'])
                                @include('pages.settings.partials.range-field', [
                                    'label' => 'Text Legibility',
                                    'name' => 'branding[cta_overlay_light]',
                                    'dotName' => 'branding.cta_overlay_light',
                                    'default' => 0,
                                    'help' => 'Fades the banner background over its artwork, behind the headline. Raise it until the text reads clearly; 0 shows the artwork untouched.',
                                ])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 1 &mdash; &ldquo;Get Started Free&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn1_color]', 'dotName' => 'branding.cta_btn1_color', 'default' => '#ffffff'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn1_text_color]', 'dotName' => 'branding.cta_btn1_text_color', 'default' => '#465fff'])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 2 &mdash; &ldquo;View Plans&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn2_color]', 'dotName' => 'branding.cta_btn2_color', 'default' => '#3641f5'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn2_text_color]', 'dotName' => 'branding.cta_btn2_text_color', 'default' => '#ffffff'])
                            </div>
                        </div>
                    </div>

                    <div x-show="mode === 'dark'" x-cloak class="mt-6 space-y-6">
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_dark]', 'dotName' => 'branding.cta_bg_dark', 'default' => '#3641f5'])
                                @include('pages.settings.partials.range-field', [
                                    'label' => 'Text Legibility',
                                    'name' => 'branding[cta_overlay_dark]',
                                    'dotName' => 'branding.cta_overlay_dark',
                                    'default' => 55,
                                    'help' => 'Fades the banner background over its artwork, behind the headline. Raise it until the text reads clearly; 0 shows the artwork untouched.',
                                ])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 1 &mdash; &ldquo;Get Started Free&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn1_color_dark]', 'dotName' => 'branding.cta_btn1_color_dark', 'default' => '#ffffff'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn1_text_color_dark]', 'dotName' => 'branding.cta_btn1_text_color_dark', 'default' => '#1e3a5f'])
                            </div>
                        </div>
                        <div>
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Button 2 &mdash; &ldquo;View Plans&rdquo;</p>
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                @include('pages.settings.partials.color-field', ['label' => 'Background', 'name' => 'branding[cta_btn2_color_dark]', 'dotName' => 'branding.cta_btn2_color_dark', 'default' => '#1e3a5f'])
                                @include('pages.settings.partials.color-field', ['label' => 'Text', 'name' => 'branding[cta_btn2_text_color_dark]', 'dotName' => 'branding.cta_btn2_text_color_dark', 'default' => '#ffffff'])
                            </div>
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-md">Save appearance</button>
            </div>
        </form>

        {{-- Uploaded fonts --}}
        <x-common.component-card title="Uploaded fonts" desc="Upload your own font files (.woff2, .woff, .ttf or .otf — .woff2 is smallest and loads fastest). Uploaded fonts appear in the font pickers above.">
            <form action="{{ route('settings.appearance.fonts.store') }}" method="POST" enctype="multipart/form-data"
                class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="font_name">Font name</label>
                    <input type="text" id="font_name" name="font_name" value="{{ old('font_name') }}"
                        placeholder="e.g. Brand Sans" class="{{ $inputClass }}" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="font_file">Font file</label>
                    <input type="file" id="font_file" name="font_file" accept=".woff2,.woff,.ttf,.otf"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-1.5 file:text-sm file:text-white hover:file:bg-brand-600" />
                </div>
                <button type="submit" class="btn btn-primary btn-md">Upload font</button>
            </form>

            @if (count($customFonts))
                <div class="mt-6 divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-100 dark:border-gray-800">
                    @foreach ($customFonts as $i => $font)
                        <div class="flex flex-wrap items-center gap-4 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $font['name'] }}
                                    <span class="ml-2 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] uppercase text-gray-500 dark:text-gray-400">{{ $font['format'] }}</span>
                                </p>
                                <p class="mt-1 truncate text-lg text-gray-700 dark:text-gray-300" style="font-family: '{{ $font['name'] }}', sans-serif;">
                                    In loving memory — AaBbCc 0123456789
                                </p>
                            </div>
                            <form action="{{ route('settings.appearance.fonts.destroy', $i) }}" method="POST"
                                onsubmit="return confirm('Delete the font “{{ $font['name'] }}”? Pages using it fall back to the theme default.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg p-2 text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20" aria-label="Delete font {{ $font['name'] }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">No fonts uploaded yet.</p>
            @endif
        </x-common.component-card>
    </div>

    <script>
        // Live preview: load the picked Google family on demand and render the
        // sample line in it. Uploaded fonts are already available via the
        // site-wide @font-face rules.
        document.querySelectorAll('[data-font-select]').forEach((select) => {
            // Compact pickers have no preview line — the select itself shows the font.
            const preview = select.closest('div').querySelector('[data-font-preview]') || select;
            const loaded = new Set();
            const apply = () => {
                const family = select.value;
                if (!family) {
                    preview.style.fontFamily = '';
                    return;
                }
                const isCustom = !!select.selectedOptions[0]?.hasAttribute('data-custom');
                if (!isCustom && !loaded.has(family)) {
                    loaded.add(family);
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://fonts.googleapis.com/css?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':400,600&display=swap';
                    document.head.appendChild(link);
                }
                preview.style.fontFamily = "'" + family + "', sans-serif";
            };
            select.addEventListener('change', apply);
            apply();
        });

        // Dropdown previews: each <option> carries an inline font-family so the
        // list shows every font in its own face. One combined stylesheet loads
        // all families subsetted (text=) to just the glyphs of the names, so
        // each preview font is a few KB instead of the full family.
        (() => {
            const families = new Set();
            document.querySelectorAll('[data-font-select] option:not([data-custom])').forEach((opt) => {
                if (opt.value) families.add(opt.value);
            });
            if (!families.size) return;
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css?family='
                + [...families].map((f) => f.replace(/ /g, '+')).join('|')
                + '&text=' + encodeURIComponent('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 ')
                + '&display=swap';
            document.head.appendChild(link);
        })();
    </script>
@endsection
