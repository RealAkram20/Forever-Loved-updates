{{--
    A type-ahead for one of the two fields on the payment-order form.

    Replaces a <select> that rendered every user, or every memorial, on the platform. That was
    slow to load, got worse with every signup, and made the one row you usually want — your own
    — impossible to find in an alphabetical list of everybody.

    The two instances talk to each other through a shared Alpine store rather than by knowing
    about one another: picking a memorial fills in its owner, and picking a user narrows the
    memorial search to pages that person actually owns. The server still refuses a mismatched
    pair, and that guard stays — this is about not reaching it by hand.

    @param string $name         form field name, posted as a hidden input
    @param string $type         users | memorials — which side of the search endpoint to ask
    @param string $model        'user' or 'memorial', the role this field plays in the pair
    @param string $placeholder
    @param mixed  $value        old() id, to survive a validation round trip
--}}
@props(['name', 'type', 'model', 'placeholder' => 'Search...', 'value' => null])

<div x-data="optionSearch({
        name: @js($name),
        type: @js($type),
        model: @js($model),
        initialId: @js($value),
        endpoint: @js(route('settings.payment-orders.options')),
     })"
     x-init="init()"
     @click.outside="open = false"
     class="relative">

    <input type="hidden" name="{{ $name }}" :value="selectedId" required>

    <input type="text"
           x-model="term"
           @focus="open = true; search()"
           @input.debounce.250ms="search()"
           @keydown.escape.stop="open = false"
           @keydown.arrow-down.prevent="move(1)"
           @keydown.arrow-up.prevent="move(-1)"
           @keydown.enter.prevent="choose(results[cursor])"
           :placeholder="placeholderFor()"
           autocomplete="off"
           class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">

    {{-- What is currently chosen, since the input holds a search term rather than the choice. --}}
    <p x-show="selectedLabel" x-cloak class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="selectedLabel"></span>
        <button type="button" @click="clear()" class="ml-1 text-brand-500 hover:underline">change</button>
    </p>

    <div x-show="open && (results.length || loading || term)" x-cloak x-transition.opacity.duration.150ms
         class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">

        <p x-show="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Searching…</p>

        <p x-show="!loading && !results.length" x-cloak class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
            <span x-text="emptyMessage()"></span>
        </p>

        <template x-for="(row, i) in results" :key="row.id">
            <button type="button"
                    @click="choose(row)"
                    @mouseenter="cursor = i"
                    :class="cursor === i ? 'bg-brand-50 dark:bg-brand-500/10' : ''"
                    class="block w-full px-3 py-2 text-left transition-colors duration-100">
                <span class="block text-sm text-gray-800 dark:text-white/90" x-text="row.label"></span>
                <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="row.sub"></span>
            </button>
        </template>
    </div>
</div>

@once
    @push('scripts')
        <script>
            /**
             * The pair share one small store so neither field has to know the other exists:
             * each writes what it picked, and each reads the other's pick to narrow itself.
             */
            document.addEventListener('alpine:init', () => {
                Alpine.store('orderPair', { userId: null, memorialId: null, fillUser: null });

                Alpine.data('optionSearch', (config) => ({
                    term: '',
                    results: [],
                    open: false,
                    loading: false,
                    cursor: 0,
                    selectedId: config.initialId ?? '',
                    selectedLabel: '',

                    init() {
                        // Picking a memorial fills in its owner. Done through the store rather
                        // than by reaching across the DOM, so the user field stays in charge of
                        // its own label and hidden input.
                        if (config.model === 'user') {
                            this.$watch('$store.orderPair.fillUser', (fill) => {
                                if (!fill || String(fill.id) === String(this.selectedId)) return;
                                this.selectedId = fill.id;
                                this.selectedLabel = fill.label;
                                this.term = '';
                                Alpine.store('orderPair').userId = fill.id;
                            });
                        }

                        // And changing the user invalidates a memorial that belonged to somebody
                        // else — leaving it selected is exactly the mismatch the server rejects.
                        if (config.model === 'memorial') {
                            this.$watch('$store.orderPair.userId', () => {
                                if (!this.selectedId) return;
                                this.clear();
                            });
                        }
                    },

                    placeholderFor() {
                        return this.selectedId ? 'Search again…' : config.placeholder;
                    },

                    emptyMessage() {
                        if (config.model === 'memorial' && Alpine.store('orderPair').userId) {
                            return 'No memorials for that user.';
                        }
                        return this.term ? 'Nothing found.' : 'Type to search.';
                    },

                    async search() {
                        this.loading = true;
                        this.cursor = 0;

                        const params = new URLSearchParams({ type: config.type, q: this.term });

                        // Only ever offer memorials the chosen person actually owns.
                        if (config.model === 'memorial' && Alpine.store('orderPair').userId) {
                            params.set('user_id', Alpine.store('orderPair').userId);
                        }

                        try {
                            const res = await fetch(`${config.endpoint}?${params}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            this.results = res.ok ? (await res.json()).results ?? [] : [];
                        } catch (e) {
                            this.results = [];
                        }

                        this.loading = false;
                    },

                    move(delta) {
                        if (!this.results.length) return;
                        this.cursor = (this.cursor + delta + this.results.length) % this.results.length;
                    },

                    choose(row) {
                        if (!row) return;

                        this.selectedId = row.id;
                        this.selectedLabel = row.label;
                        this.term = '';
                        this.open = false;

                        const store = Alpine.store('orderPair');

                        if (config.model === 'user') {
                            store.userId = row.id;
                        } else {
                            store.memorialId = row.id;
                            // Hand the owner over, so the person is filled in rather than hunted for.
                            if (row.user_id) store.fillUser = { id: row.user_id, label: row.sub };
                        }
                    },

                    clear() {
                        this.selectedId = '';
                        this.selectedLabel = '';
                        this.term = '';
                        this.open = true;
                        this.search();

                        const store = Alpine.store('orderPair');
                        if (config.model === 'user') store.userId = null;
                        else store.memorialId = null;
                    },
                }));
            });
        </script>
    @endpush
@endonce
