@php
    /*
     * Heading.
     *
     * The level-to-appearance mapping is expressed in theme tokens rather than fixed Tailwind
     * classes, so a template restyles headings by setting variables instead of forking this
     * file. Dignified's override was 57 lines that changed a font, a letter-spacing, a size
     * and a colour — all of which are now `--t-*` values it can set in one place.
     *
     * The per-widget Style-tab overrides still win, because they are inline and !important:
     * change nothing and you get the template's design, change something and you get yours.
     */
    $level = (int) ($props['level'] ?? 2);
    $level = in_array($level, [1, 2, 3, 4, 5, 6], true) ? $level : 2;
    $tag = 'h'.$level;

    $alignClass = match ($props['alignment'] ?? 'left') {
        'center' => 'text-center',
        'right' => 'text-right',
        'justify' => 'text-justify',
        default => 'text-left',
    };

    // Level 3 is the eyebrow role everywhere — small, tracked, accent-coloured. The rest are
    // headings at descending sizes. Colour stays in Tailwind so dark mode keeps working
    // without a second token per shade.
    $roleClass = match ($level) {
        1 => 't-heading t-h1 ap-title text-gray-900 dark:text-white',
        2 => 't-heading t-h2 ap-title-accent text-[var(--t-accent)]',
        3 => 't-eyebrow t-h3 ap-eyebrow text-[var(--t-accent)]',
        4 => 't-heading t-h4 text-gray-900 dark:text-white',
        5 => 't-heading t-h5 text-gray-900 dark:text-white',
        default => 't-heading t-h6 text-gray-900 dark:text-white',
    };

    $inlineStyles = [];
    if (! empty($props['color'])) $inlineStyles[] = 'color:'.e($props['color']).' !important';
    if (! empty($props['font_size'])) $inlineStyles[] = 'font-size:'.e($props['font_size']).e($props['font_size_unit'] ?? 'px');
    if (! empty($props['font_weight'])) $inlineStyles[] = 'font-weight:'.e($props['font_weight']);
    if (! empty($props['line_height'])) $inlineStyles[] = 'line-height:'.e($props['line_height']);
    if (! empty($props['letter_spacing'])) $inlineStyles[] = 'letter-spacing:'.e($props['letter_spacing']);

    $styleAttr = $inlineStyles ? implode(';', $inlineStyles) : '';
    $text = $props['text'] ?? '';
    $link = trim($props['link'] ?? '');
@endphp

<section>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <{{ $tag }} class="{{ $roleClass }} {{ $alignClass }}"@if ($styleAttr) style="{{ $styleAttr }}" @endif>@if ($link)<a href="{{ e($link) }}" class="hover:underline">{{ $text }}</a>@else{{ $text }}@endif</{{ $tag }}>
    </div>
</section>
