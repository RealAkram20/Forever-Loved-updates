{{--
    Where a visitor lands after clicking unsubscribe in an update email.

    It confirms in the past tense and offers the memorial itself as the way back, because
    stopping the emails is not the same as wanting nothing more to do with the person — most
    people unsubscribing from a memorial still visit it.
--}}
@extends('layouts.visitor')

@section('page')
    <div class="mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center px-6 py-16 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/15">
            <svg class="h-7 w-7 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <h1 class="mt-6 font-serif text-2xl text-gray-900 dark:text-white/90">You have been unsubscribed</h1>

        <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
            @if ($memorial)
                You will not receive any more update emails about
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $memorial->full_name }}</span>.
            @else
                You will not receive any more update emails from this memorial.
            @endif
            Nothing else has changed, and anything you have already left stays where it is.
        </p>

        @if ($memorial)
            <a href="{{ $memorial->publicUrl() }}"
                class="btn btn-primary btn-md mt-7">
                Visit the memorial
            </a>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                You can turn updates back on from that page whenever you like.
            </p>
        @else
            <a href="{{ url('/') }}" class="btn btn-primary btn-md mt-7">Back to home</a>
        @endif
    </div>
@endsection
