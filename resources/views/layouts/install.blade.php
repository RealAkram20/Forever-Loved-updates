<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Install &mdash; {{ $title ?? 'Setup' }}</title>
    @if (\App\Support\InstallerVite::hasManifest())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @elseif (\App\Support\InstallerVite::hasUsableBuildWithoutManifest())
        @foreach (\App\Support\InstallerVite::fallbackStylesheetPaths() as $href)
            <link rel="stylesheet" href="{{ asset($href) }}" />
        @endforeach
        <script type="module" src="{{ asset(\App\Support\InstallerVite::fallbackScriptPath()) }}"></script>
    @endif
    <style>
        /* Fallback if Vite assets are unavailable */
        .install-fallback { font-family: system-ui, -apple-system, sans-serif; }
    </style>
</head>

<body class="min-h-screen bg-gray-50 dark:bg-gray-900 antialiased">
    @if (! \App\Support\InstallerVite::hasManifest() && ! \App\Support\InstallerVite::hasUsableBuildWithoutManifest())
        <div class="install-fallback" role="alert" style="max-width:42rem;margin:0 auto;padding:1rem 1.25rem;border-bottom:1px solid #fecaca;background:#fef2f2;color:#7f1d1d;font-family:system-ui,sans-serif;font-size:14px;line-height:1.55;">
            <strong>Installer assets missing.</strong>
            <span style="display:block;margin-top:.35rem">Upload the full <code style="background:#fee2e2;padding:.1rem .35rem;border-radius:3px">public/build</code> folder from <code style="background:#fee2e2;padding:.1rem .35rem;border-radius:3px">npm run build</code> (include <code style="background:#fee2e2;padding:.1rem .35rem;border-radius:3px">manifest.json</code> and <code style="background:#fee2e2;padding:.1rem .35rem;border-radius:3px">assets/*</code>), or use <code style="background:#fee2e2;padding:.1rem .35rem;border-radius:3px">/setup.php</code> instead.</span>
        </div>
    @endif
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        {{-- Logo / Title --}}
        <div class="mb-8 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/10 dark:bg-brand-400/10">
                <svg class="h-8 w-8 text-brand-500 dark:text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Forever Love</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Installation Wizard</p>
        </div>

        {{-- Step Indicator --}}
        @php
            $steps = [
                ['key' => 'requirements', 'label' => 'Requirements'],
                ['key' => 'database', 'label' => 'Database'],
                ['key' => 'settings', 'label' => 'Settings'],
                ['key' => 'admin', 'label' => 'Admin'],
                ['key' => 'install', 'label' => 'Install'],
            ];
            $currentStep = $currentStep ?? 'requirements';
            $currentIndex = collect($steps)->search(fn ($s) => $s['key'] === $currentStep);
        @endphp
        <nav class="mb-8 w-full max-w-2xl">
            <ol class="flex items-center justify-between">
                @foreach ($steps as $i => $step)
                    <li class="flex items-center {{ $i < count($steps) - 1 ? 'flex-1' : '' }}">
                        <div class="flex flex-col items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold
                                @if ($i < $currentIndex) bg-green-500 text-white
                                @elseif ($i === $currentIndex) bg-brand-500 text-white
                                @else bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400
                                @endif">
                                @if ($i < $currentIndex)
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </div>
                            <span class="mt-1.5 text-xs font-medium
                                @if ($i <= $currentIndex) text-gray-700 dark:text-gray-300
                                @else text-gray-400 dark:text-gray-500
                                @endif">{{ $step['label'] }}</span>
                        </div>
                        @if ($i < count($steps) - 1)
                            <div class="mx-2 mt-[-1rem] h-0.5 flex-1
                                @if ($i < $currentIndex) bg-green-500
                                @else bg-gray-200 dark:bg-gray-700
                                @endif"></div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        {{-- Card --}}
        <div class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8 dark:border-gray-700 dark:bg-gray-800">
            @if (session('error'))
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">&copy; {{ date('Y') }} Forever Love. All rights reserved.</p>
    </div>

    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            if (saved === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</body>

</html>
