@props([
    'widgets' => [],
    'context' => [],
    'editable' => false,
])

@php
    use App\PageBuilder\Support\WidgetChrome;

    $registry = app(\App\PageBuilder\WidgetRegistry::class);
@endphp

@foreach ($widgets as $widget)
    @php
        $type = $widget['type'] ?? '';
        $class = $registry->classForType($type);
        $props = is_array($widget['props'] ?? null) ? $widget['props'] : [];
        $hidden = WidgetChrome::isHidden($props);
        $wrapperStyle = WidgetChrome::wrapperStyle($props);
        $cssId = WidgetChrome::cssId($props);
        $cssClass = WidgetChrome::cssClass($props);
        $needsWrapper = $editable || $hidden || $wrapperStyle !== '' || $cssId !== null || $cssClass !== null;
    @endphp
    @if ($class && (! $hidden || $editable))
        @if ($needsWrapper)
            <div
                @if ($editable) data-pb-id="{{ $widget['id'] ?? '' }}" @endif
                @if ($editable && $hidden) data-pb-hidden="1" @endif
                @if ($cssId) id="{{ $cssId }}" @endif
                @if ($cssClass) class="{{ $cssClass }}" @endif
                @if ($wrapperStyle) style="{{ $wrapperStyle }}" @endif
            >
                @include($class::viewName(), array_merge(['props' => $props], $context))
            </div>
        @else
            @include($class::viewName(), array_merge(['props' => $props], $context))
        @endif
    @endif
@endforeach
