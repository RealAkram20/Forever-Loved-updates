@props([
    'blocks' => [],
    'popularMemorials' => null,
    'tagline' => '',
])

@php
    $registry = app(\App\SiteBlocks\SiteBlockRegistry::class);
    $memorials = $popularMemorials ?? collect();
@endphp

@foreach ($blocks as $block)
    @php
        $type = $block['type'] ?? '';
        $class = $registry->classForType($type);
        $props = is_array($block['props'] ?? null) ? $block['props'] : [];
    @endphp
    @if ($class)
        @include($class::viewName(), [
            'props' => $props,
            'popularMemorials' => $memorials,
            'tagline' => $tagline,
        ])
    @endif
@endforeach
