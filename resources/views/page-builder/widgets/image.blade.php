@php
    $src = $props['src'] ?? '';
    $alt = $props['alt'] ?? '';
    $caption = $props['caption'] ?? '';
    $href = trim($props['href'] ?? '');
@endphp
@if ($src !== '')
<section class="py-4 sm:py-6">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <figure class="text-center">
            @if ($href !== '')
                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="inline-block">
                    <img src="{{ $src }}" alt="{{ e($alt) }}" class="max-h-[480px] w-auto max-w-full rounded-xl border border-gray-200 object-contain dark:border-gray-700 mx-auto" loading="lazy" />
                </a>
            @else
                <img src="{{ $src }}" alt="{{ e($alt) }}" class="max-h-[480px] w-auto max-w-full rounded-xl border border-gray-200 object-contain dark:border-gray-700 mx-auto" loading="lazy" />
            @endif
            @if ($caption !== '')
                <figcaption class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $caption }}</figcaption>
            @endif
        </figure>
    </div>
</section>
@endif
