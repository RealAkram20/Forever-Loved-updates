@extends('layouts.app')

@section('content')
    <x-common.page-header title="Reports"
        desc="Read the numbers on screen, then take them away as a PDF, an Excel file or a CSV." />

    @if ($groups->isEmpty())
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-6 py-16 text-center">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No reports available</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                Nothing here is available to your account yet.
            </p>
        </div>
    @endif

    @foreach ($groups as $groupTitle => $reports)
        <section class="mb-8 last:mb-0">
            <h3 class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $groupTitle }}</h3>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($reports as $report)
                    {{-- The whole card is the target, not a "View" link in its corner: a card
                         with one destination should not make you aim. Press feedback is a 0.99
                         scale — enough to confirm the tap, small enough not to read as motion
                         on a page an admin opens many times a day. Deliberately no entrance
                         animation here for the same reason. --}}
                    <a href="{{ route($showRoute, $report->key()) }}"
                        class="group flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 transition-[transform,border-color,background-color] duration-150 ease-out hover:border-gray-300 hover:bg-gray-50 active:scale-[0.99] dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700 dark:hover:bg-white/[0.05]">
                        <div class="flex items-start justify-between gap-3">
                            <h4 class="text-base font-medium text-gray-800 dark:text-white/90">{{ $report->title() }}</h4>

                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-300 transition-transform duration-150 ease-out group-hover:translate-x-0.5 group-hover:text-gray-400 dark:text-gray-600 dark:group-hover:text-gray-500"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M9 6l6 6-6 6" />
                            </svg>
                        </div>

                        <p class="mt-1.5 flex-1 text-sm text-gray-500 dark:text-gray-400">{{ $report->description() }}</p>

                        @if (! $report->unlockedFor(auth()->user()))
                            <span class="mt-3 inline-flex w-fit items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
                                Not in your plan
                            </span>
                        @elseif (! $report->usesDateWindow())
                            <span class="mt-3 inline-flex w-fit items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-400">
                                As of now
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
