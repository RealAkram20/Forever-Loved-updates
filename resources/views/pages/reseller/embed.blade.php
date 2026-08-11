@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Embed on your website</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Put your memorials on any website you own — a page on your existing site, a partner's site, anywhere HTML goes. Pick what to show, copy the snippet, paste it. It stays in your branding and updates itself.
        </p>
    </div>

    @unless ($embeddingInTier)
        <div class="flex items-start gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/[0.03] p-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Embedding isn't included in your {{ $reseller->tier?->name ?? 'current' }} tier — the snippet below will show visitors a "not included" message until it's added. Get in touch to enable it.
            </p>
        </div>
    @endunless

    <x-common.component-card title="What to show">
        <div x-data="embedBuilder()" class="space-y-5">
            {{-- Mode --}}
            <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="radio" value="all" x-model="mode" class="text-brand-500 focus:ring-brand-500/30">
                    All my memorials <span class="text-xs text-gray-400">(with search &amp; pages)</span>
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="radio" value="picked" x-model="mode" class="text-brand-500 focus:ring-brand-500/30">
                    Only the ones I pick
                </label>
            </div>

            {{-- Picker: the search field's dropdown is where the choosing happens --}}
            <div x-show="mode === 'picked'" x-cloak class="space-y-3">
                <div class="relative max-w-md">
                    <input type="search" x-model="q" @input.debounce.300ms="search()" @focus="search()"
                           @keydown.escape="results = []"
                           placeholder="Search your memorials…"
                           class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">

                    <div x-show="results.length" x-cloak @click.outside="results = []"
                         class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                        <template x-for="item in results" :key="item.slug">
                            <button type="button" @click="toggle(item)"
                                    class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                                <template x-if="item.photo">
                                    <img :src="item.photo" alt="" class="h-9 w-9 rounded-full object-cover">
                                </template>
                                <template x-if="!item.photo">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 text-sm" x-text="(item.name || '?').charAt(0)"></span>
                                </template>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="item.name"></span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="item.years || ''"></span>
                                </span>
                                <span class="text-xs font-medium" :class="isPicked(item.slug) ? 'text-brand-500' : 'text-gray-400'"
                                      x-text="isPicked(item.slug) ? 'Added ✓' : 'Add'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- The picked set, as removable chips --}}
                <div class="flex flex-wrap gap-2" x-show="picked.length" x-cloak>
                    <template x-for="item in picked" :key="item.slug">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 dark:bg-brand-500/15 px-3 py-1 text-sm text-brand-700 dark:text-brand-400">
                            <span x-text="item.name"></span>
                            <button type="button" @click="remove(item.slug)" class="text-brand-400 hover:text-brand-600" aria-label="Remove">&times;</button>
                        </span>
                    </template>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400" x-show="!picked.length">
                    Nothing picked yet — search above and add memorials from the results. One memorial embeds as a single card; two or more become a small directory.
                </p>
            </div>

            {{-- The snippet --}}
            <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Copy this into any page</p>
                <div class="relative">
                    <pre class="overflow-x-auto rounded-xl bg-gray-900 p-4 text-xs leading-relaxed text-gray-100"><code x-text="snippet()"></code></pre>
                    <button type="button" @click="copy()"
                            class="absolute right-3 top-3 rounded-lg bg-white/10 px-3 py-1 text-xs font-medium text-white hover:bg-white/20 transition"
                            x-text="copied ? 'Copied ✓' : 'Copy'"></button>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    The widget sizes itself, shows your logo and colours, and every card links back to the memorial on your site. Only public, active memorials appear.
                </p>
            </div>
        </div>
    </x-common.component-card>
</div>

<script>
    function embedBuilder() {
        return {
            mode: 'all',
            q: '',
            results: [],
            picked: [],
            copied: false,

            search() {
                fetch('{{ route('reseller.embed.search') }}?q=' + encodeURIComponent(this.q), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(r => r.ok ? r.json() : { data: [] })
                    .then(payload => { this.results = payload.data || []; });
            },
            isPicked(slug) {
                return this.picked.some(p => p.slug === slug);
            },
            toggle(item) {
                this.isPicked(item.slug)
                    ? this.remove(item.slug)
                    : this.picked.push({ slug: item.slug, name: item.name });
            },
            remove(slug) {
                this.picked = this.picked.filter(p => p.slug !== slug);
            },
            snippet() {
                const origin = @json($embedOrigin);
                if (this.mode === 'picked' && this.picked.length === 1) {
                    return '<script src="' + origin + '/embed.js" data-memorial="' + this.picked[0].slug + '"><\/script>';
                }
                const value = this.mode === 'picked' && this.picked.length
                    ? this.picked.map(p => p.slug).join(',')
                    : 'all';
                return '<script src="' + origin + '/embed.js" data-memorials="' + value + '"><\/script>';
            },
            copy() {
                navigator.clipboard.writeText(this.snippet()).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1600);
                });
            },
        };
    }
</script>
@endsection
