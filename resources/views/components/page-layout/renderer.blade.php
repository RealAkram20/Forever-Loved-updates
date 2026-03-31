@props([
    'widgets' => [],
    'context' => [],
])

@php
    $registry = app(\App\PageBuilder\WidgetRegistry::class);

    function pbSpacingStyle(array $props): string {
        $spacing = $props['_spacing'] ?? null;
        if (!is_array($spacing)) return '';
        $parts = [];
        foreach (['margin', 'padding'] as $group) {
            $box = $spacing[$group] ?? null;
            if (!is_array($box)) continue;
            $t = $box['top'] ?? '0';
            $r = $box['right'] ?? '0';
            $b = $box['bottom'] ?? '0';
            $l = $box['left'] ?? '0';
            $u = in_array($box['unit'] ?? 'px', ['px','em','%','rem'], true) ? $box['unit'] : 'px';
            if ($t === '0' && $r === '0' && $b === '0' && $l === '0') continue;
            $parts[] = $group . ':' . $t.$u . ' ' . $r.$u . ' ' . $b.$u . ' ' . $l.$u;
        }
        return implode(';', $parts);
    }
@endphp

@foreach ($widgets as $widget)
    @php
        $type = $widget['type'] ?? '';
        $class = $registry->classForType($type);
        $props = is_array($widget['props'] ?? null) ? $widget['props'] : [];
        $spacingStyle = pbSpacingStyle($props);
    @endphp
    @if ($class)
        @if ($spacingStyle)
            <div style="{{ $spacingStyle }}">
                @include($class::viewName(), array_merge(['props' => $props], $context))
            </div>
        @else
            @include($class::viewName(), array_merge(['props' => $props], $context))
        @endif
    @endif
@endforeach
