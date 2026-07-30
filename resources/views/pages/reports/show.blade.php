@extends('layouts.app')

@section('content')
    @php
        $currentQuery = request()->except('page');
        $downloadQuery = fn (string $format) => route($downloadRoute, array_merge(
            ['report' => $report->key(), 'format' => $format],
            $currentQuery,
        ));
        $formatLabels = ['pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'];
    @endphp

    <x-common.back-link :href="route($indexRoute)" label="All reports" />

    <x-common.page-header :title="$report->title()" :desc="$report->description()" />

    {{-- Filters and downloads share one bar: choosing a period and taking the result away
         are the same task, and separating them makes people export the wrong window. --}}
    <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] lg:flex-row lg:items-center lg:justify-between">
        @if ($report->usesDateWindow())
            <div x-data="{ custom: {{ $filters->preset === 'custom' ? 'true' : 'false' }} }" class="flex flex-wrap items-center gap-2">
                @foreach (\App\Reports\ReportFilters::PRESETS as $value => $label)
                    @if ($value === 'custom')
                        <button type="button" @click="custom = !custom"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-[transform,background-color,color] duration-150 ease-out active:scale-[0.97] {{ $filters->preset === 'custom' ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]' }}">
                            {{ $label }}
                        </button>
                    @else
                        <a href="{{ route($showRoute, array_merge(['report' => $report->key()], ['preset' => $value])) }}"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-[transform,background-color,color] duration-150 ease-out active:scale-[0.97] {{ $filters->preset === $value ? 'bg-brand-500 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]' }}">
                            {{ $label }}
                        </a>
                    @endif
                @endforeach

                <form x-show="custom" x-cloak method="GET" action="{{ route($showRoute, $report->key()) }}"
                    class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="preset" value="custom">
                    <input type="date" name="from" value="{{ $filters->from?->format('Y-m-d') }}"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <span class="text-sm text-gray-400">to</span>
                    <input type="date" name="to" value="{{ $filters->to?->format('Y-m-d') }}"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-2.5 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <button type="submit" class="btn btn-primary btn-sm transition-transform duration-150 ease-out active:scale-[0.97]">Apply</button>
                </form>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                A snapshot as it stands right now — this report has no date range.
            </p>
        @endif

        <div class="flex items-center gap-2">
            @foreach ($formats as $format)
                <a href="{{ $downloadQuery($format) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 transition-[transform,background-color,border-color] duration-150 ease-out hover:border-gray-300 hover:bg-gray-50 active:scale-[0.97] dark:border-gray-700 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-white/[0.06]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                    </svg>
                    {{ $formatLabels[$format] ?? strtoupper($format) }}
                </a>
            @endforeach
        </div>
    </div>

    @if ($report->usesDateWindow() && $report->dateWindowNote())
        <p class="mb-6 -mt-2 text-xs text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-600 dark:text-gray-300">{{ $filters->label() }}</span>
            &middot; {{ $report->dateWindowNote() }}
        </p>
    @endif

    @if ($stats)
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($stats as $stat)
                @php
                    $tone = match ($stat->tone) {
                        \App\Reports\ReportStat::TONE_POSITIVE => 'text-emerald-600 dark:text-emerald-400',
                        \App\Reports\ReportStat::TONE_WARNING => 'text-amber-600 dark:text-amber-400',
                        \App\Reports\ReportStat::TONE_DANGER => 'text-red-600 dark:text-red-400',
                        default => 'text-gray-800 dark:text-white/90',
                    };
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $stat->label }}</p>
                    <p class="mt-1.5 text-xl font-semibold tabular-nums {{ $tone }}">{{ $stat->value }}</p>
                    @if ($stat->hint)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat->hint }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        @if ($paginator->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4.5h14M5 12h14M5 19.5h9"/></svg>
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Nothing in this period</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    @if ($report->usesDateWindow())
                        No rows fall between {{ $filters->label() }}. Try a wider range.
                    @else
                        There is nothing to show here yet.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            @foreach ($columns as $column)
                                <th class="whitespace-nowrap px-4 py-3 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 {{ $column->align() === 'right' ? 'text-right' : 'text-left' }}">
                                    {{ $column->label }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($paginator as $row)
                            <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                @foreach ($columns as $column)
                                    {{-- tabular-nums on numeric columns so digits sit in a column
                                         you can scan for magnitude rather than read one by one. --}}
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-700 dark:text-gray-300 {{ $column->align() === 'right' ? 'text-right tabular-nums' : 'text-left' }}">
                                        {{ $formatter->display($column, $row) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($paginator->hasPages())
                <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                    {{ $paginator->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>

    @if ($paginator->total() > 2000)
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            This report has {{ number_format($paginator->total()) }} rows. The PDF prints the first 2,000 —
            the Excel and CSV downloads contain all of them.
        </p>
    @endif
@endsection
