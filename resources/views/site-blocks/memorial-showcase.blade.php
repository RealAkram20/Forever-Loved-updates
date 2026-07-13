@if (isset($popularMemorials) && $popularMemorials->isNotEmpty())
@php
    $dirUrl = \Illuminate\Support\Facades\Route::has('memorial.directory') ? route('memorial.directory') : '#';
@endphp
<section class="relative isolate py-14 sm:py-16">
    <div class="showcase-wash-dark absolute inset-0 hidden pointer-events-none select-none dark:block" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-8">
            <div>
                @if (!empty($props['eyebrow']))
                    <p class="ap-eyebrow text-sm font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
                @endif
                <h2 class="ap-title font-display mt-2 text-3xl font-semibold text-gray-900 dark:text-white sm:text-4xl">{{ $props['title'] ?? 'Popular Memorials' }}</h2>
            </div>
            <a href="{{ $dirUrl }}" class="btn btn-link btn-md group hidden sm:inline-flex">
                {{ $props['view_all_label'] ?? 'View All' }}
                <svg class="lucide icon-arrow h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </div>

        <div class="swiper memorial-swiper" x-data x-init="
            new Swiper($el, {
                modules: [SwiperAutoplay, SwiperFreeMode],
                slidesPerView: 1.2,
                spaceBetween: 16,
                loop: true,
                speed: 5000,
                freeMode: { enabled: true, momentum: false },
                autoplay: {
                    delay: 0,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                },
                breakpoints: {
                    640: { slidesPerView: 2.2, spaceBetween: 20 },
                    1024: { slidesPerView: 3.2, spaceBetween: 24 },
                    1280: { slidesPerView: 4, spaceBetween: 24 }
                }
            })
        ">
            <div class="swiper-wrapper">
                @foreach ($popularMemorials as $memorial)
                <div class="swiper-slide">
                    <a href="{{ route('memorial.public', $memorial->slug) }}"
                       class="group block rounded-2xl bg-white shadow-[0_2px_16px_rgba(35,43,84,0.06)] ring-1 ring-gray-900/[0.04] dark:ring-white/10 dark:bg-gray-800/60 p-5 transition hover:shadow-[0_8px_28px_rgba(35,43,84,0.12)] hover:-translate-y-0.5">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="h-16 w-16 shrink-0 rounded-xl bg-brand-50 dark:bg-brand-500/10 overflow-hidden">
                                @if ($memorial->profile_photo_url)
                                    <img src="{{ $memorial->profile_photo_url }}" alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-brand-500 dark:text-brand-400">
                                        <svg class="lucide h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9"/><path d="M7.5 12H9"/><path d="M16.5 12H15"/><path d="M12 16.5V15"/><path d="m8 8 1.88 1.88"/><path d="M14.12 9.88 16 8"/><path d="m8 16 1.88-1.88"/><path d="M14.12 14.12 16 16"/></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{{ $memorial->full_name }}</h3>
                                <p class="truncate mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($memorial->birth_year && $memorial->death_year)
                                        {{ $memorial->birth_year }} &ndash; {{ $memorial->death_year }}
                                    @elseif ($memorial->primary_profession)
                                        {{ $memorial->primary_profession }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($memorial->short_description)
                            <p class="line-clamp-2 mb-4 text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ $memorial->short_description }}</p>
                        @endif
                        <div class="flex items-center gap-5 text-sm text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="lucide h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                                {{ number_format($memorial->view_count ?? 0) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="lucide h-4 w-4 text-[var(--color-accent-light)]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                                {{ number_format($memorial->tribute_count ?? 0) }}
                            </span>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <a href="{{ $dirUrl }}" class="btn btn-link btn-md group mt-6 sm:hidden">
            {{ $props['mobile_view_all_label'] ?? 'View All Memorials' }}
            <svg class="lucide icon-arrow h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
    </div>
</section>
@endif
