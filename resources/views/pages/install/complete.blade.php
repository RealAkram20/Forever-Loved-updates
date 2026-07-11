@extends('layouts.install')

@section('content')
    <div class="text-center py-4">
        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
            <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h2 class="mb-2 text-xl font-semibold text-gray-900 dark:text-white">Installation Complete</h2>
        <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
            Your application has been installed successfully. You can now sign in with your admin account.
        </p>

        @php
            $phpBinary = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
            $cronLine = '* * * * * '.$phpBinary.' '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
        @endphp
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-left dark:border-amber-800/50 dark:bg-amber-900/20">
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-amber-800 dark:text-amber-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Set up your cron job (required)
            </h3>
            <p class="mb-3 text-sm text-amber-700 dark:text-amber-400">
                Email, notifications, AI generation, and subscription renewals run in the background.
                Add this single line in your hosting control panel (cPanel &rarr; <strong>Cron Jobs</strong>, schedule: every minute):
            </p>
            <div class="flex items-center gap-2">
                <code id="cron-line" class="block flex-1 overflow-x-auto whitespace-nowrap rounded bg-gray-900 px-3 py-2 text-xs text-green-400">{{ $cronLine }}</code>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('cron-line').textContent).then(() => { this.textContent = 'Copied!'; setTimeout(() => this.textContent = 'Copy', 2000); })"
                    class="btn btn-secondary btn-sm shrink-0">Copy</button>
            </div>
            <p class="mt-2 text-xs text-amber-700/80 dark:text-amber-400/80">
                The dashboard will warn you until this cron is detected. Until then, the app falls back to slower on-request processing.
            </p>
        </div>

        <div class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left dark:border-gray-700 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Recommended next steps:</h3>
            <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-brand-500">1.</span>
                    <span>Sign in and configure your branding, payment, and SMTP settings in <strong>Settings</strong>.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-brand-500">2.</span>
                    <span>Optionally import geo data for country/state/city selectors: <code class="rounded bg-gray-200 px-1.5 py-0.5 text-xs dark:bg-gray-700">php artisan geo:import --download</code></span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 text-brand-500">3.</span>
                    <span>Create your first memorial and invite contributors.</span>
                </li>
            </ul>
        </div>

        <a href="{{ url('/login') }}"
           class="btn btn-primary btn-lg">
            Go to Login
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
        </a>
    </div>
@endsection
