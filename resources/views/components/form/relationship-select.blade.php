@props([
    'id' => 'relationship',
    'name' => 'relationship',
    'label' => 'Relationship',
    'value' => null,
    'other' => null,
])

@php
    $presets = [
        'Father', 'Mother', 'Spouse', 'Husband', 'Wife', 'Child', 'Son', 'Daughter',
        'Brother', 'Sister', 'Grandparent', 'Grandfather', 'Grandmother',
        'Uncle', 'Aunt', 'Cousin', 'Friend',
    ];

    // A stored relationship that isn't one of the presets was typed into the "Other"
    // box, so re-open that box with the text instead of losing it. On a redisplay
    // after failed validation the two halves arrive separately ("Other" + the text).
    $current = trim((string) ($value ?? ''));
    $typed = trim((string) ($other ?? ''));
    $isCustom = $current !== '' && ! in_array($current, $presets, true);
    $selected = $isCustom ? 'Other' : $current;
    $custom = $current === 'Other' ? $typed : ($isCustom ? $current : '');
@endphp

<div x-data="{ relationship: @js($selected) }">
    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $id }}">{{ $label }}</label>
    <select id="{{ $id }}" name="{{ $name }}" x-model="relationship"
        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 focus:ring-3 focus:outline-hidden">
        <option value="">— Select relationship —</option>
        @foreach ($presets as $preset)
            <option value="{{ $preset }}" @selected($selected === $preset)>{{ $preset }}</option>
        @endforeach
        <option value="Other" @selected($selected === 'Other')>Other</option>
    </select>

    <div class="mt-3" x-show="relationship === 'Other'" x-collapse>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $id }}_other">Please state the relationship</label>
        <input type="text" id="{{ $id }}_other" name="{{ $name }}_other" value="{{ $custom }}"
            placeholder="e.g. Godmother, neighbour, colleague"
            x-bind:required="relationship === 'Other'"
            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
    </div>
</div>
