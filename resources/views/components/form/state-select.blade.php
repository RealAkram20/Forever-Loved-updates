@props([
    'id' => 'state-' . uniqid(),
    'name' => null,
    'label' => null,
    'value' => '',
    'placeholder' => 'Select or search...',
    'countryFieldId' => null,
])

<div
    x-data="stateSelectComponent('{{ $id }}', '{{ addslashes($value) }}', '{{ $countryFieldId }}')"
    x-init="init()"
    class="relative"
    @click.away="close()"
    @keydown.escape.prevent="close()"
    @country-selected.window="handleCountrySelected($event)"
>
    <label :for="'{{ $id }}'" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
        <span x-text="dynamicLabel">{{ $label ?: 'State / Region' }}</span>
    </label>

    <input type="hidden" name="{{ $name }}" :value="selectedName" x-ref="hiddenInput" />

    <div class="relative">
        <input
            type="text"
            id="{{ $id }}"
            x-ref="searchInput"
            x-model="search"
            @focus="open = true"
            @click="open = true"
            @input="open = true; highlightIndex = 0"
            @keydown.arrow-down.prevent="navigateDown()"
            @keydown.arrow-up.prevent="navigateUp()"
            @keydown.enter.prevent="selectHighlighted()"
            @keydown.tab="close()"
            :placeholder="loading ? 'Loading...' : (states.length ? placeholder : 'Select a country first')"
            :disabled="!countryCode && !selectedName"
            autocomplete="off"
            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent pl-4 pr-10 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden disabled:bg-gray-50 disabled:text-gray-400 dark:disabled:bg-gray-800/50 dark:disabled:text-gray-500"
        />
        <button type="button" @click="open = !open; open && $nextTick(() => $refs.searchInput.focus())" class="absolute right-0 top-0 flex h-11 w-10 items-center justify-center text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" tabindex="-1">
            <svg x-show="!loading" class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </button>
    </div>

    <div
        x-show="open && states.length > 0"
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
            <template x-for="(state, index) in filtered" :key="state.id">
                <li
                    @mousedown.prevent="selectState(state)"
                    @mouseenter="highlightIndex = index"
                    :class="{
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300': highlightIndex === index,
                        'font-semibold text-brand-600 bg-brand-50/50 dark:bg-brand-500/15 dark:text-brand-300': selectedName === state.name && highlightIndex !== index
                    }"
                    class="cursor-pointer px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/10"
                    role="option"
                    :id="'{{ $id }}-opt-' + index"
                >
                    <span x-text="state.name"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0 && search.trim()" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">No results found</li>
        </ul>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('stateSelectComponent', (elId, initialValue, countryFieldId) => ({
        states: [],
        search: '',
        open: false,
        selectedName: initialValue || '',
        selectedId: null,
        highlightIndex: 0,
        loading: false,
        countryCode: '',
        countryFieldId: countryFieldId,
        dynamicLabel: 'State / Region',
        placeholder: '{{ $placeholder }}',
        elId: elId,

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q || q === this.selectedName.toLowerCase()) return this.states;
            return this.states.filter(s => s.name.toLowerCase().includes(q));
        },

        init() {
            if (this.selectedName) {
                this.search = this.selectedName;
            }
        },

        handleCountrySelected(ev) {
            if (ev.detail.fieldId !== this.countryFieldId) return;
            const newCode = ev.detail.code;
            const isInitialLoad = !this.countryCode;
            if (newCode === this.countryCode && this.states.length) return;

            this.countryCode = newCode;

            if (!isInitialLoad || !this.selectedName) {
                this.selectedName = '';
                this.selectedId = null;
                this.search = '';
            }

            this.states = [];
            this.fetchStates(newCode, isInitialLoad);

            if (!isInitialLoad) {
                this.$nextTick(() => {
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
                this.$dispatch('state-selected', { id: null, name: '', countryCode: newCode, fieldId: this.elId });
            }
        },

        async fetchStates(code, resolveExisting) {
            this.loading = true;
            try {
                const base = window.__appBaseUrl || '';
                const r = await fetch(`${base}/api/location/states/${code}`);
                const data = await r.json();
                this.states = data.states || [];
                this.dynamicLabel = data.type_label || 'State / Region';

                if (resolveExisting && this.selectedName) {
                    const match = this.states.find(s => s.name.toLowerCase() === this.selectedName.toLowerCase());
                    if (match) {
                        this.selectedId = match.id;
                        this.$nextTick(() => {
                            this.$dispatch('state-selected', {
                                id: match.id,
                                name: match.name,
                                countryCode: this.countryCode,
                                fieldId: this.elId
                            });
                        });
                    }
                }
            } catch {
                this.states = [];
            } finally {
                this.loading = false;
            }
        },

        selectState(state) {
            this.selectedName = state.name;
            this.selectedId = state.id;
            this.search = state.name;
            this.open = false;

            this.$nextTick(() => {
                this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            });

            this.$dispatch('state-selected', {
                id: state.id,
                name: state.name,
                countryCode: this.countryCode,
                fieldId: this.elId
            });
        },

        close() {
            this.open = false;
            this.search = this.selectedName || '';
        },

        navigateDown() {
            if (!this.open) { this.open = true; return; }
            if (this.highlightIndex < this.filtered.length - 1) {
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

        selectHighlighted() {
            if (this.open && this.filtered.length > 0 && this.filtered[this.highlightIndex]) {
                this.selectState(this.filtered[this.highlightIndex]);
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
