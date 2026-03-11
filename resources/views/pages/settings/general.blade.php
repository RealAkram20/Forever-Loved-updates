@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="General Settings" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
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
                    <input type="text" name="branding.app_name"
                        value="{{ old('branding.app_name', $settings['branding.app_name'] ?? 'Forever Loved') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('branding.app_name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Tagline</label>
                    <input type="text" name="branding.tagline"
                        value="{{ old('branding.tagline', $settings['branding.tagline'] ?? '') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('branding.tagline')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
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
        <x-common.component-card title="App Colors" desc="Set the primary, secondary and accent colors for your application.">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="branding.primary_color"
                            value="{{ old('branding.primary_color', $settings['branding.primary_color'] ?? '#465fff') }}"
                            class="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 p-1" />
                        <input type="text" readonly
                            value="{{ old('branding.primary_color', $settings['branding.primary_color'] ?? '#465fff') }}"
                            class="h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-600 dark:text-gray-300"
                            x-data x-ref="display"
                            x-init="$el.previousElementSibling.addEventListener('input', e => { $el.value = e.target.value })" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="branding.secondary_color"
                            value="{{ old('branding.secondary_color', $settings['branding.secondary_color'] ?? '#1e3a5f') }}"
                            class="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 p-1" />
                        <input type="text" readonly
                            value="{{ old('branding.secondary_color', $settings['branding.secondary_color'] ?? '#1e3a5f') }}"
                            class="h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-600 dark:text-gray-300"
                            x-data x-ref="display"
                            x-init="$el.previousElementSibling.addEventListener('input', e => { $el.value = e.target.value })" />
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Accent Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="branding.accent_color"
                            value="{{ old('branding.accent_color', $settings['branding.accent_color'] ?? '#f59e0b') }}"
                            class="h-11 w-14 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 p-1" />
                        <input type="text" readonly
                            value="{{ old('branding.accent_color', $settings['branding.accent_color'] ?? '#f59e0b') }}"
                            class="h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 text-sm text-gray-600 dark:text-gray-300"
                            x-data x-ref="display"
                            x-init="$el.previousElementSibling.addEventListener('input', e => { $el.value = e.target.value })" />
                    </div>
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
