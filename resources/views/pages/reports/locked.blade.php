@extends('layouts.app')

@section('content')
    <x-common.back-link :href="route($indexRoute)" label="All reports" />

    <x-common.page-header :title="$report->title()" :desc="$report->description()" />

    {{-- A paid capability, not forbidden data — so this explains what the report is rather
         than returning a 403. Same choice the Analytics page already makes. --}}
    <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center dark:border-gray-800 dark:bg-white/[0.03]">
        <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
            <path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z" />
        </svg>

        <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Not included in your current plan</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
            {{ $report->lockedMessage() ?? 'Get in touch to add this report to your account.' }}
        </p>
    </div>
@endsection
