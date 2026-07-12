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
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Colors, fonts and the default light/dark mode moved to
                        <a href="{{ route('settings.appearance') }}" class="text-brand-500 hover:text-brand-600 underline">Appearance</a>.
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


        <div class="flex justify-end">
            <button type="submit"
                class="btn btn-primary btn-md">
                Save Changes
            </button>
        </div>
    </form>
@endsection
