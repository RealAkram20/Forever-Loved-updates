@props(['reseller' => null])

{{-- Who owns a record: a named reseller, or the platform directly. Rendered by the
     global admin lists now that users, memorials, plans and orders can belong to either. --}}
@if ($reseller)
    <a href="{{ route('settings.resellers.show', $reseller) }}"
        class="inline-flex max-w-[12rem] items-center gap-1.5 truncate rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 transition-colors duration-150 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20">
        <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.25 8.75h17.5M4.75 8.75V19a1.5 1.5 0 0 0 1.5 1.5h11.5a1.5 1.5 0 0 0 1.5-1.5V8.75M3.25 8.75l1.6-4.05A1.5 1.5 0 0 1 6.25 3.75h11.5a1.5 1.5 0 0 1 1.4.95l1.6 4.05"/></svg>
        <span class="truncate">{{ $reseller->name }}</span>
    </a>
@else
    <span class="text-xs text-gray-400 dark:text-gray-500">Direct</span>
@endif
