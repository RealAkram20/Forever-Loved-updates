@extends('layouts.fullscreen-layout')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gradient-to-b from-gray-50 via-white to-gray-50 px-4 pb-24 pt-8 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900 sm:pb-28">
    <div class="mx-auto max-w-lg px-6 text-center">
        {{-- Animated heart icon --}}
        <div class="mb-10">
            <div class="preparing-heart-pulse mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/15">
                <svg class="h-10 w-10 text-brand-500 dark:text-brand-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
            </div>
        </div>

        {{-- Main message --}}
        <h1 class="preparing-fade-in text-2xl font-light tracking-wide text-gray-700 dark:text-gray-200 sm:text-3xl">
            Creating a Space Where
            <span class="block mt-1 font-semibold text-brand-600 dark:text-brand-400">{{ $memorial->first_name }}</span>
            <span class="block mt-1">is Forever Loved</span>
        </h1>

        {{-- Animated ellipsis --}}
        <div class="mt-6 flex items-center justify-center gap-1.5">
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 0s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 0.3s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 0.6s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 0.9s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 1.2s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 1.5s;"></span>
            <span class="preparing-dot h-2 w-2 rounded-full bg-brand-400" style="animation-delay: 1.8s;"></span>
        </div>

        {{-- Sub message --}}
        <p class="preparing-fade-in-delayed mt-8 text-sm font-medium tracking-widest uppercase text-gray-400 dark:text-gray-500">
            Always Remembered
        </p>
    </div>
</div>

<style>
    @keyframes gentleFade {
        0%, 100% { opacity: 0.12; }
        50% { opacity: 1; }
    }
    .preparing-dot {
        opacity: 0.12;
        animation: gentleFade 2s ease-in-out infinite;
    }
    @keyframes heartPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    .preparing-heart-pulse {
        animation: heartPulse 2s ease-in-out infinite;
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .preparing-fade-in {
        animation: fadeInUp 1s ease-out forwards;
    }
    .preparing-fade-in-delayed {
        opacity: 0;
        animation: fadeInUp 1s ease-out 0.6s forwards;
    }
</style>

@push('scripts')
<script>
    (function() {
        try {
            localStorage.removeItem('memorial_signup_step1');
            localStorage.removeItem('memorial_signup_step2');
        } catch (e) {}
    })();
    setTimeout(function () {
        window.location.href = @json(route('memorial.public', ['slug' => $memorial->slug]));
    }, 4000);
</script>
@endpush
@endsection
