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
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white">This memorial is dedicated to</h1>
                    <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Share information about your loved one. You can update this later.</p>

                    @if (session('error'))
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-400">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-400">{{ $errors->first() }}</div>
                    @endif

                    <form id="step1-form" method="POST" action="{{ route('memorial.create.storeStep1') }}" class="space-y-6" @input="saveToStorage()" @change="saveToStorage()">
                        @csrf
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="first_name">First name</label>
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $data['first_name'] ?? '') }}" required
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="middle_name">Middle name</label>
                                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $data['middle_name'] ?? '') }}"
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="last_name">Last name</label>
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $data['last_name'] ?? '') }}" required
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

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="relationship">Relationship</label>
                                @php $relVal = old('relationship', $data['relationship'] ?? ''); @endphp
                                <select id="relationship" name="relationship" class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 focus:ring-3 focus:outline-hidden">
                                    <option value="">— Select relationship —</option>
                                    <option value="Father" {{ $relVal === 'Father' ? 'selected' : '' }}>Father</option>
                                    <option value="Mother" {{ $relVal === 'Mother' ? 'selected' : '' }}>Mother</option>
                                    <option value="Spouse" {{ $relVal === 'Spouse' ? 'selected' : '' }}>Spouse</option>
                                    <option value="Husband" {{ $relVal === 'Husband' ? 'selected' : '' }}>Husband</option>
                                    <option value="Wife" {{ $relVal === 'Wife' ? 'selected' : '' }}>Wife</option>
                                    <option value="Child" {{ $relVal === 'Child' ? 'selected' : '' }}>Child</option>
                                    <option value="Son" {{ $relVal === 'Son' ? 'selected' : '' }}>Son</option>
                                    <option value="Daughter" {{ $relVal === 'Daughter' ? 'selected' : '' }}>Daughter</option>
                                    <option value="Brother" {{ $relVal === 'Brother' ? 'selected' : '' }}>Brother</option>
                                    <option value="Sister" {{ $relVal === 'Sister' ? 'selected' : '' }}>Sister</option>
                                    <option value="Grandparent" {{ $relVal === 'Grandparent' ? 'selected' : '' }}>Grandparent</option>
                                    <option value="Grandfather" {{ $relVal === 'Grandfather' ? 'selected' : '' }}>Grandfather</option>
                                    <option value="Grandmother" {{ $relVal === 'Grandmother' ? 'selected' : '' }}>Grandmother</option>
                                    <option value="Uncle" {{ $relVal === 'Uncle' ? 'selected' : '' }}>Uncle</option>
                                    <option value="Aunt" {{ $relVal === 'Aunt' ? 'selected' : '' }}>Aunt</option>
                                    <option value="Cousin" {{ $relVal === 'Cousin' ? 'selected' : '' }}>Cousin</option>
                                    <option value="Friend" {{ $relVal === 'Friend' ? 'selected' : '' }}>Friend</option>
                                    <option value="Other" {{ $relVal === 'Other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </div>

                        {{-- Do it later #1: Dates, location & designation --}}
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Dates, location & designation:</p>
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
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="cause_of_death">Designation</label>
                                    @php $causeVal = old('cause_of_death', $data['cause_of_death'] ?? ''); @endphp
                                    <select id="cause_of_death" name="cause_of_death" class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 focus:ring-3 focus:outline-hidden">
                                        <option value="">— Select designation —</option>
                                        <option value="COVID-19 victim" {{ $causeVal === 'COVID-19 victim' ? 'selected' : '' }}>COVID-19 victim</option>
                                        <option value="War veteran" {{ $causeVal === 'War veteran' ? 'selected' : '' }}>War veteran</option>
                                        <option value="First responder" {{ $causeVal === 'First responder' ? 'selected' : '' }}>First responder</option>
                                        <option value="Substance abuse victim" {{ $causeVal === 'Substance abuse victim' ? 'selected' : '' }}>Substance abuse victim</option>
                                        <option value="Cancer victim" {{ $causeVal === 'Cancer victim' ? 'selected' : '' }}>Cancer victim</option>
                                        <option value="Victim of an accident" {{ $causeVal === 'Victim of an accident' ? 'selected' : '' }}>Victim of an accident</option>
                                        <option value="Crime victim" {{ $causeVal === 'Crime victim' ? 'selected' : '' }}>Crime victim</option>
                                        <option value="Miscarriage, stillborn and infant loss" {{ $causeVal === 'Miscarriage, stillborn and infant loss' ? 'selected' : '' }}>Miscarriage, stillborn and infant loss</option>
                                        <option value="Child loss" {{ $causeVal === 'Child loss' ? 'selected' : '' }}>Child loss</option>
                                        <option value="Other" {{ $causeVal === 'Other' ? 'selected' : '' }}>Other</option>
                                        <option value="No designation" {{ $causeVal === 'No designation' ? 'selected' : '' }}>No designation</option>
                                    </select>
                                    <label class="mt-2 flex cursor-pointer items-center gap-2">
                                        <input type="hidden" name="cause_of_death_private" value="0" />
                                        <input type="checkbox" name="cause_of_death_private" value="1" {{ old('cause_of_death_private', $data['cause_of_death_private'] ?? false) ? 'checked' : '' }}
                                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Keep designation private</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Do it later #2: Profile enrichment --}}
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Help us generate a richer memorial profile:</p>
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
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="short_description">Short description</label>
                                    <input type="text" id="short_description" name="short_description" value="{{ old('short_description', $data['short_description'] ?? '') }}"
                                        placeholder="e.g. American businessman, co-inventor, investor"
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
                                            placeholder="e.g. Entrepreneur"
                                            class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden" />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="major_achievements">Major achievements</label>
                                    <textarea id="major_achievements" name="major_achievements" rows="3" placeholder="e.g. Co-founded Apple Inc. with Steve Wozniak in 1976..."
                                        class="dark:bg-gray-900/80 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-3 focus:outline-hidden">{{ old('major_achievements', $data['major_achievements'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="mt-2 w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
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

        init() {
            const saved = this.getSaved();
            const hasServerData = this.serverData && this.serverData.first_name;
            if (saved && !hasServerData) {
                this.restoreForm(saved);
            }
            if (saved && (saved.doDatesLater !== undefined)) this.doDatesLater = !!saved.doDatesLater;
            if (saved && (saved.doProfileLater !== undefined)) this.doProfileLater = !!saved.doProfileLater;

            const form = document.getElementById('step1-form');
            if (form) {
                form.addEventListener('submit', () => {
                    try { localStorage.removeItem(STEP1_STORAGE_KEY); } catch (e) {}
                    if (this.doDatesLater) {
                        const dateFields = ['date_of_birth', 'date_of_passing', 'birth_city', 'birth_state', 'birth_country', 'death_city', 'death_state', 'death_country', 'cause_of_death', 'cause_of_death_private'];
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
            }
            this.doDatesLater = !!saved.doDatesLater;
            this.doProfileLater = !!saved.doProfileLater;
            form.dispatchEvent(new Event('change', { bubbles: true }));
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
