@props([
    'id' => 'city-' . uniqid(),
    'name' => null,
    'label' => 'City or town',
    'value' => '',
    'placeholder' => 'Search city or town...',
    'stateFieldId' => null,
])

<div
    x-data="citySelectComponent('{{ $id }}', '{{ addslashes($value) }}', '{{ $stateFieldId }}')"
    x-init="init()"
    class="relative"
    @click.away="close()"
    @keydown.escape.prevent="close()"
    @state-selected.window="handleStateSelected($event)"
>
    @if($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    @endif

    <input type="hidden" name="{{ $name }}" :value="selectedName" x-ref="hiddenInput" />

    <div class="relative">
        <input
            type="text"
            id="{{ $id }}"
            x-ref="searchInput"
            x-model="search"
            @focus="maybeFetch()"
            @click="maybeFetch()"
            @input.debounce.300ms="onSearchInput()"
            @keydown.arrow-down.prevent="navigateDown()"
            @keydown.arrow-up.prevent="navigateUp()"
            @keydown.enter.prevent="selectHighlightedOrFreeform()"
            @keydown.tab="close()"
            @blur="commitFreeform()"
            :placeholder="loading ? 'Loading...' : '{{ $placeholder }}'"
            autocomplete="off"
            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent pl-4 pr-10 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden"
        />
        <span class="absolute right-0 top-0 flex h-11 w-10 items-center justify-center text-gray-400 dark:text-gray-500">
            <svg x-show="!loading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </span>
    </div>

    <div
        x-show="open && cities.length > 0"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        x-cloak
    >
        <ul x-ref="listbox" class="max-h-60 overflow-y-auto py-1" role="listbox">
            <template x-for="(city, index) in cities" :key="city.id">
                <li
                    @mousedown.prevent="selectCity(city)"
                    @mouseenter="highlightIndex = index"
                    :class="{
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300': highlightIndex === index,
                        'font-semibold text-brand-600 bg-brand-50/50 dark:bg-brand-500/15 dark:text-brand-300': selectedName === city.name && highlightIndex !== index
                    }"
                    class="cursor-pointer px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/10"
                    role="option"
                    :id="'{{ $id }}-opt-' + index"
                >
                    <span x-text="city.name"></span>
                </li>
            </template>
        </ul>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('citySelectComponent', (elId, initialValue, stateFieldId) => ({
        cities: [],
        search: '',
        open: false,
        selectedName: initialValue || '',
        highlightIndex: 0,
        loading: false,
        stateId: null,
        countryCode: '',
        stateFieldId: stateFieldId,
        elId: elId,
        fetchController: null,
        initialized: false,

        init() {
            if (this.selectedName) {
                this.search = this.selectedName;
            }
        },

        handleStateSelected(ev) {
            if (ev.detail.fieldId !== this.stateFieldId) return;
            const isInitialLoad = !this.initialized;
            this.initialized = true;
            this.stateId = ev.detail.id;
            if (ev.detail.countryCode) {
                this.countryCode = ev.detail.countryCode;
            }

            if (!isInitialLoad) {
                this.selectedName = '';
                this.search = '';
                this.cities = [];
                this.$nextTick(() => {
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
        },

        maybeFetch() {
            this.open = true;
            if (this.stateId && this.cities.length === 0 && !this.loading) {
                this.fetchCities('');
            }
        },

        onSearchInput() {
            this.open = true;
            this.highlightIndex = 0;
            if (this.stateId) {
                this.fetchCities(this.search.trim());
            }
        },

        async fetchCities(query) {
            if (!this.stateId) return;
            if (this.fetchController) this.fetchController.abort();
            this.fetchController = new AbortController();
            this.loading = true;

            try {
                const base = window.__appBaseUrl || '';
                let url = `${base}/api/location/cities/${this.stateId}?q=${encodeURIComponent(query)}&limit=50`;
                if (this.countryCode) {
                    url += `&country_code=${encodeURIComponent(this.countryCode)}`;
                }
                const r = await fetch(url, { signal: this.fetchController.signal });
                const data = await r.json();
                this.cities = data.cities || [];
            } catch (e) {
                if (e.name !== 'AbortError') this.cities = [];
            } finally {
                this.loading = false;
            }
        },

        selectCity(city) {
            this.selectedName = city.name;
            this.search = city.name;
            this.open = false;

            this.$nextTick(() => {
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },

        selectHighlightedOrFreeform() {
            if (this.open && this.cities.length > 0 && this.cities[this.highlightIndex]) {
                this.selectCity(this.cities[this.highlightIndex]);
            } else {
                this.commitFreeform();
            }
        },

        commitFreeform() {
            const val = this.search.trim();
            if (val && val !== this.selectedName) {
                this.selectedName = val;
                this.$nextTick(() => {
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            this.open = false;
        },

        close() {
            this.open = false;
            if (this.selectedName) {
                this.search = this.selectedName;
            }
        },

        navigateDown() {
            if (!this.open) { this.open = true; return; }
            if (this.highlightIndex < this.cities.length - 1) {
                this.highlightIndex++;
                this.scrollToHighlighted();
            }
        },

        navigateUp() {
            if (this.highlightIndex > 0) {
                this.highlightIndex--;
                this.scrollToHighlighted();
            }
        },

        scrollToHighlighted() {
            this.$nextTick(() => {
                const el = document.getElementById(this.elId + '-opt-' + this.highlightIndex);
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        }
    }));
});
</script>
@endonce
