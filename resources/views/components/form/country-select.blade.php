@props([
    'id' => 'country-' . uniqid(),
    'name' => null,
    'label' => null,
    'value' => '',
    'placeholder' => 'Search or select country...',
    'autoDetect' => false,
    'emitNationality' => false,
])

@once
@php $geoCountries = \App\Models\Geo\GeoCountry::orderBy('name')->get(['id','name','iso2','nationality','emoji'])->toArray(); @endphp
<script>
window.__countryData = @json($geoCountries);
window.__countryGeoDetected = null;
window.__countryGeoPromise = null;

window.detectUserCountry = function() {
    if (window.__countryGeoDetected) return Promise.resolve(window.__countryGeoDetected);
    if (window.__countryGeoPromise) return window.__countryGeoPromise;
    window.__countryGeoPromise = fetch('https://ipapi.co/json/', { signal: AbortSignal.timeout(4000) })
        .then(r => r.json())
        .then(data => {
            const code = (data.country_code || '').toUpperCase();
            if (code) {
                const match = window.__countryData.find(c => c.iso2 === code);
                window.__countryGeoDetected = match || null;
            }
            return window.__countryGeoDetected;
        })
        .catch(() => null);
    return window.__countryGeoPromise;
};
</script>
@endonce

<div
    x-data="countrySelectComponent('{{ $id }}', '{{ addslashes($value) }}', {{ $autoDetect ? 'true' : 'false' }}, {{ $emitNationality ? 'true' : 'false' }})"
    x-init="init()"
    class="relative"
    @click.away="close()"
    @keydown.escape.prevent="close()"
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
            @focus="open = true"
            @click="open = true"
            @input="open = true; highlightIndex = 0"
            @keydown.arrow-down.prevent="navigateDown()"
            @keydown.arrow-up.prevent="navigateUp()"
            @keydown.enter.prevent="selectHighlighted()"
            @keydown.tab="close()"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent pl-4 pr-10 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden"
        />
        <button type="button" @click="open = !open; open && $nextTick(() => $refs.searchInput.focus())" class="absolute right-0 top-0 flex h-11 w-10 items-center justify-center text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" tabindex="-1">
            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
    </div>

    <div
        x-show="open"
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
            <template x-for="(country, index) in filtered" :key="country.iso2">
                <li
                    @mousedown.prevent="selectCountry(country)"
                    @mouseenter="highlightIndex = index"
                    :class="{
                        'bg-brand-50 text-brand-700 dark:bg-brand-500/20 dark:text-brand-300': highlightIndex === index,
                        'font-semibold text-brand-600 bg-brand-50/50 dark:bg-brand-500/15 dark:text-brand-300': selectedName === country.name && highlightIndex !== index
                    }"
                    class="cursor-pointer px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/10 flex items-center gap-2"
                    role="option"
                    :id="'{{ $id }}-opt-' + index"
                >
                    <span class="shrink-0 text-base" x-text="country.emoji"></span>
                    <span x-text="country.name"></span>
                    <span x-show="country.nationality" class="ml-auto text-xs text-gray-400 dark:text-gray-500" x-text="country.nationality"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">No countries found</li>
        </ul>
    </div>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('countrySelectComponent', (elId, initialValue, autoDetect, emitNationality) => ({
        countries: window.__countryData || [],
        search: '',
        open: false,
        selectedName: initialValue || '',
        selectedCode: '',
        highlightIndex: 0,
        emitNationality: emitNationality,
        elId: elId,

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q || q === this.selectedName.toLowerCase()) return this.countries;
            return this.countries.filter(c =>
                c.name.toLowerCase().includes(q) ||
                c.iso2.toLowerCase() === q ||
                (c.nationality && c.nationality.toLowerCase().includes(q))
            );
        },

        init() {
            if (this.selectedName) {
                this.search = this.selectedName;
                const match = this.countries.find(c => c.name.toLowerCase() === this.selectedName.toLowerCase());
                if (match) {
                    this.selectedCode = match.iso2;
                    this.$nextTick(() => {
                        this.$dispatch('country-selected', {
                            name: match.name,
                            code: match.iso2,
                            nationality: match.nationality || '',
                            fieldId: this.elId
                        });
                    });
                }
            } else if (autoDetect) {
                window.detectUserCountry().then(geo => {
                    if (geo && !this.selectedName) {
                        this.selectCountry(geo, true);
                    }
                });
            }
        },

        selectCountry(country, silent) {
            this.selectedName = country.name;
            this.selectedCode = country.iso2;
            this.search = country.name;
            this.open = false;
            if (!silent) {
                this.$nextTick(() => {
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
            if (this.emitNationality && country.nationality) {
                this.$dispatch('nationality-detected', { nationality: country.nationality, source: this.elId });
            }
            this.$dispatch('country-selected', {
                name: country.name,
                code: country.iso2,
                nationality: country.nationality || '',
                fieldId: this.elId
            });
        },

        close() {
            this.open = false;
            if (this.selectedName) {
                this.search = this.selectedName;
            } else {
                this.search = '';
            }
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
                this.selectCountry(this.filtered[this.highlightIndex]);
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
