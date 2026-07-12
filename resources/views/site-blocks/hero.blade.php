@php
    $eyebrow = $props['eyebrow'] ?? '';
    $primaryUrl = \Illuminate\Support\Facades\Route::has($props['primary_route'] ?? '') ? route($props['primary_route']) : '#';
    $secondaryRoute = $props['secondary_route'] ?? '';
    $secondaryUrl = $secondaryRoute && \Illuminate\Support\Facades\Route::has($secondaryRoute) ? route($secondaryRoute) : '#';
    $trustPoints = is_array($props['trust_points'] ?? null) ? $props['trust_points'] : [];
    $taglineText = trim($props['tagline'] ?? ($tagline ?? ''));
@endphp
<section class="relative overflow-hidden">
    {{-- Everything decorative — wash and artwork alike — is held to the same
         column width as the section content, so nothing spills toward the
         viewport edge and no seam forms where the column ends. --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none select-none" aria-hidden="true">
        <div class="absolute inset-0 mx-auto max-w-7xl">
            <div class="absolute inset-y-0 right-0 w-3/4 bg-gradient-to-l from-orange-100/60 via-rose-100/20 to-transparent sm:w-2/3 dark:hidden"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 translate-y-1/3 rounded-full bg-orange-200/40 blur-3xl dark:hidden"></div>
            <div class="hero-wash-dark absolute inset-0 hidden dark:block"></div>

            {{-- TEMP: testing Gg.webp stands in for the final light-mode artwork --}}
            <img src="{{ asset('testing Gg.webp') }}" alt="" draggable="false" fetchpriority="high" class="hero-bg-img dark:hidden">
            <img src="{{ asset('DRK-bg.webp') }}" alt="" draggable="false" loading="lazy" class="hero-bg-img hero-bg-img--blend hidden dark:block">

            {{-- Settles the column's trailing edges back onto the page background --}}
            <div class="hero-artwork-fade absolute inset-0"></div>
        </div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8 lg:py-28">
        <div class="max-w-2xl">
            @if ($eyebrow !== '')
                <p class="ap-eyebrow mb-5 text-sm font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">{{ $eyebrow }}</p>
            @endif
            <h1 class="ap-title font-display text-[2.75rem] leading-[1.08] font-semibold text-gray-900 dark:text-white sm:text-6xl lg:text-[4.25rem]">
                {{ $props['headline_line1'] ?? '' }}
                @if (!empty($props['headline_accent']))
                    <span class="ap-title-accent block text-brand-500">{{ $props['headline_accent'] }}</span>
                @endif
            </h1>

            @if ($taglineText !== '')
                <p class="hero-tagline mt-7 flex items-center gap-3 text-gray-800 dark:text-gray-200">
                    <span class="h-0.5 w-8 shrink-0 rounded-full bg-[var(--color-accent-light)]" aria-hidden="true"></span>
                    <svg class="lucide h-4.5 w-4.5 shrink-0 text-[var(--color-accent-light)]" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                    <em class="font-display text-lg italic">{{ $taglineText }}</em>
                </p>
            @endif

            @if (!empty($props['body']))
                <p class="ap-lead mt-7 max-w-lg text-lg leading-relaxed text-gray-600 dark:text-gray-400">{{ $props['body'] }}</p>
            @endif
            <div class="mt-9 flex flex-wrap gap-4">
                <a href="{{ $primaryUrl }}" class="btn btn-primary btn-lg group">
                    <svg class="lucide icon-plus h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    {{ $props['primary_btn_label'] ?? 'Create a Memorial' }}
                </a>
                @if (($props['secondary_btn_label'] ?? '') !== '')
                    <a href="{{ $secondaryUrl }}" class="btn btn-secondary btn-lg group">
                        <svg class="lucide icon-heart h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        {{ $props['secondary_btn_label'] }}
                        <svg class="lucide icon-arrow h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
            </div>

            @if (count($trustPoints) > 0)
                <div class="mt-10 inline-flex flex-wrap items-center gap-x-6 gap-y-3 rounded-2xl border border-white/50 bg-white/30 px-5 py-3.5 text-sm text-gray-600 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-white/[0.06] dark:text-gray-300">
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
