@extends('layouts.app')

@section('content')
    @php
        $downloadBase = route('memorials.report.download', $memorial->slug);
        $query = array_filter(request()->only(['preset', 'from', 'to']));
    @endphp

    <x-common.back-link :href="$backUrl" :label="$backLabel" />

    <x-common.page-header :title="$memorial->full_name"
        :desc="'What has happened on this memorial ' . ($filters->isBounded() ? 'between ' . $filters->label() : 'since it was published') . '.'">
        <x-slot:actions>
            <a href="{{ $downloadBase . '?' . http_build_query($query + ['without_messages' => 1]) }}"
                class="btn btn-secondary btn-md transition-transform duration-150 ease-out active:scale-[0.97]">
                PDF without messages
            </a>
            <a href="{{ $downloadBase . ($query ? '?' . http_build_query($query) : '') }}"
                class="btn btn-primary btn-md transition-transform duration-150 ease-out active:scale-[0.97]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                </svg>
                Download PDF
            </a>
        </x-slot:actions>
    </x-common.page-header>

    {{-- Visitors are only ever counted — memorial_views holds a hash and a timestamp and
         nothing else. This page says how many people came and never implies who. --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => Str::plural('person', $visitors) . ' visited', 'value' => number_format($visitors)],
            ['label' => 'Visits in total', 'value' => number_format($visits)],
            ['label' => Str::plural('tribute', $tributeCount) . ' left', 'value' => number_format($tributeCount)],
            ['label' => 'Times shared', 'value' => number_format($shareCount)],
        ] as $stat)
            <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $stat['label'] }}</p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums text-gray-800 dark:text-white/90">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($series->isNotEmpty() && $series->max('visits') > 0)
        @php $peak = max(1, $series->max('visits')); @endphp

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Visits over time</h3>
                @if ($busiestDay && $busiestDay['visits'] > 0)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Busiest {{ $busiestDay['date']->format('j M Y') }} &middot; {{ number_format($busiestDay['visits']) }}
                    </p>
                @endif
            </div>

            <div class="mt-6 flex h-40 items-end gap-[2px]" role="img"
                aria-label="Visits to this memorial over {{ $series->count() }} days">
                @foreach ($series as $point)
                    <div class="group relative flex h-full flex-1 items-end">
                        <div class="w-full rounded-t bg-brand-500 transition-colors duration-150 group-hover:bg-brand-600 dark:bg-brand-400 dark:group-hover:bg-brand-300"
                            style="height: {{ $point['visits'] > 0 ? max(2, round($point['visits'] / $peak * 100)) : 0.5 }}%"></div>

                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2.5 py-1.5 text-xs text-white shadow-lg group-hover:block dark:bg-gray-700">
                            <span class="font-medium tabular-nums">{{ number_format($point['visits']) }}</span>
                            {{ Str::plural('visit', $point['visits']) }}
                            <span class="text-gray-400">&middot; {{ $point['label'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>{{ $series->first()['label'] }}</span>
                <span>{{ $series->last()['label'] }}</span>
            </div>
        </div>
    @endif

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="mb-4 text-base font-medium text-gray-800 dark:text-white/90">Tributes</h3>
            @if ($tributesByType->isNotEmpty())
                <dl class="space-y-2.5 text-sm">
                    @foreach ($tributesByType as $type => $count)
                        <div class="flex items-baseline justify-between border-b border-gray-100 pb-2.5 last:border-0 dark:border-gray-800">
                            <dt class="text-gray-600 dark:text-gray-400">{{ ucfirst($type) }}s</dt>
                            <dd class="font-medium tabular-nums text-gray-800 dark:text-white/90">{{ number_format($count) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">No tributes have been left yet.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <h3 class="mb-4 text-base font-medium text-gray-800 dark:text-white/90">The memorial itself</h3>
            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    'Story chapters' => $chapterCount,
                    'Photographs' => $photoCount,
                    'Videos' => $videoCount,
                    'People helping' => $collaborators->count(),
                ] as $label => $count)
                    <div class="flex items-baseline justify-between border-b border-gray-100 pb-2.5 last:border-0 dark:border-gray-800">
                        <dt class="text-gray-600 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-medium tabular-nums text-gray-800 dark:text-white/90">{{ number_format($count) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    @if ($messages->isNotEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-medium text-gray-800 dark:text-white/90">What people wrote</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Included in the PDF unless you choose the version without messages
                </p>
            </div>

            <div class="space-y-5">
                @foreach ($messages as $message)
                    <blockquote class="border-l-2 border-gray-200 pl-4 dark:border-gray-700">
                        <p class="text-sm italic text-gray-700 dark:text-gray-300">&ldquo;{{ $message->message }}&rdquo;</p>
                        <footer class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
                            {{ $message->user?->name ?? $message->guest_name ?? 'A visitor' }}
                            &middot; {{ $message->created_at->format('j M Y') }}
                        </footer>
                    </blockquote>
                @endforeach
            </div>
        </div>
    @endif
@endsection
