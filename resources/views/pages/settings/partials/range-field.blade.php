{{--
    A 0-100 slider with a live readout.

    Expects, from the caller:
      $label   — field label
      $name    — form input name, e.g. branding[cta_overlay_light]
      $dotName — matching settings key, e.g. branding.cta_overlay_light
      $default — value to fall back to when the setting is unset
      $help    — optional line under the slider
--}}
@php
    $value = old(str_replace(['[', ']'], ['.', ''], $name), $settings[$dotName] ?? $default);
@endphp

<div x-data="{ value: {{ (int) $value }} }">
    <div class="mb-2 flex items-center justify-between">
        <label for="{{ $dotName }}" class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $label }}</label>
        <span class="text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-300" x-text="value + '%'"></span>
    </div>

    <input id="{{ $dotName }}" type="range" min="0" max="100" step="5"
           name="{{ $name }}" x-model.number="value"
           class="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-brand-500 dark:bg-gray-700" />

    @if (!empty($help))
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $help }}</p>
    @endif
</div>
