@props([
    'size' => 'md',
    'variant' => 'primary',
    'href' => null,
    'startIcon' => null,
    'endIcon' => null,
    'block' => false,
    'icon' => false,
    'className' => '',
    'disabled' => false,
])

@php
    $sizeClass = in_array($size, ['sm', 'md', 'lg'], true) ? "btn-{$size}" : 'btn-md';

    $variantClass = match ($variant) {
        'secondary', 'outline' => 'btn-secondary',
        'danger' => 'btn-danger',
        'danger-soft' => 'btn-danger-soft',
        'ghost' => 'btn-ghost',
        'link' => 'btn-link',
        default => 'btn-primary',
    };

    $classes = trim(implode(' ', array_filter([
        'btn',
        $sizeClass,
        $variantClass,
        $icon ? 'btn-icon' : '',
        $block ? 'btn-block' : '',
        $disabled ? 'btn-disabled' : '',
        $className,
    ])));
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($startIcon) {!! $startIcon !!} @endif
        {{ $slot }}
        @if ($endIcon) {!! $endIcon !!} @endif
    </a>
@else
    <button
        {{ $attributes->merge(['class' => $classes, 'type' => $attributes->get('type', 'button')]) }}
        @disabled($disabled)
    >
        @if ($startIcon) {!! $startIcon !!} @endif
        {{ $slot }}
        @if ($endIcon) {!! $endIcon !!} @endif
    </button>
@endif
