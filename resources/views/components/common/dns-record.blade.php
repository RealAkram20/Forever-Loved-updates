@props(['type', 'host', 'value'])

{{--
    A DNS record laid out as the registrar's own form expects it, with each value copyable.
    Verification tokens are 32 random characters — hand-retyping one into a DNS panel is the
    single most likely way this whole flow fails, and the resulting error ("record not
    found") points at DNS rather than at the typo.
--}}
<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
    @foreach ([['Type', $type, false], ['Host', $host, true], ['Value', $value, true]] as [$label, $fieldValue, $copyable])
        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-2.5 last:border-b-0 dark:border-gray-800">
            <span class="w-12 shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $label }}</span>
            <code class="min-w-0 flex-1 truncate font-mono text-xs text-gray-800 dark:text-gray-200" title="{{ $fieldValue }}">{{ $fieldValue }}</code>
            @if ($copyable)
                <button type="button"
                    x-data="{ copied: false }"
                    @click="navigator.clipboard.writeText(@js($fieldValue)).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                    class="shrink-0 rounded-md px-2 py-1 text-xs font-medium text-gray-500 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200">
                    {{-- Text swap rather than an icon-only tick: "Copied" is unambiguous, and
                         at this size an icon change is easy to miss entirely. --}}
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Copied</span>
                </button>
            @endif
        </div>
    @endforeach
</div>
