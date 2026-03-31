@php
    $items = is_array($props['items'] ?? null) ? $props['items'] : [];
    $items = array_values(array_filter($items, fn ($t) => is_string($t) && trim($t) !== ''));
    $alignment = in_array($props['alignment'] ?? '', ['left', 'center'], true) ? $props['alignment'] : 'left';
    $listAlign = $alignment === 'center' ? 'items-center' : 'items-start';
    $textAlign = $alignment === 'center' ? 'text-center' : 'text-left';
@endphp
@if (count($items) > 0)
<section class="py-6 sm:py-8">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 {{ $textAlign }}">
        @if (!empty($props['title']))
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $props['title'] }}</h3>
        @endif
        <ul class="flex flex-col gap-3 sm:gap-4 {{ $listAlign }}">
            @foreach ($items as $item)
                <li class="flex items-start gap-3 max-w-lg">
                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center text-green-500" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
