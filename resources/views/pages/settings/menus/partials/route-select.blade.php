@php
    $selectedValue = $selectedValue ?? '';
    // `min-w-0` matters here. A select's minimum content width is its widest option, and
    // `w-full` does not stop that minimum from pushing its flex parent wider — with route
    // labels like "Find Memorial · /Forever/find-memorial" that was enough to overflow a
    // phone. Zeroing the minimum lets the cell shrink; the select clips its own option text,
    // which is what a narrow select is supposed to do.
    $selectClass = $selectClass ?? 'mt-0.5 h-9 w-full min-w-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm';
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
