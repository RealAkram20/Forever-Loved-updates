@extends('layouts.visitor')

@push('head')
<style>
    .memorial-swiper .swiper-wrapper {
        transition-timing-function: linear !important;
    }
</style>
@endpush

@section('page')

{{-- Hero Section --}}
<section class="relative overflow-hidden bg-gradient-to-br from-gray-50 via-white to-brand-50/30 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 glass-bg-mesh">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-brand-500/8 blur-3xl dark:bg-brand-500/10"></div>
        <div class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-pink-400/6 blur-3xl dark:bg-brand-500/10"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full bg-blue-300/5 blur-3xl dark:hidden"></div>
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8 lg:py-36">
        <div class="max-w-2xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">{{ $tagline }}</p>
            <h1 class="text-4xl font-bold leading-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl">
                Honor Your Loved Ones.
                <span class="text-brand-500">Forever Remembered.</span>
            </h1>
            <p class="mt-6 max-w-lg text-lg leading-relaxed text-gray-600 dark:text-gray-400">
                Create beautiful, lasting digital memorials. Share memories, collect tributes, and preserve legacies for generations to come.
            </p>
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('memorial.create.step1') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create a Memorial
                </a>
                <a href="{{ route('memorial.directory') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-6 py-3.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Explore Memorials
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>

            {{-- Trust indicators --}}
            <div class="mt-10 flex flex-wrap items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Free to start
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Secure & Private
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    No credit card required
                </span>
            </div>
        </div>
    </div>
</section>

{{-- Popular Memorials Slider --}}
@if ($popularMemorials->isNotEmpty())
<section class="bg-white dark:bg-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">Featured</p>
                <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Popular Memorials</h2>
            </div>
            <a href="{{ route('memorial.directory') }}" class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition">
                View All
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
                        @if ($memorial->short_description || $memorial->designation)
                            <p class="line-clamp-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{{ $memorial->short_description ?? $memorial->designation }}</p>
                        @endif
                    </a>
                </div>
                @endforeach
            </div>
        </div>

        <a href="{{ route('memorial.directory') }}" class="mt-6 sm:hidden inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 dark:text-brand-400">
            View All Memorials
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
        </a>
    </div>
</section>
@endif

{{-- Must-Have Feature Cards --}}
<section id="features" class="relative bg-gray-50 dark:bg-gray-800/50 py-16 sm:py-20 glass-bg-mesh">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">Why Choose Us</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Everything You Need to Honor a Life</h2>
            <p class="mt-3 text-gray-600 dark:text-gray-400">Our platform provides all the tools to create a meaningful, lasting tribute.</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Card 1 --}}
            <div class="rounded-2xl glass-card dark:border-gray-700 dark:bg-gray-800 p-6 transition hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Beautiful Memorials</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">Create elegant, personalized memorial pages with biographies, life timelines, and cherished stories.</p>
            </div>

            {{-- Card 2 --}}
            <div class="rounded-2xl glass-card dark:border-gray-700 dark:bg-gray-800 p-6 transition hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Collect Tributes</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">Let family and friends leave virtual flowers, light candles, and share heartfelt written tributes.</p>
            </div>

            {{-- Card 3 --}}
            <div class="rounded-2xl glass-card dark:border-gray-700 dark:bg-gray-800 p-6 transition hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Photo & Video Gallery</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">Upload and display photos and videos in a stunning gallery to preserve precious moments.</p>
            </div>

            {{-- Card 4 --}}
            <div class="rounded-2xl glass-card dark:border-gray-700 dark:bg-gray-800 p-6 transition hover:shadow-md">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 mb-4">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">AI-Powered Biography</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">Let our AI help craft a beautiful biography from key details, making it easy to tell their story.</p>
            </div>
        </div>
    </div>
</section>

{{-- Featured Designations --}}
<section class="relative bg-white/60 dark:bg-gray-900 py-16 sm:py-20 glass-bg-mesh">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">Memorial Categories</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Honoring Every Story</h2>
            <p class="mt-3 text-gray-600 dark:text-gray-400">Dedicated spaces for remembering those lost to different circumstances.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($designations as $designation)
            <a href="{{ route('memorial.directory') }}?designation={{ urlencode($designation['value']) }}"
               class="group flex items-center gap-4 rounded-xl glass-card dark:border-gray-800 dark:bg-gray-800/50 p-5 transition hover:shadow-md hover:border-brand-200 dark:hover:border-brand-800">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 group-hover:bg-brand-50 dark:group-hover:bg-brand-500/10 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white group-hover:text-brand-600 dark:group-hover:text-brand-400 transition">{{ $designation['name'] }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $designation['count'] }} {{ Str::plural('memorial', $designation['count']) }}</p>
                </div>
                <svg class="h-5 w-5 shrink-0 text-gray-300 dark:text-gray-600 group-hover:text-brand-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA Section --}}
<section class="relative overflow-hidden py-16 sm:py-20" style="background-color: var(--color-cta-bg, var(--color-brand-500));">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -right-32 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-32 -left-32 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
    </div>
    <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-white sm:text-3xl lg:text-4xl">Start Preserving Memories Today</h2>
        <p class="mt-4 text-lg text-white/80">Every life has a story worth telling. Create a free memorial in minutes and invite loved ones to contribute their memories.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="{{ route('memorial.create.step1') }}"
               class="inline-flex items-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold shadow-lg transition"
               style="background-color: var(--color-btn-secondary, #fff); color: var(--color-cta-bg, var(--color-brand-600));">
                Get Started Free
            </a>
            <a href="{{ route('pricing') }}"
               class="inline-flex items-center gap-2 rounded-xl border-2 border-white/30 px-6 py-3.5 text-sm font-semibold text-white hover:bg-white/10 transition">
                View Plans
            </a>
        </div>
    </div>
</section>

@endsection
