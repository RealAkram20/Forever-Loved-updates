@extends('layouts.fullscreen-layout')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-white">
    <div class="mx-auto max-w-md px-6 text-center">
        {{-- Soft decorative icon --}}
        <div class="mb-8">
            <svg class="mx-auto h-16 w-16 text-brand-400 opacity-60" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
            </svg>
        </div>

        {{-- Message --}}
        <p class="text-lg font-light tracking-wide text-gray-600">
            We are gently preparing the memories of
            <span class="font-medium text-gray-800">{{ $memorial->first_name }}</span>…
            <br>please wait
        </p>

        {{-- Three fading dots --}}
        <div class="mt-8 flex items-center justify-center gap-3">
            <span class="preparing-dot h-2.5 w-2.5 rounded-full bg-brand-400" style="animation-delay: 0s;"></span>
            <span class="preparing-dot h-2.5 w-2.5 rounded-full bg-brand-400" style="animation-delay: 0.4s;"></span>
            <span class="preparing-dot h-2.5 w-2.5 rounded-full bg-brand-400" style="animation-delay: 0.8s;"></span>
        </div>
    </div>
</div>

<style>
    @keyframes gentleFade {
        0%, 100% { opacity: 0.15; }
        50% { opacity: 1; }
    }
    .preparing-dot {
        opacity: 0.15;
        animation: gentleFade 1.6s ease-in-out infinite;
    }
</style>

@push('scripts')
<script>
    setTimeout(function () {
        window.location.href = @json(route('memorial.public', ['slug' => $memorial->slug]));
    }, 4000);
</script>
@endpush
@endsection
