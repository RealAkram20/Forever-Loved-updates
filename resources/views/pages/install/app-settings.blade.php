@extends('layouts.install')

@section('content')
    <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Application Settings</h2>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Configure your application name, URL, and environment.</p>

    <form method="POST" action="{{ route('install.settings.store') }}">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="app_name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Application Name</label>
                <input type="text" name="app_name" id="app_name" value="{{ old('app_name', $settings['app_name']) }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                @error('app_name') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="app_url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Application URL</label>
                <input type="url" name="app_url" id="app_url" value="{{ old('app_url', $settings['app_url']) }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">The full URL where the app will be accessed (no trailing slash).</p>
                @error('app_url') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="app_env" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Environment</label>
                <select name="app_env" id="app_env"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-sm focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
                    <option value="production" {{ old('app_env', $settings['app_env']) === 'production' ? 'selected' : '' }}>Production</option>
                    <option value="local" {{ old('app_env', $settings['app_env']) === 'local' ? 'selected' : '' }}>Local Development</option>
                </select>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Production disables debug mode and enables secure cookies.</p>
                @error('app_env') <span class="mt-1 text-xs text-red-500">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-5 dark:border-gray-700">
            <a href="{{ route('install.database') }}"
               class="btn btn-secondary btn-md">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                Back
            </a>
            <button type="submit"
                class="btn btn-primary btn-md">
                Continue
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </button>
        </div>
    </form>
@endsection
