@extends('layouts.app')

@section('content')
    <x-common.page-header title="Analytics"
        desc="How much attention the memorials you host are receiving." />

    @if ($locked)
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-6 py-16 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Not included in your {{ $reseller->tier?->name ?? 'current' }} tier</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                Analytics shows visitor numbers, unique visitors and which memorials are being viewed most — useful when you want to show a family how many people came to remember someone. Get in touch to add it.
            </p>
        </div>
    @else
        {{-- Counts are headline numbers, not charts: there is no comparison or trend in a
             single total, so a plot would add ink without adding meaning. --}}
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Views, last '.$windowDays.' days', 'value' => number_format($viewsInWindow)],
                ['label' => 'Unique visitors', 'value' => number_format($visitorsInWindow)],
                ['label' => 'Views, all time', 'value' => number_format($viewsAllTime)],
                ['label' => 'Tributes left', 'value' => number_format($tributesAllTime)],
            ] as $stat)
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $stat['label'] }}</p>
                    <p class="mt-1.5 text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        @php
            $peak = max(1, $series->max('views'));
        @endphp

        <div class="mb-6 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-6">
            {{-- One series, so the title names it and no legend box is needed. Single hue,
                 validated against both the light and dark chart surfaces. --}}
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Daily views</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Peak {{ number_format($peak) }} in a day</p>
            </div>

            @if ($viewsInWindow === 0)
                <p class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    No views recorded in the last {{ $windowDays }} days yet.
                </p>
            @else
                {{-- Bars sit on a shared baseline with a 2px gap between them, 4px rounded
                     tops, and a per-bar hover tooltip. Empty days render a hairline rather
                     than nothing, so a gap reads as zero instead of as missing time. --}}
                <div class="mt-6 flex h-40 items-end gap-[2px]" role="img"
                    aria-label="Daily memorial views over the last {{ $windowDays }} days">
                    @foreach ($series as $point)
                        <div class="group relative flex h-full flex-1 items-end" x-data>
                            <div class="w-full rounded-t bg-brand-500 transition-colors duration-150 group-hover:bg-brand-600 dark:bg-brand-400 dark:group-hover:bg-brand-300"
                                style="height: {{ $point['views'] > 0 ? max(2, round($point['views'] / $peak * 100)) : 0.5 }}%"></div>

                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                                <span class="font-medium tabular-nums">{{ number_format($point['views']) }}</span>
                                {{ \Illuminate\Support\Str::plural('view', $point['views']) }}
                                <span class="text-gray-400">&middot; {{ $point['label'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Two endpoint labels rather than thirty: a date on every bar is unreadable
                     at this width and tells you nothing the tooltip does not. --}}
                <div class="mt-2 flex justify-between text-xs text-gray-400 dark:text-gray-500">
                    <span>{{ $series->first()['label'] }}</span>
                    <span>{{ $series->last()['label'] }}</span>
                </div>
            @endif
        </div>

        {{-- The table view: identity here is a name, which a chart would only obscure. --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
            <div class="px-6 py-5">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Most visited memorials</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">All time, across everything you host.</p>
            </div>

            @if ($topMemorials->isEmpty())
                <p class="border-t border-gray-100 dark:border-gray-800 px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Nothing to report yet — this fills in as families visit.
                </p>
            @else
                <div class="overflow-x-auto border-t border-gray-100 dark:border-gray-800">
                    <table class="w-full min-w-[32rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Memorial</th>
                                <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Views</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Tributes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($topMemorials as $memorial)
                                <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3.5">
                                        <a href="{{ $memorial->publicUrl() }}" target="_blank" rel="noopener"
                                            class="font-medium text-gray-800 transition-colors duration-150 hover:text-brand-500 dark:text-white/90 dark:hover:text-brand-400">
                                            {{ $memorial->full_name }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($memorial->views_count) }}</td>
                                    <td class="px-6 py-3.5 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($memorial->tributes_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
