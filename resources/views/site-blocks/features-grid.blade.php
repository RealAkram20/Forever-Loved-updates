@php
    $cards = is_array($props['cards'] ?? null) ? $props['cards'] : [];
@endphp
<section id="features" class="relative bg-gray-50 dark:bg-gray-800/50 py-16 sm:py-20 glass-bg-mesh">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            @if (!empty($props['eyebrow']))
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
            @endif
            <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">{{ $props['title'] ?? '' }}</h2>
            @if (!empty($props['subtitle']))
                <p class="mt-3 text-gray-600 dark:text-gray-400">{{ $props['subtitle'] }}</p>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cards as $card)
                @if (! is_array($card))
                    @continue
                @endif
                <div class="rounded-2xl glass-card dark:border-gray-700 dark:bg-gray-800 p-6 transition hover:shadow-md">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 mb-4">
                        @switch($card['icon'] ?? 'book')
                            @case('heart')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                @break
                            @case('image')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                @break
                            @case('sparkles')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                @break
                            @default
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        @endswitch
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $card['title'] ?? '' }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $card['body'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
