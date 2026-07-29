@props(['title', 'desc' => ''])

{{-- Title, one line of context, and the page's primary action. Replaces the breadcrumb
     on pages that have something to do rather than just somewhere you are. --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h2>
        @if ($desc)
            <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $desc }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
