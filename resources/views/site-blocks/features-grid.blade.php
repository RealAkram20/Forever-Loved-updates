@php
    $cards = is_array($props['cards'] ?? null) ? $props['cards'] : [];
@endphp
<section id="features" class="relative py-14 sm:py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            @if (!empty($props['eyebrow']))
                <p class="ap-eyebrow text-sm font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
            @endif
            <h2 class="ap-title font-display mt-3 text-3xl font-semibold text-gray-900 dark:text-white sm:text-4xl">{{ $props['title'] ?? '' }}</h2>
            @if (!empty($props['subtitle']))
                <p class="ap-lead mt-4 text-gray-600 dark:text-gray-400">{{ $props['subtitle'] }}</p>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cards as $card)
                @if (! is_array($card))
                    @continue
                @endif
                <div class="group rounded-2xl bg-white shadow-[0_2px_16px_rgba(35,43,84,0.06)] ring-1 ring-gray-900/[0.04] dark:ring-white/10 dark:bg-gray-800/60 p-6 transition hover:shadow-[0_8px_28px_rgba(35,43,84,0.12)] hover:-translate-y-0.5">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500 dark:text-brand-400 mb-5 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3">
                        @switch($card['icon'] ?? 'book')
                            @case('heart')
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                                @break
                            @case('image')
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                @break
                            @case('sparkles')
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>
                                @break
                            @case('infinity')
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16c5 0 7-8 12-8a4 4 0 0 1 0 8c-5 0-7-8-12-8a4 4 0 1 0 0 8"/></svg>
                                @break
                            @case('flower')
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9"/><path d="M7.5 12H9"/><path d="M16.5 12H15"/><path d="M12 16.5V15"/><path d="m8 8 1.88 1.88"/><path d="M14.12 9.88 16 8"/><path d="m8 16 1.88-1.88"/><path d="M14.12 14.12 16 16"/></svg>
                                @break
                            @default
                                <svg class="lucide h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v14"/><path d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z"/></svg>
                        @endswitch
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $card['title'] ?? '' }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $card['body'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
