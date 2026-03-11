@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 min-h-screen bg-white dark:bg-gray-900">
    <x-home-header />

    {{-- Full-width single column content --}}
    <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="mt-12 lg:mt-16">
            <h1 class="text-title-lg sm:text-title-xl mb-6 font-semibold leading-tight text-gray-900 dark:text-white/90">
                        Honor Your Loved Ones.<br />
                        <span class="text-brand-500">Forever Remembered.</span>
                    </h1>
                    <p class="mb-10 max-w-md text-lg text-gray-600 dark:text-gray-400">
                        Create beautiful, lasting memorials for those who have passed. Share memories, collect tributes from family and friends, and keep their legacy alive.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('memorial.create.step1') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white shadow-theme-sm hover:bg-brand-600">
                            Create a Memorial
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-3 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Sign In
                            </a>
                        @endauth
                    </div>
        </div>

        {{-- Features --}}
        <div class="mt-16 grid gap-6 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.03] p-4">
                <div class="mb-2 text-2xl">🌸</div>
                <h3 class="font-medium text-gray-900 dark:text-white/90">Lay Flowers</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Friends and family can leave virtual flowers and candles.</p>
            </div>
            <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.03] p-4">
                <div class="mb-2 text-2xl">💬</div>
                <h3 class="font-medium text-gray-900 dark:text-white/90">Share Tributes</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Collect heartfelt messages and memories in one place.</p>
            </div>
            <div class="rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.03] p-4">
                <div class="mb-2 text-2xl">📷</div>
                <h3 class="font-medium text-gray-900 dark:text-white/90">Photos & Media</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Build a gallery of cherished photos and videos.</p>
            </div>
        </div>
    </main>

    {{-- Theme toggler --}}
    <div class="fixed right-6 bottom-6 z-50">
            <button
                class="bg-brand-500 hover:bg-brand-600 inline-flex size-14 items-center justify-center rounded-full text-white transition-colors"
                @click.prevent="$store.theme.toggle()">
                <svg class="hidden fill-current dark:block" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z" fill="" />
                </svg>
                <svg class="fill-current dark:hidden" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z" fill="" />
                </svg>
        </button>
    </div>
</div>
@endsection
