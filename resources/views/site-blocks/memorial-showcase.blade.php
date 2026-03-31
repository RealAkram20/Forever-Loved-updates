@if (isset($popularMemorials) && $popularMemorials->isNotEmpty())
@php
    $dirUrl = \Illuminate\Support\Facades\Route::has('memorial.directory') ? route('memorial.directory') : '#';
@endphp
<section class="bg-white dark:bg-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
            <div>
                @if (!empty($props['eyebrow']))
                    <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $props['eyebrow'] }}</p>
                @endif
                <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">{{ $props['title'] ?? 'Popular Memorials' }}</h2>
            </div>
            <a href="{{ $dirUrl }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition">
                {{ $props['view_all_label'] ?? 'View All' }}
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
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
                       class="group block rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800/50 p-4 transition hover:shadow-lg hover:border-brand-200 dark:hover:border-brand-800">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="h-14 w-14 shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden ring-2 ring-white dark:ring-gray-800">
                                @if ($memorial->profile_photo_url)
                                    <img src="{{ $memorial->profile_photo_url }}" alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-lg font-bold text-gray-400 dark:text-gray-500">{{ strtoupper(substr($memorial->first_name ?? '?', 0, 1)) }}</div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{{ $memorial->full_name }}</h3>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    @if ($memorial->birth_year && $memorial->death_year)
                                        {{ $memorial->birth_year }} &ndash; {{ $memorial->death_year }}
                                    @elseif ($memorial->primary_profession)
                                        {{ $memorial->primary_profession }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($memorial->short_description)
                            <p class="line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $memorial->short_description }}</p>
                        @endif
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <a href="{{ $dirUrl }}" class="mt-6 sm:hidden inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400">
            {{ $props['mobile_view_all_label'] ?? 'View All Memorials' }}
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>
@endif
