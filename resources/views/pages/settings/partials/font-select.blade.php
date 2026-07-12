{{--
    Font family picker. Expects from the caller:
      $id, $name  — element id and form input name
      $current    — the currently saved family ('' = theme default)
    Inherits $googleFonts and $customNames from the page scope.
    The page's [data-font-select] script live-loads the chosen Google family
    and styles the select (or a sibling [data-font-preview]) with it.
--}}
<select id="{{ $id }}" name="{{ $name }}" data-font-select
    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 dark:bg-gray-900/80 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
    <option value="">Theme default</option>
    @if (count($customNames))
        <optgroup label="Uploaded fonts">
            @foreach ($customNames as $fontName)
                <option value="{{ $fontName }}" data-custom @selected($current === $fontName)>{{ $fontName }}</option>
            @endforeach
        </optgroup>
    @endif
    @foreach ($googleFonts as $category => $families)
        <optgroup label="Google Fonts — {{ $category }}">
            @foreach ($families as $family)
                <option value="{{ $family }}" @selected($current === $family)>{{ $family }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
