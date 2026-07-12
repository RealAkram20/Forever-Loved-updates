@php
    $eyebrow = $props['eyebrow'] ?? '';
    $primaryUrl = \Illuminate\Support\Facades\Route::has($props['primary_route'] ?? '') ? route($props['primary_route']) : '#';
    $secondaryRoute = $props['secondary_route'] ?? '';
    $secondaryUrl = $secondaryRoute && \Illuminate\Support\Facades\Route::has($secondaryRoute) ? route($secondaryRoute) : '#';
    $trustPoints = is_array($props['trust_points'] ?? null) ? $props['trust_points'] : [];
@endphp
<section class="relative overflow-hidden bg-gradient-to-br from-gray-50 via-white to-brand-50/30 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 glass-bg-mesh">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-500/8 blur-3xl dark:bg-brand-500/10"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-pink-400/6 blur-3xl dark:bg-brand-500/10"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full bg-blue-300/5 blur-3xl dark:hidden"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36">
        <div class="max-w-2xl">
            @if ($eyebrow !== '')
                <p class="ap-eyebrow mb-4 text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $eyebrow }}</p>
            @endif
            <h1 class="ap-title text-4xl font-bold leading-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl">
                {{ $props['headline_line1'] ?? '' }}
                @if (!empty($props['headline_accent']))
                    <span class="ap-title-accent text-brand-500">{{ $props['headline_accent'] }}</span>
                @endif
            </h1>
            @if (!empty($props['body']))
                <p class="ap-lead mt-6 max-w-lg text-lg leading-relaxed text-gray-600 dark:text-gray-400">{{ $props['body'] }}</p>
            @endif
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ $primaryUrl }}"
                   class="btn btn-primary btn-lg">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ $props['primary_btn_label'] ?? 'Create a Memorial' }}
                </a>
                @if (($props['secondary_btn_label'] ?? '') !== '')
                    <a href="{{ $secondaryUrl }}"
                       class="btn btn-secondary btn-lg">
                        {{ $props['secondary_btn_label'] }}
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                @endif
            </div>

            @if (count($trustPoints) > 0)
                <div class="mt-10 flex flex-wrap items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                    @foreach ($trustPoints as $point)
                        @if (is_string($point) && $point !== '')
                            <span class="flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                {{ $point }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
