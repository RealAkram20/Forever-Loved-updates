@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true])

@section('content')
<div class="min-h-screen bg-white dark:bg-gray-900">
    <x-home-header />

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white/90 mb-6">Find Memorial</h1>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,3fr)] w-full"
            x-data="{
                viewMode: 'grid',
                filtersOpen: false,
                search: '',
                gender: '',
                designation: '',
                ageMin: '',
                ageMax: '',
                birthYearFrom: '',
                birthYearTo: '',
                deathYearFrom: '',
                deathYearTo: '',
                items: [],
                loading: false,
                page: 1,
                lastPage: 1,
                total: 0,
                perPage: 12,
                fetch() {
                    this.loading = true;
                    const params = new URLSearchParams({
                        page: this.page,
                        per_page: this.perPage,
                        q: this.search,
                        gender: this.gender,
                        designation: this.designation,
                        age_min: this.ageMin === '' || this.ageMin === null ? 0 : this.ageMin,
                        age_max: this.ageMax === '' || this.ageMax === null ? 120 : this.ageMax,
                        birth_year_from: this.birthYearFrom || 0,
                        birth_year_to: this.birthYearTo || 0,
                        death_year_from: this.deathYearFrom || 0,
                        death_year_to: this.deathYearTo || 0
                    });
                    fetch(`{{ route('memorial.directory') }}?${params}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => {
                        if (!r.ok) throw new Error('Request failed');
                        return r.json();
                    })
                    .then(data => {
                        this.items = data.data || [];
                        this.lastPage = data.meta?.last_page || 1;
                        this.total = data.meta?.total || 0;
                        this.loading = false;
                    })
                    .catch(() => {
                        this.items = [];
                        this.loading = false;
                    });
                },
                applyFilters() {
                    this.page = 1;
                    const a = this.ageMin === '' || this.ageMin === null ? 0 : Number(this.ageMin);
                    const b = this.ageMax === '' || this.ageMax === null ? 120 : Number(this.ageMax);
                    this.ageMin = Math.min(a, b);
                    this.ageMax = Math.max(a, b);
                    this.fetch();
                },
                init() {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('designation')) this.designation = urlParams.get('designation');
                    if (urlParams.get('q')) this.search = urlParams.get('q');
                    if (urlParams.get('gender')) this.gender = urlParams.get('gender');
                    this.fetch();
                    if (window.innerWidth >= 1024) this.filtersOpen = true;
                    window.addEventListener('resize', () => { if (window.innerWidth >= 1024) this.filtersOpen = true; });
                }
            }"
            x-init="init()">

            {{-- Left filters (hidden on mobile, toggle to show) --}}
            <aside x-show="filtersOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="w-full min-w-0 space-y-6 lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white/90 mb-4">Filters</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                            <input type="text" x-model="search" @keyup.enter="applyFilters()"
                                placeholder="Name, profession..."
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Gender</label>
                            <select x-model="gender"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90">
                                <option value="">All</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Designation</label>
                            <select x-model="designation"
                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90">
                                <option value="">All categories</option>
                                <option value="COVID-19 victim">COVID-19 Victims</option>
                                <option value="War veteran">War Veterans</option>
                                <option value="First responder">First Responders</option>
                                <option value="Substance abuse victim">Substance Abuse Victims</option>
                                <option value="Cancer victim">Cancer Victims</option>
                                <option value="Victim of an accident">Accident Victims</option>
                                <option value="Crime victim">Crime Victims</option>
                                <option value="Miscarriage, stillborn and infant loss">Infant Loss</option>
                                <option value="Child loss">Child Loss</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Age at death</label>
                            <div class="flex gap-2">
                                <input type="number" x-model="ageMin" placeholder="Min" min="0" max="120"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <input type="number" x-model="ageMax" placeholder="Max" min="0" max="120"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                            </div>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Leave empty for no limit</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Birth year</label>
                            <div class="flex gap-2">
                                <input type="number" x-model="birthYearFrom" placeholder="From" min="1800" max="2100"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90" />
                                <input type="number" x-model="birthYearTo" placeholder="To" min="1800" max="2100"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Death year</label>
                            <div class="flex gap-2">
                                <input type="number" x-model="deathYearFrom" placeholder="From" min="1800" max="2100"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90" />
                                <input type="number" x-model="deathYearTo" placeholder="To" min="1800" max="2100"
                                    class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 py-2 text-sm text-gray-800 dark:text-white/90" />
                            </div>
                        </div>

                        <button @click="applyFilters(); if (window.innerWidth < 1024) filtersOpen = false"
                            class="w-full rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            Apply filters
                        </button>
                    </div>
                </div>
            </aside>

            {{-- Results --}}
            <div class="min-w-0 w-full">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <button @click="filtersOpen = !filtersOpen" class="lg:hidden inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span x-text="filtersOpen ? 'Hide filters' : 'Filters'"></span>
                        </button>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="total + ' memorials found'"></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400' : 'text-gray-400 hover:text-gray-600'"
                            class="p-2 rounded-lg transition-colors" title="Grid view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                        <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-brand-100 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400' : 'text-gray-400 hover:text-gray-600'"
                            class="p-2 rounded-lg transition-colors" title="List view">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </button>
                    </div>
                </div>

                <div x-show="loading" class="flex justify-center py-12">
                    <svg class="h-8 w-8 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </div>

                <template x-if="!loading && items.length === 0">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-12 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No memorials found. Try adjusting your filters.</p>
                    </div>
                </template>

                {{-- Grid view (Nike/product card style) --}}
                <div x-show="!loading && items.length > 0 && viewMode === 'grid'"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-5 w-full min-w-0">
                    <template x-for="item in items" :key="item.slug">
                        <a :href="item.url" class="group block min-w-0 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-lg hover:border-gray-200 dark:hover:border-gray-600 transition-all duration-200">
                            {{-- Image area (clean product-photo style) --}}
                            <div class="aspect-[4/5] bg-gray-50 dark:bg-gray-700/50 overflow-hidden flex items-center justify-center">
                                <template x-if="item.photo">
                                    <img :src="item.photo" alt="" class="h-full w-full object-cover object-top group-hover:scale-[1.02] transition-transform duration-300" onerror="this.onerror=null; this.src='{{ asset('images/forever-loved-placeholder.jpeg') }}'; this.alt=''; this.classList.add('opacity-25','object-contain')" />
                                </template>
                                <template x-if="!item.photo">
                                    <div class="relative flex w-full h-full items-center justify-center bg-gray-50 dark:bg-gray-700/30">
                                        <img src="{{ asset('images/forever-loved-placeholder.jpeg') }}" alt="" class="absolute inset-0 h-full w-full object-contain opacity-25 dark:opacity-20 p-8" />
                                        <div class="relative flex h-16 w-16 items-center justify-center rounded-full bg-white/80 dark:bg-gray-600/80 text-2xl font-semibold text-gray-500 dark:text-gray-400 shadow-sm" x-text="(item.name || '?').charAt(0).toUpperCase()"></div>
                                    </div>
                                </template>
                            </div>
                            {{-- Details (hierarchy: years → name → profession) --}}
                            <div class="p-4 min-w-0">
                                <p class="text-sm font-semibold text-brand-600 dark:text-brand-400 mb-0.5" x-text="item.years || '—'"></p>
                                <p class="font-semibold text-gray-900 dark:text-white/90 truncate text-base" x-text="item.name || 'Unknown'"></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate mt-0.5" x-text="item.profession || '—'"></p>
                            </div>
                        </a>
                    </template>
                </div>

                {{-- List view (E-commerce product list style) --}}
                <div x-show="!loading && items.length > 0 && viewMode === 'list'"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="space-y-4 w-full">
                    <template x-for="item in items" :key="item.slug">
                        <a :href="item.url" class="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-4 md:gap-5 items-center rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-4 hover:shadow-lg hover:border-gray-200 dark:hover:border-gray-600 transition-all duration-200">
                            {{-- Left: Image area (square product thumb) --}}
                            <div class="relative h-24 w-24 md:h-28 md:w-28 shrink-0 rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-700/50 flex items-center justify-center">
                                <template x-if="item.photo">
                                    <img :src="item.photo" alt="" class="h-full w-full object-cover object-center" onerror="this.onerror=null; this.src='{{ asset('images/forever-loved-placeholder.jpeg') }}'; this.alt=''; this.classList.add('opacity-25','object-contain')" />
                                </template>
                                <template x-if="!item.photo">
                                    <div class="relative flex w-full h-full items-center justify-center bg-gray-50 dark:bg-gray-700/30 overflow-hidden">
                                        <img src="{{ asset('images/forever-loved-placeholder.jpeg') }}" alt="" class="absolute inset-0 h-full w-full object-contain opacity-25 dark:opacity-20 p-4" />
                                        <span class="relative text-xl font-semibold text-gray-500 dark:text-gray-400" x-text="(item.name || '?').charAt(0).toUpperCase()"></span>
                                    </div>
                                </template>
                            </div>
                            {{-- Center: Details (min-w-0 prevents overflow into button) --}}
                            <div class="min-w-0 overflow-hidden flex flex-col justify-center gap-0.5">
                                <p class="font-semibold text-gray-900 dark:text-white/90 text-base truncate" x-text="item.name || 'Unknown'"></p>
                                <p class="text-sm font-medium text-brand-600 dark:text-brand-400 truncate" x-text="item.years || '—'"></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate" x-text="item.profession || '—'"></p>
                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="item.tributes_count > 0 || item.visitor_count > 0">
                                    <span x-show="item.tributes_count > 0" x-text="item.tributes_count + ' tributes'"></span>
                                    <span x-show="item.visitor_count > 0" x-text="item.visitor_count + ' visitors'"></span>
                                </div>
                            </div>
                            {{-- Right: Action button (shrink-0 keeps it from being squeezed) --}}
                            <div class="shrink-0">
                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white">
                                    View
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </div>
                        </a>
                    </template>
                </div>

                {{-- Pagination --}}
                <div x-show="!loading && lastPage > 1" class="mt-6 flex justify-center gap-2">
                    <button @click="page = Math.max(1, page - 1); fetch()" :disabled="page <= 1"
                        class="rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800">
                        Previous
                    </button>
                    <span class="flex items-center px-4 py-2 text-sm text-gray-500 dark:text-gray-400" x-text="'Page ' + page + ' of ' + lastPage"></span>
                    <button @click="page = Math.min(lastPage, page + 1); fetch()" :disabled="page >= lastPage"
                        class="rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50 dark:hover:bg-gray-800">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>
@endsection
