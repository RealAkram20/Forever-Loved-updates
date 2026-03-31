@php
    $safe = \App\Services\WidgetHtmlSanitizer::paragraph($props['content'] ?? '');
    $extraClass = trim($props['class'] ?? '');
@endphp
<section>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="prose prose-lg prose-gray dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed {{ $extraClass }}">
            {!! $safe !!}
        </div>
    </div>
</section>
