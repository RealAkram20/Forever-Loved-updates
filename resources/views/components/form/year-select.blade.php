@props([
    'id' => 'year-' . uniqid(),
    'name' => null,
    'value' => '',
    'placeholder' => 'Select year',
    'min' => 1900,
])

<select id="{{ $id }}" name="{{ $name }}"
    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent dark:bg-gray-900/80 px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 focus:ring-3 focus:outline-hidden">
    <option value="">{{ $placeholder }}</option>
    @for ($y = now()->year; $y >= $min; $y--)
        <option value="{{ $y }}" @selected((string) $value === (string) $y)>{{ $y }}</option>
    @endfor
</select>
