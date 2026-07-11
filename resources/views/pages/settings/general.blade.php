@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="General Settings" />

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

    <form action="{{ route('settings.general.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Branding --}}
        <x-common.component-card title="Branding" desc="Configure your application name, tagline and logo.">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">App Name</label>
                    <input type="text" name="branding[app_name]"
                        value="{{ old('branding.app_name', $settings['branding.app_name'] ?? 'Forever Loved') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tagline</label>
                    <input type="text" name="branding[tagline]"
                        value="{{ old('branding.tagline', $settings['branding.tagline'] ?? '') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo</label>
                    <input type="file" name="logo" accept="image/*"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-1.5 file:text-sm file:text-white hover:file:bg-brand-600" />
                    @if (!empty($settings['branding.logo_path']))
                        <p class="mt-1 text-xs text-gray-500">Current: {{ $settings['branding.logo_path'] }}</p>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Dark Logo</label>
                    <input type="file" name="logo_dark" accept="image/*"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-1.5 file:text-sm file:text-white hover:file:bg-brand-600" />
                    @if (!empty($settings['branding.logo_dark_path']))
                        <p class="mt-1 text-xs text-gray-500">Current: {{ $settings['branding.logo_dark_path'] }}</p>
                    @endif
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Favicon</label>
                    <input type="file" name="favicon" accept="image/*"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-1.5 file:text-sm file:text-white hover:file:bg-brand-600" />
                    @if (!empty($settings['branding.favicon_path']))
                        <p class="mt-1 text-xs text-gray-500">Current: {{ $settings['branding.favicon_path'] }}</p>
                    @endif
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Default appearance</label>
                    <select name="branding[default_theme]"
                        class="h-11 w-full max-w-md rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                        @php
                            $defaultTheme = old('branding.default_theme', $settings['branding.default_theme'] ?? 'light');
                        @endphp
                        <option value="light" @selected($defaultTheme === 'light')>Light mode</option>
                        <option value="dark" @selected($defaultTheme === 'dark')>Dark mode</option>
                    </select>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Used for visitors who have not toggled the theme yet. Anyone who already chose light or dark keeps their saved preference.
                    </p>
                </div>
            </div>
        </x-common.component-card>

        @php
            $rawOauthOn = old('oauth.google_enabled');
            if ($rawOauthOn === null) {
                $v = $oauth['oauth.google_enabled'] ?? '0';
                $oauthGoogleOn = $v === true || $v === 1 || $v === '1';
            } else {
                $oauthGoogleOn = in_array($rawOauthOn, [true, 1, '1', 'true'], true);
            }
            $googleSecretStored = ! empty($oauth['oauth.google_client_secret'] ?? '');
        @endphp
        <x-common.component-card title="Sign in with Google" desc="OAuth 2.0 for the login and sign-up pages. Create credentials in Google Cloud Console → APIs & Services → Credentials → OAuth client (Web application).">
            <div class="space-y-5">
                <div class="flex items-center gap-3">
                    <input type="hidden" name="oauth[google_enabled]" value="0" />
                    <input type="checkbox" id="oauth_google_enabled" name="oauth[google_enabled]" value="1"
                        class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900"
                        {{ $oauthGoogleOn ? 'checked' : '' }} />
                    <label for="oauth_google_enabled" class="text-sm font-medium text-gray-800 dark:text-white/90">Enable Google sign-in</label>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Authorized redirect URI in Google Cloud must be exactly:
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ url('/auth/google/callback') }}</code>
                </p>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Client ID</label>
                        <input type="text" name="oauth[google_client_id]" autocomplete="off"
                            value="{{ old('oauth.google_client_id', $oauth['oauth.google_client_id'] ?? '') }}"
                            placeholder="xxxxx.apps.googleusercontent.com"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden font-mono text-xs" />
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Client secret</label>
                        <input type="password" name="oauth[google_client_secret]" autocomplete="new-password"
                            value="{{ $googleSecretStored ? '••••••••' : '' }}"
                            placeholder="GOCSPX-…"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden font-mono text-xs" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave as dots to keep the existing secret. Optional: you can instead set <code class="text-gray-600 dark:text-gray-300">GOOGLE_CLIENT_ID</code> and <code class="text-gray-600 dark:text-gray-300">GOOGLE_CLIENT_SECRET</code> in <code class="text-gray-600 dark:text-gray-300">.env</code> (still turn on “Enable Google sign-in” above).</p>
                    </div>
                </div>
            </div>
        </x-common.component-card>

        {{-- Colors --}}
        <x-common.component-card title="App Colors" desc="Set the primary, accent, background, button and CTA colors for your application.">
            {{-- Primary Colors --}}
            <div class="mb-8">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Primary Colors</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Brand colors used across the application.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    @include('pages.settings.partials.color-field', ['label' => 'Light Mode', 'name' => 'branding[primary_color]', 'dotName' => 'branding.primary_color', 'default' => '#465fff'])
                    @include('pages.settings.partials.color-field', ['label' => 'Dark Mode', 'name' => 'branding[secondary_color]', 'dotName' => 'branding.secondary_color', 'default' => '#1e3a5f'])
                    @include('pages.settings.partials.color-field', ['label' => 'Primary Light', 'name' => 'branding[primary_light]', 'dotName' => 'branding.primary_light', 'default' => '#465fff'])
                </div>
            </div>

            <hr class="border-gray-200 dark:border-gray-700 mb-8" />

            {{-- Accent Colors --}}
            <div class="mb-8">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Accent Colors</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Secondary highlight colors for badges, icons, and emphasis.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    @include('pages.settings.partials.color-field', ['label' => 'Accent', 'name' => 'branding[accent_color]', 'dotName' => 'branding.accent_color', 'default' => '#f59e0b'])
                    @include('pages.settings.partials.color-field', ['label' => 'Light Mode Accent', 'name' => 'branding[accent_light]', 'dotName' => 'branding.accent_light', 'default' => '#f59e0b'])
                    @include('pages.settings.partials.color-field', ['label' => 'Dark Mode Accent', 'name' => 'branding[accent_dark]', 'dotName' => 'branding.accent_dark', 'default' => '#f59e0b'])
                </div>
            </div>

            <hr class="border-gray-200 dark:border-gray-700 mb-8" />

            {{-- Background Colors --}}
            <div class="mb-8">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Background Colors</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Page background for light and dark modes.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    @include('pages.settings.partials.color-field', ['label' => 'Light Background', 'name' => 'branding[bg_light]', 'dotName' => 'branding.bg_light', 'default' => '#f9fafb'])
                    @include('pages.settings.partials.color-field', ['label' => 'Dark Background', 'name' => 'branding[bg_dark]', 'dotName' => 'branding.bg_dark', 'default' => '#101828'])
                    @include('pages.settings.partials.color-field', ['label' => 'Primary Dark', 'name' => 'branding[primary_dark]', 'dotName' => 'branding.primary_dark', 'default' => '#1e3a5f'])
                </div>
            </div>

            <hr class="border-gray-200 dark:border-gray-700 mb-8" />

            {{-- Button Colors --}}
            {{-- Both tabs stay in the DOM (x-show, not x-if) so the inactive mode's hidden
                 inputs still submit — otherwise switching tabs would wipe the other theme. --}}
            <div class="mb-8" x-data="{ mode: 'light' }">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Buttons</h4>
                <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                    Every button across the site uses these colors. The glow under each button is derived
                    automatically from its background, so it always matches.
                </p>

                {{-- Light / Dark tabs --}}
                <div class="mb-5 inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
                    <button type="button" @click="mode = 'light'"
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
                    <button type="button" @click="mode = 'dark'"
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

                @include('pages.settings.partials.button-preview', [
                    'buttons' => [
                        ['label' => 'Primary Button', 'class' => 'btn-primary', 'size' => 'btn-lg', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                        ['label' => 'Secondary', 'class' => 'btn-secondary', 'size' => 'btn-lg', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                        ['label' => 'Medium', 'class' => 'btn-primary', 'size' => 'btn-md', 'bg' => 'branding[button1_color]', 'text' => 'branding[button1_text_color]', 'bg_dark' => 'branding[button1_color_dark]', 'text_dark' => 'branding[button1_text_color_dark]'],
                        ['label' => 'Small', 'class' => 'btn-secondary', 'size' => 'btn-sm', 'bg' => 'branding[button2_color]', 'text' => 'branding[button2_text_color]', 'bg_dark' => 'branding[button2_color_dark]', 'text_dark' => 'branding[button2_text_color_dark]'],
                    ],
                    'surface' => null,
                ])

                {{-- Light mode fields --}}
                <div x-show="mode === 'light'" class="mt-6">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Background', 'name' => 'branding[button1_color]', 'dotName' => 'branding.button1_color', 'default' => '#465fff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Primary Text', 'name' => 'branding[button1_text_color]', 'dotName' => 'branding.button1_text_color', 'default' => '#ffffff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Secondary Background', 'name' => 'branding[button2_color]', 'dotName' => 'branding.button2_color', 'default' => '#ffffff'])
                        @include('pages.settings.partials.color-field', ['label' => 'Secondary Text', 'name' => 'branding[button2_text_color]', 'dotName' => 'branding.button2_text_color', 'default' => '#374151'])
                    </div>
                </div>

                {{-- Dark mode fields --}}
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

            {{-- CTA Section --}}
            <div x-data="{ mode: 'light' }">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">CTA Banner</h4>
                <p class="mb-5 text-xs text-gray-500 dark:text-gray-400">
                    The call-to-action banner sits on its own colored background, so its two buttons are
                    styled independently of the site-wide buttons above.
                </p>

                {{-- Light / Dark tabs --}}
                <div class="mb-5 inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
                    <button type="button" @click="mode = 'light'"
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
                    <button type="button" @click="mode = 'dark'"
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

                @include('pages.settings.partials.button-preview', [
                    'buttons' => [
                        ['label' => 'Get Started Free', 'class' => 'btn-cta-primary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn1_color]', 'text' => 'branding[cta_btn1_text_color]', 'bg_dark' => 'branding[cta_btn1_color_dark]', 'text_dark' => 'branding[cta_btn1_text_color_dark]'],
                        ['label' => 'View Plans', 'class' => 'btn-cta-secondary', 'size' => 'btn-lg', 'bg' => 'branding[cta_btn2_color]', 'text' => 'branding[cta_btn2_text_color]', 'bg_dark' => 'branding[cta_btn2_color_dark]', 'text_dark' => 'branding[cta_btn2_text_color_dark]'],
                    ],
                    'surface' => ['light' => 'branding[cta_bg_light]', 'dark' => 'branding[cta_bg_dark]'],
                ])

                {{-- Light mode fields --}}
                <div x-show="mode === 'light'" class="mt-6 space-y-6">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_light]', 'dotName' => 'branding.cta_bg_light', 'default' => '#465fff'])
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

                {{-- Dark mode fields --}}
                <div x-show="mode === 'dark'" x-cloak class="mt-6 space-y-6">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Banner Background</p>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            @include('pages.settings.partials.color-field', ['label' => 'Banner Background', 'name' => 'branding[cta_bg_dark]', 'dotName' => 'branding.cta_bg_dark', 'default' => '#3641f5'])
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
            <button type="submit"
                class="btn btn-primary btn-md">
                Save Changes
            </button>
        </div>
    </form>
@endsection
