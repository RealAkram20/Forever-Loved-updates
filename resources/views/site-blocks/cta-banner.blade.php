@php
    $pRoute = $props['primary_route'] ?? '';
    $sRoute = $props['secondary_route'] ?? '';
    $primaryUrl = $pRoute && \Illuminate\Support\Facades\Route::has($pRoute) ? route($pRoute) : '#';
    $secondaryUrl = $sRoute && \Illuminate\Support\Facades\Route::has($sRoute) ? route($sRoute) : '#';
@endphp
<section class="py-14 sm:py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl dark:ring-1 dark:ring-white/10" style="background-color: var(--color-cta-bg, var(--color-brand-500));">
            {{-- Panorama artwork (candle + vase), one cut per theme; the panel color above
                 shows wherever the image doesn't cover, and each theme's CTA background
                 setting is matched to its artwork's left edge so no seam forms.
                 Text/button colors stay themeable via the Appearance CTA roles and CTA
                 button settings. --}}
            <img src="{{ asset('CTA-Bg.webp') }}" alt="" draggable="false" loading="lazy"
                 class="absolute inset-0 h-full w-full object-cover object-[78%_100%] sm:object-right pointer-events-none select-none dark:hidden" aria-hidden="true">
            <img src="{{ asset('CTA-Dark-Bg.webp') }}" alt="" draggable="false" loading="lazy"
                 class="absolute inset-0 hidden h-full w-full object-cover object-right pointer-events-none select-none dark:block" aria-hidden="true">

            {{-- Legibility scrim: the banner's own color faded over the artwork, strongest
                 behind the copy. Its strength per theme is the "Text Legibility" slider in
                 Appearance > CTA Banner; at 0 it disappears. Direction is set in .cta-scrim —
                 sideways on wide panels, downward on narrow ones. --}}
            <div class="cta-scrim absolute inset-0 pointer-events-none" aria-hidden="true"></div>

            <div class="relative px-6 py-12 sm:px-12 sm:py-14 lg:px-14">
                <div class="max-w-xl">
                    <h2 class="ap-cta-title font-display text-2xl font-semibold leading-snug text-gray-900 sm:text-3xl lg:text-[2.1rem] dark:text-white">{{ $props['title'] ?? '' }}</h2>
                    @if (!empty($props['body']))
                        <p class="ap-cta-body mt-3 text-base text-gray-700 dark:text-gray-300">{{ $props['body'] }}</p>
                    @endif
                    <div class="mt-7 flex flex-wrap items-center gap-x-7 gap-y-4">
                        <a href="{{ $primaryUrl }}" class="btn btn-cta-primary btn-lg">
                            {{ $props['primary_label'] ?? 'Get Started Free' }}
                        </a>
                        @if (($props['secondary_label'] ?? '') !== '')
                            <a href="{{ $secondaryUrl }}" class="cta-text-link group">
                                {{ $props['secondary_label'] }}
                                <svg class="lucide icon-arrow h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
