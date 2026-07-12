@php
    $level = (int) ($props['level'] ?? 2);
    $level = in_array($level, [1, 2, 3, 4, 5, 6], true) ? $level : 2;
    $tag = 'h' . $level;

    $alignment = $props['alignment'] ?? 'left';
    $alignClass = match ($alignment) {
        'center' => 'text-center',
        'right' => 'text-right',
        'justify' => 'text-justify',
        default => 'text-left',
    };

    $baseClasses = match ($level) {
        1 => 'ap-title text-4xl font-bold leading-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl',
        2 => 'ap-title-accent text-4xl font-bold leading-tight text-brand-500 sm:text-5xl lg:text-6xl',
        3 => 'ap-eyebrow text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400',
        4 => 'text-xl font-semibold text-gray-900 dark:text-white',
        5 => 'text-lg font-medium text-gray-900 dark:text-white',
        default => 'text-base font-medium text-gray-900 dark:text-white',
    };

    $inlineStyles = [];
    // !important so this per-widget choice beats the site-wide Appearance
    // role colors (which are themselves !important to beat text utilities).
    if (!empty($props['color'])) $inlineStyles[] = 'color:' . e($props['color']) . ' !important';
    if (!empty($props['font_size'])) {
        $unit = $props['font_size_unit'] ?? 'px';
        $inlineStyles[] = 'font-size:' . e($props['font_size']) . e($unit);
    }
    if (!empty($props['font_weight'])) $inlineStyles[] = 'font-weight:' . e($props['font_weight']);
    if (!empty($props['line_height'])) $inlineStyles[] = 'line-height:' . e($props['line_height']);
    if (!empty($props['letter_spacing'])) $inlineStyles[] = 'letter-spacing:' . e($props['letter_spacing']);

    $styleAttr = $inlineStyles ? implode(';', $inlineStyles) : '';
    $text = $props['text'] ?? '';
    $link = trim($props['link'] ?? '');
@endphp
<section>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <{{ $tag }} class="{{ $baseClasses }} {{ $alignClass }}"@if($styleAttr) style="{{ $styleAttr }}"@endif>@if($link)<a href="{{ e($link) }}" class="hover:underline" style="{{ $styleAttr ? 'color:inherit' : '' }}">{{ $text }}</a>@else{{ $text }}@endif</{{ $tag }}>
    </div>
</section>
