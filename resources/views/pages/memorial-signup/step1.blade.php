@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white dark:bg-gray-900 px-6 pt-6 pb-[max(8rem,env(safe-area-inset-bottom,0px)+5rem)] sm:px-0 sm:pt-10 sm:pb-[max(8rem,env(safe-area-inset-bottom,0px)+3rem)] lg:pb-40" x-data="step1Persist({{ json_encode($data) }})">
    <div class="relative flex min-h-screen w-full flex-col justify-start py-8 sm:py-12">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-0 pt-4 pb-12 sm:px-6 sm:pt-10 sm:pb-16 lg:px-12 lg:pb-20">
                <x-memorial-signup.step-tabs :currentStep="1" />
                <a href="{{ route('home') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to home
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 1 of 3</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Deceased details</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white">
                        This memorial is dedicated to<template x-if="hasName"><span> <span class="font-bold" x-text="fullName"></span></span></template>
                    </h1>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Share information about <span :class="hasName && 'font-bold text-base'" x-text="displayName"></span>. You can update this later.</p>

                    @if (session('error'))
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-400">{{ session('error') }}</div>
                    @endif
                    {{-- Field errors render inline below their inputs; the banner covers everything else --}}
                    @php $inlineErrorFields = ['first_name', 'last_name', 'date_of_birth', 'date_of_passing']; @endphp
                    @if ($errors->any() && collect($errors->keys())->diff($inlineErrorFields)->isNotEmpty())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-400">{{ $errors->first(collect($errors->keys())->diff($inlineErrorFields)->first()) }}</div>
                    @endif

                    <form id="step1-form" method="POST" action="{{ route('memorial.create.storeStep1') }}" class="space-y-6" @input="saveToStorage()" @change="saveToStorage()">
                        @csrf
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="first_name">First name</label>
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $data['first_name'] ?? '') }}" required
                                        @input="firstName = $event.target.value"
                                        @error('first_name') aria-invalid="true" aria-describedby="first_name-error" @enderror
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('first_name') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                    @error('first_name')
                                        <p id="first_name-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="last_name">Last name</label>
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $data['last_name'] ?? '') }}" required
                                        @input="lastName = $event.target.value"
                                        @error('last_name') aria-invalid="true" aria-describedby="last_name-error" @enderror
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border @error('last_name') border-red-500 dark:border-red-500 @else border-gray-300 dark:border-gray-600 @enderror bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                    @error('last_name')
                                        <p id="last_name-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="middle_name">Middle name (optional)</label>
                                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $data['middle_name'] ?? '') }}"
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                                <div class="flex flex-wrap items-center gap-6">
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 dark:text-gray-300 select-none">
                                        <input type="radio" name="gender" value="male" {{ old('gender', $data['gender'] ?? '') === 'male' ? 'checked' : '' }}
                                            class="border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500" />
                                        <span class="ml-2">Male</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 dark:text-gray-300 select-none">
                                        <input type="radio" name="gender" value="female" {{ old('gender', $data['gender'] ?? '') === 'female' ? 'checked' : '' }}
                                            class="border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500" />
                                        <span class="ml-2">Female</span>
                                    </label>
                                </div>
                            </div>

                            <x-form.relationship-select
                                :value="old('relationship', $data['relationship'] ?? '')"
                                :other="old('relationship_other', $data['relationship_other'] ?? '')" />
                        </div>

                        {{-- Do it later #1: Dates & location --}}
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    When and where <span :class="hasName && 'font-bold text-base'" x-text="displayName"></span> was born and passed away:
                                </p>
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300 select-none">
                                    <div class="relative">
                                        <input type="checkbox" class="sr-only" x-model="doDatesLater" @change="saveToStorage()" />
                                        <div class="block h-6 w-11 rounded-full" :class="doDatesLater ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-600'"></div>
                                        <div :class="doDatesLater ? 'translate-x-full' : 'translate-x-0'" class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"></div>
                                    </div>
                                    <span>Do this later</span>
                                </label>
                            </div>
                            <div class="space-y-5" x-show="!doDatesLater" x-collapse>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Born</label>
                                    <x-form.date-picker id="date_of_birth" name="date_of_birth" placeholder="Select date"
                                        :defaultDate="old('date_of_birth', $data['date_of_birth'] ?? null)" />
                                    @error('date_of_birth')
                                        <p id="date_of_birth-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                    <div>
                                        <x-form.country-select id="step1_birth_country" name="birth_country" label="Country"
                                            :value="old('birth_country', $data['birth_country'] ?? '')" :autoDetect="true" :emitNationality="true" />
                                    </div>
                                    <div>
                                        <x-form.state-select id="step1_birth_state" name="birth_state"
                                            :value="old('birth_state', $data['birth_state'] ?? '')" countryFieldId="step1_birth_country" />
                                    </div>
                                    <div>
                                        <x-form.city-select id="step1_birth_city" name="birth_city"
                                            :value="old('birth_city', $data['birth_city'] ?? '')" stateFieldId="step1_birth_state" />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Passed away</label>
                                    <x-form.date-picker id="date_of_passing" name="date_of_passing" placeholder="Select date"
                                        :defaultDate="old('date_of_passing', $data['date_of_passing'] ?? null)" />
                                    @error('date_of_passing')
                                        <p id="date_of_passing-error" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                    <div>
                                        <x-form.country-select id="step1_death_country" name="death_country" label="Country"
                                            :value="old('death_country', $data['death_country'] ?? '')" />
                                    </div>
                                    <div>
                                        <x-form.state-select id="step1_death_state" name="death_state"
                                            :value="old('death_state', $data['death_state'] ?? '')" countryFieldId="step1_death_country" />
                                    </div>
                                    <div>
                                        <x-form.city-select id="step1_death_city" name="death_city"
                                            :value="old('death_city', $data['death_city'] ?? '')" stateFieldId="step1_death_state" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Do it later #2: Profile enrichment --}}
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Tell us more about <span :class="hasName && 'font-bold text-base'" x-text="displayName"></span></p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">This helps us generate a richer memorial profile.</p>
                                </div>
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300 select-none">
                                    <div class="relative">
                                        <input type="checkbox" class="sr-only" x-model="doProfileLater" @change="saveToStorage()" />
                                        <div class="block h-6 w-11 rounded-full" :class="doProfileLater ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-600'"></div>
                                        <div :class="doProfileLater ? 'translate-x-full' : 'translate-x-0'" class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"></div>
                                    </div>
                                    <span>Do this later</span>
                                </label>
                            </div>
                            <div class="space-y-5" x-show="!doProfileLater" x-collapse>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="short_description">Short description of <span :class="hasName && 'font-bold text-base'" x-text="displayName"></span></label>
                                    <input type="text" id="short_description" name="short_description" value="{{ old('short_description', $data['short_description'] ?? '') }}"
                                        placeholder="e.g. Loving mother, teacher, community leader"
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div x-data="{ nationalityVal: '{{ old('nationality', $data['nationality'] ?? '') }}' }" @nationality-detected.window="if ($event.detail.source === 'step1_birth_country') nationalityVal = $event.detail.nationality">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="nationality">Nationality</label>
                                        <input type="text" id="nationality" name="nationality" x-model="nationalityVal"
                                            placeholder="Auto-filled from birth country"
                                            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="primary_profession">Primary profession</label>
                                        <input type="text" id="primary_profession" name="primary_profession" value="{{ old('primary_profession', $data['primary_profession'] ?? '') }}"
                                            placeholder="e.g. Teacher"
                                            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="major_achievements">Major achievements of <span :class="hasName && 'font-bold text-base'" x-text="displayName"></span></label>
                                    <textarea id="major_achievements" name="major_achievements" rows="3" placeholder="e.g. Built the family business and mentored many young people..."
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden">{{ old('major_achievements', $data['major_achievements'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-md btn-block w-full mt-2">
                            Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const STEP1_STORAGE_KEY = 'memorial_signup_step1';

function step1Persist(serverData) {
    return {
        serverData: serverData || {},
        doProfileLater: false,
        doDatesLater: false,
        saveTimeout: null,
        firstName: '',
        lastName: '',

        // The page speaks about the deceased by name as soon as one is typed, and
        // falls back to a gentle phrase before that.
        get fullName() {
            return [this.firstName.trim(), this.lastName.trim()].filter(Boolean).join(' ');
        },
        get hasName() {
            return this.firstName.trim() !== '';
        },
        get displayName() {
            return this.firstName.trim() || 'your loved one';
        },

        readNames() {
            const form = document.getElementById('step1-form');
            if (!form) return;
            this.firstName = form.querySelector('[name="first_name"]')?.value ?? '';
            this.lastName = form.querySelector('[name="last_name"]')?.value ?? '';
        },

        init() {
            this.readNames();

            // Storage is cleared on submit, so anything still saved was typed after the
            // last submit and is newer than what the server rendered — restore it even
            // when the session already holds step 1 data.
            const saved = this.getSaved();
            if (saved) {
                // Wait for nested components (the relationship select) to bind first.
                this.$nextTick(() => this.restoreForm(saved));
            }

            const form = document.getElementById('step1-form');
            if (form) {
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(STEP1_STORAGE_KEY); } catch (e) {}
                    if (this.doDatesLater) {
                        const dateFields = ['date_of_birth', 'date_of_passing', 'birth_city', 'birth_state', 'birth_country', 'death_city', 'death_state', 'death_country'];
                        dateFields.forEach(name => {
                            const el = form.querySelector(`[name="${name}"]`);
                            if (el) { el.disabled = true; }
                        });
                    }
                    if (this.doProfileLater) {
                        const profileFields = ['short_description', 'nationality', 'primary_profession', 'major_achievements'];
                        profileFields.forEach(name => {
                            const el = form.querySelector(`[name="${name}"]`);
                            if (el) { el.disabled = true; }
                        });
                    }
                });
            }
        },

        getSaved() {
            try {
                const s = localStorage.getItem(STEP1_STORAGE_KEY);
                return s ? JSON.parse(s) : null;
            } catch (e) { return null; }
        },

        saveToStorage() {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => {
                const form = document.getElementById('step1-form');
                if (!form) return;
                const fd = new FormData(form);
                const obj = { doDatesLater: this.doDatesLater, doProfileLater: this.doProfileLater };
                for (const [k, v] of fd) {
                    if (v instanceof File) continue;
                    obj[k] = v;
                }
                for (const el of form.querySelectorAll('input[type="checkbox"]:not(:checked)')) {
                    if (el.name && !(el.name in obj)) obj[el.name] = '';
                }
                try {
                    localStorage.setItem(STEP1_STORAGE_KEY, JSON.stringify(obj));
                } catch (e) {}
            }, 300);
        },

        restoreForm(saved) {
            const form = document.getElementById('step1-form');
            if (!form) return;
            const locationFields = new Set([
                'birth_country', 'birth_state', 'birth_city',
                'death_country', 'death_state', 'death_city',
            ]);
            for (const [name, value] of Object.entries(saved)) {
                if (name === 'doDatesLater' || name === 'doProfileLater') continue;
                if (locationFields.has(name)) continue;
                const el = form.querySelector(`[name="${name}"]`);
                if (!el) continue;
                if (el.type === 'checkbox') {
                    el.checked = value === '1' || value === 'on' || value === true || value === 'true';
                } else if (el.type === 'radio') {
                    el.checked = (el.value === value);
                } else {
                    el.value = value || '';
                }
                // Alpine-bound fields (the relationship select) only track values that
                // arrive through an event, not ones assigned straight to .value.
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.doDatesLater = !!saved.doDatesLater;
            this.doProfileLater = !!saved.doProfileLater;
            this.readNames();
            setTimeout(() => this.restoreLocationFields(saved), 0);
        },

        restoreLocationFields(saved) {
            if (typeof Alpine === 'undefined') return;
            const groups = [
                { prefix: 'birth', countryId: 'step1_birth_country', stateId: 'step1_birth_state', cityId: 'step1_birth_city' },
                { prefix: 'death', countryId: 'step1_death_country', stateId: 'step1_death_state', cityId: 'step1_death_city' },
            ];
            for (const g of groups) {
                const countryVal = saved[g.prefix + '_country'] || '';
                const stateVal = saved[g.prefix + '_state'] || '';
                const cityVal = saved[g.prefix + '_city'] || '';
                if (!countryVal) continue;
                this._restoreLocationGroup(g, countryVal, stateVal, cityVal);
            }
        },

        _restoreLocationGroup(g, countryVal, stateVal, cityVal) {
            const countryRoot = document.getElementById(g.countryId)?.closest('[x-data]');
            if (!countryRoot) return;
            const countryData = Alpine.$data(countryRoot);

            const match = (window.__countryData || []).find(
                c => c.name.toLowerCase() === countryVal.toLowerCase()
            );
            if (match) {
                countryData.selectedName = match.name;
                countryData.selectedCode = match.iso2;
                countryData.search = match.name;
            } else {
                countryData.selectedName = countryVal;
                countryData.search = countryVal;
                return;
            }

            const stateRoot = document.getElementById(g.stateId)?.closest('[x-data]');
            if (stateRoot) {
                const sd = Alpine.$data(stateRoot);
                sd.countryCode = match.iso2;
                if (stateVal) {
                    sd.selectedName = stateVal;
                    sd.search = stateVal;
                }
                sd.fetchStates(match.iso2, true);
            }

            if (cityVal) {
                const cityRoot = document.getElementById(g.cityId)?.closest('[x-data]');
                if (cityRoot) {
                    const cd = Alpine.$data(cityRoot);
                    cd.selectedName = cityVal;
                    cd.search = cityVal;
                    cd.countryCode = match.iso2;
                }
            }
        }
    };
}
</script>
@endpush
@endsection
