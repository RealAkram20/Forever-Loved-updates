@php
    $selectedValue = $selectedValue ?? '';
    $selectClass = $selectClass ?? 'mt-0.5 h-9 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm';
@endphp
<select name="route_name" class="{{ $selectClass }}">
    <option value="" @selected($selectedValue === '')>— Custom URL only —</option>
    @foreach ($menuRouteGroups as $groupLabel => $opts)
        <optgroup label="{{ $groupLabel }}">
            @foreach ($opts as $val => $lab)
                <option value="{{ $val }}" @selected($selectedValue === $val)>{{ $lab }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
