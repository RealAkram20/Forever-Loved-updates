@php
    /*
     * Prose.
     *
     * `prose` is gone deliberately. The plugin imposes its own type scale, measure and link
     * colour over whatever the template has set, so a paragraph widget and a hand-written page
     * came out looking like two different websites. The rules below read from the same tokens
     * every other widget uses, which is what lets a template restyle body copy without
     * overriding this file.
     */
    $safe = \App\Services\WidgetHtmlSanitizer::paragraph($props['content'] ?? '');
    $extraClass = trim($props['class'] ?? '');
@endphp

<section>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="t-body text-gray-700 dark:text-gray-300
            [&_h2]:t-heading [&_h2]:t-h4 [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-gray-900 [&_h2]:dark:text-white
            [&_h3]:t-heading [&_h3]:t-h5 [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-gray-900 [&_h3]:dark:text-white
            [&_p]:mb-4
            [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5
            [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-1.5
            [&_blockquote]:my-5 [&_blockquote]:border-l-2 [&_blockquote]:border-[var(--t-accent)] [&_blockquote]:pl-5 [&_blockquote]:italic
            [&_a]:text-[var(--t-accent)] [&_a]:underline [&_a]:underline-offset-2
            [&_strong]:text-gray-900 [&_strong]:dark:text-white
            {{ $extraClass }}">
            {!! $safe !!}
        </div>
    </div>
</section>
