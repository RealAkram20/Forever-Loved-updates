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
            <div class="mb-8">
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">Button Colors</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Colors for primary and secondary action buttons.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @include('pages.settings.partials.color-field', ['label' => 'Button 1 (Primary)', 'name' => 'branding[button1_color]', 'dotName' => 'branding.button1_color', 'default' => '#465fff'])
                    @include('pages.settings.partials.color-field', ['label' => 'Button 2 (Secondary)', 'name' => 'branding[button2_color]', 'dotName' => 'branding.button2_color', 'default' => '#ffffff'])
                </div>
            </div>

            <hr class="border-gray-200 dark:border-gray-700 mb-8" />

            {{-- CTA Section Colors --}}
            <div>
                <h4 class="mb-1 text-sm font-semibold text-gray-900 dark:text-white/90">CTA Section</h4>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Background for the call-to-action banner on the landing page.</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @include('pages.settings.partials.color-field', ['label' => 'CTA Light', 'name' => 'branding[cta_bg_light]', 'dotName' => 'branding.cta_bg_light', 'default' => '#465fff'])
                    @include('pages.settings.partials.color-field', ['label' => 'CTA Dark', 'name' => 'branding[cta_bg_dark]', 'dotName' => 'branding.cta_bg_dark', 'default' => '#3641f5'])
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 transition">
                Save Changes
            </button>
        </div>
    </form>
@endsection
