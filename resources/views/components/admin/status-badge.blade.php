@props(['status'])

@php
    $styles = match ($status) {
        'active' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-900/25 dark:text-green-400 dark:ring-green-500/20',
        'suspended' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/25 dark:text-red-400 dark:ring-red-500/20',
        default => 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600/30',
    };
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $styles }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    {{ ucfirst($status) }}
</span>
