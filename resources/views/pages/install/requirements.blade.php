@extends('layouts.install')

@section('content')
    <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Server Requirements</h2>
    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Let's make sure your server meets all the requirements.</p>

    {{-- PHP Version --}}
    <div class="mb-5">
        <h3 class="mb-2.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">PHP Version</h3>
        <div class="space-y-2">
            @foreach ($checks['php'] as $check)
                <div class="flex items-center justify-between rounded-lg border px-4 py-2.5
                    {{ $check['pass'] ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
                    <span class="text-sm font-medium {{ $check['pass'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $check['label'] }}
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $check['value'] ?? '' }}</span>
                        @if ($check['pass'])
                            <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @else
                            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Extensions --}}
    <div class="mb-5">
        <h3 class="mb-2.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">PHP Extensions</h3>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ($checks['extensions'] as $check)
                <div class="flex items-center justify-between rounded-lg border px-4 py-2.5
                    {{ $check['pass'] ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
                    <span class="text-sm font-medium {{ $check['pass'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $check['label'] }}
                    </span>
                    @if ($check['pass'])
                        <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    @else
                        <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Writable Dirs --}}
    <div class="mb-6">
        <h3 class="mb-2.5 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Directory Permissions</h3>
        <div class="space-y-2">
            @foreach ($checks['writable'] as $check)
                <div class="flex items-center justify-between rounded-lg border px-4 py-2.5
                    {{ $check['pass'] ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
                    <span class="text-sm font-medium {{ $check['pass'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ $check['label'] }}
                    </span>
                    @if ($check['pass'])
                        <svg class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    @else
                        <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between border-t border-gray-200 pt-5 dark:border-gray-700">
        <a href="{{ route('install.requirements') }}"
           class="btn btn-secondary btn-md">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
            Re-check
        </a>
        @if ($allPassed)
            <a href="{{ route('install.database') }}"
               class="btn btn-primary btn-md">
                Continue
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
            </a>
        @else
            <span class="text-sm text-red-500">Please fix all requirements above to continue.</span>
        @endif
    </div>
@endsection
