@props(['reseller', 'copy' => true, 'note' => true])

{{--
    A reseller's public address, as it can actually be reached right now.

    Six pages used to build this inline as `{{ $slug }}.{{ config('reseller.domain') }}`,
    which on a subdirectory install printed a confident acme.foreverloved.com — a host
    nothing answers on — and the copy button prefixed it with a hardcoded https://.

    When the environment cannot serve the real host, Reseller::publicBaseUrl() hands back
    the /r/{slug} fallback and this says so, naming the address the reseller will actually
    hand to a family once DNS and APP_URL are in place. Showing the working address without
    that note would be a different kind of wrong: the reseller would give /r/acme to a
    client and it would break at launch.
--}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-white/[0.03] px-3 py-2">
        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m5.656-5.656l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0"/></svg>
        <code class="min-w-0 flex-1 truncate text-sm text-gray-700 dark:text-gray-300" title="{{ $reseller->publicDisplayAddress() }}">{{ $reseller->publicDisplayAddress() }}</code>
        @if ($copy)
            <button type="button" title="Copy"
                x-data="{ copied: false }"
                @click="navigator.clipboard.writeText(@js($reseller->publicBaseUrl())).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                class="ml-1 shrink-0 rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <svg x-show="copied" x-cloak class="h-4 w-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </button>
        @endif
    </div>

    @if ($note && $reseller->usingFallbackAddress())
        <p class="mt-2 flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-500">
            <svg class="mt-px h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 9v4m0 3v.01M10.3 4.3 2.6 17.6A1.5 1.5 0 0 0 3.9 20h16.2a1.5 1.5 0 0 0 1.3-2.4L13.7 4.3a1.5 1.5 0 0 0-2.6 0Z"/></svg>
            <span>Temporary address for this environment. Once DNS and <code class="font-mono">APP_URL</code> are set up, it becomes <code class="font-mono">{{ $reseller->publicHost() }}</code>.</span>
        </p>
    @endif
</div>
