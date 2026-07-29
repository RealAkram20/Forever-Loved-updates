@props(['status'])

@php
    [$styles, $label] = match ($status) {
        'verified' => ['bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-900/25 dark:text-green-400 dark:ring-green-500/20', 'Verified'],
        'failed' => ['bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/25 dark:text-red-400 dark:ring-red-500/20', 'Failed'],
        default => ['bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/25 dark:text-amber-400 dark:ring-amber-500/20', 'Unverified'],
    };
@endphp

<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[0.6875rem] font-medium ring-1 ring-inset {{ $styles }}">{{ $label }}</span>
