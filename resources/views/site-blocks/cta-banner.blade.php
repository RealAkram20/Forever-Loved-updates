@php
    $pRoute = $props['primary_route'] ?? '';
    $sRoute = $props['secondary_route'] ?? '';
    $primaryUrl = $pRoute && \Illuminate\Support\Facades\Route::has($pRoute) ? route($pRoute) : '#';
    $secondaryUrl = $sRoute && \Illuminate\Support\Facades\Route::has($sRoute) ? route($sRoute) : '#';
@endphp
<section class="relative overflow-hidden py-16 sm:py-20" style="background-color: var(--color-cta-bg, var(--color-brand-500));">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -right-32 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-32 -left-32 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
    </div>
    <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-white sm:text-3xl lg:text-4xl">{{ $props['title'] ?? '' }}</h2>
        @if (!empty($props['body']))
            <p class="mt-4 text-lg text-white/80">{{ $props['body'] }}</p>
        @endif
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="{{ $primaryUrl }}"
               class="btn btn-cta-primary btn-lg">
                {{ $props['primary_label'] ?? 'Get Started Free' }}
            </a>
            @if (($props['secondary_label'] ?? '') !== '')
                <a href="{{ $secondaryUrl }}"
                   class="btn btn-cta-secondary btn-lg">
                    {{ $props['secondary_label'] }}
                </a>
            @endif
        </div>
    </div>
</section>
