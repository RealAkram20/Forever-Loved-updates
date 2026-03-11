@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    <x-common.page-breadcrumb pageTitle="Edit Memorial" />
    @php $completionPercent = $memorial->completion_percentage; @endphp
    <div class="mb-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-white/[0.03] p-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Profile completion</span>
                    <span id="completion-label" class="text-sm font-medium text-brand-600">{{ $completionPercent }}%</span>
                </div>
                <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div id="completion-bar" class="h-full rounded-full bg-brand-500 transition-all duration-300" style="width: {{ $completionPercent }}%"></div>
                </div>
            </div>
            <div class="shrink-0 flex items-center gap-3">
                <span x-data x-show="$store.autoSave?.status === 'saving'" x-cloak class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Saving...
                </span>
                <span x-data x-show="$store.autoSave?.status === 'saved'" x-cloak x-transition.opacity.duration.300ms class="inline-flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Saved
                </span>
                <span x-data x-show="$store.autoSave?.status === 'error'" x-cloak x-transition.opacity.duration.300ms class="inline-flex items-center gap-1.5 text-xs text-red-500">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Save failed
                </span>
                <a href="{{ route('memorial.public', ['slug' => $memorial->slug]) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Preview
                </a>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ route('memorials.update', $memorial) }}" x-data="memorialEditForm({{ $memorial->id }})" data-fields-url="{{ route('memorials.fields', $memorial) }}" data-memorial-id="{{ $memorial->id }}" @submit.prevent="saveAll()" @change="fieldChanged($event)" @input.debounce.1500ms="fieldChanged($event)">
        @csrf
        @method('PUT')
        <div class="space-y-6">
            {{-- Phase 1: Identity --}}
            <x-common.component-card title="1. Identity" desc="Core information that generates the opening sentence">
                <div class="space-y-5" data-section="identity">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $memorial->first_name) }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="middle_name">Middle name</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $memorial->middle_name) }}"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $memorial->last_name) }}" required
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="short_description">Short description</label>
                        <input type="text" id="short_description" name="short_description" value="{{ old('short_description', $memorial->short_description) }}"
                            placeholder="e.g. American businessman, co-inventor, investor"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div x-data="{ nationalityVal: '{{ old('nationality', $memorial->nationality) }}' }" @nationality-detected.window="if ($event.detail.source === 'edit_birth_country') { nationalityVal = $event.detail.nationality; $nextTick(() => $refs.natInput.dispatchEvent(new Event('change', { bubbles: true }))) }">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" x-model="nationalityVal" x-ref="natInput"
                                placeholder="Auto-filled from birth country"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="primary_profession">Primary profession</label>
                            <input type="text" id="primary_profession" name="primary_profession" value="{{ old('primary_profession', $memorial->primary_profession) }}"
                                placeholder="e.g. Entrepreneur"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="notable_title">Notable title (optional)</label>
                        <input type="text" id="notable_title" name="notable_title" value="{{ old('notable_title', $memorial->notable_title) }}"
                            placeholder="e.g. Pioneer of personal computing"
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Gender</label>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="gender" value="male" {{ old('gender', $memorial->gender) === 'male' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Male</span>
                            </label>
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="gender" value="female" {{ old('gender', $memorial->gender) === 'female' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Female</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="relationship">Relationship</label>
                        <select id="relationship" name="relationship" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                            <option value="">— Select relationship —</option>
                            <option value="Father" {{ old('relationship', $memorial->relationship) === 'Father' ? 'selected' : '' }}>Father</option>
                            <option value="Mother" {{ old('relationship', $memorial->relationship) === 'Mother' ? 'selected' : '' }}>Mother</option>
                            <option value="Spouse" {{ old('relationship', $memorial->relationship) === 'Spouse' ? 'selected' : '' }}>Spouse</option>
                            <option value="Husband" {{ old('relationship', $memorial->relationship) === 'Husband' ? 'selected' : '' }}>Husband</option>
                            <option value="Wife" {{ old('relationship', $memorial->relationship) === 'Wife' ? 'selected' : '' }}>Wife</option>
                            <option value="Child" {{ old('relationship', $memorial->relationship) === 'Child' ? 'selected' : '' }}>Child</option>
                            <option value="Son" {{ old('relationship', $memorial->relationship) === 'Son' ? 'selected' : '' }}>Son</option>
                            <option value="Daughter" {{ old('relationship', $memorial->relationship) === 'Daughter' ? 'selected' : '' }}>Daughter</option>
                            <option value="Brother" {{ old('relationship', $memorial->relationship) === 'Brother' ? 'selected' : '' }}>Brother</option>
                            <option value="Sister" {{ old('relationship', $memorial->relationship) === 'Sister' ? 'selected' : '' }}>Sister</option>
                            <option value="Grandparent" {{ old('relationship', $memorial->relationship) === 'Grandparent' ? 'selected' : '' }}>Grandparent</option>
                            <option value="Grandfather" {{ old('relationship', $memorial->relationship) === 'Grandfather' ? 'selected' : '' }}>Grandfather</option>
                            <option value="Grandmother" {{ old('relationship', $memorial->relationship) === 'Grandmother' ? 'selected' : '' }}>Grandmother</option>
                            <option value="Uncle" {{ old('relationship', $memorial->relationship) === 'Uncle' ? 'selected' : '' }}>Uncle</option>
                            <option value="Aunt" {{ old('relationship', $memorial->relationship) === 'Aunt' ? 'selected' : '' }}>Aunt</option>
                            <option value="Cousin" {{ old('relationship', $memorial->relationship) === 'Cousin' ? 'selected' : '' }}>Cousin</option>
                            <option value="Friend" {{ old('relationship', $memorial->relationship) === 'Friend' ? 'selected' : '' }}>Friend</option>
                            <option value="Other" {{ old('relationship', $memorial->relationship) === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 2: Biography Summary --}}
            <x-common.component-card title="2. Biography Summary" desc="For auto-generating the top paragraph">
                <div class="space-y-5" data-section="biography_summary">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="major_achievements">Major achievements</label>
                        <textarea id="major_achievements" name="major_achievements" rows="3" placeholder="e.g. Co-founded Apple Inc. with Steve Wozniak in 1976..."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden">{{ old('major_achievements', $memorial->major_achievements) }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="known_for">Known for</label>
                        <input type="text" id="known_for" name="known_for" value="{{ old('known_for', $memorial->known_for) }}"
                            placeholder="e.g. Co-founding Apple Inc."
                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="active_year_start">Active year start</label>
                            <input type="number" id="active_year_start" name="active_year_start" value="{{ old('active_year_start', $memorial->active_year_start) }}"
                                placeholder="e.g. 1976" min="1900" max="2100"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="active_year_end">Active year end</label>
                            <input type="number" id="active_year_end" name="active_year_end" value="{{ old('active_year_end', $memorial->active_year_end) }}"
                                placeholder="e.g. 2011" min="1900" max="2100"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Notable companies</label>
                        <div class="space-y-2" x-ref="companiesContainer">
                            @php $companiesData = old('companies', $memorial->notableCompanies->map(fn($c) => ['company_name' => $c->company_name])->toArray()); @endphp
                            @foreach($companiesData as $i => $company)
                            <div class="flex gap-2 items-center company-row">
                                <input type="text" name="companies[{{ $i }}][company_name]" value="{{ $company['company_name'] ?? '' }}"
                                    placeholder="e.g. Apple Inc."
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="$event.target.closest('.company-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addCompanyRow($refs.companiesContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add company</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Co-founders</label>
                        <div class="space-y-2" x-ref="coFoundersContainer">
                            @php $coFoundersData = old('co_founders', $memorial->coFounders->map(fn($c) => ['name' => $c->name])->toArray()); @endphp
                            @foreach($coFoundersData as $i => $founder)
                            <div class="flex gap-2 items-center cofounder-row">
                                <input type="text" name="co_founders[{{ $i }}][name]" value="{{ $founder['name'] ?? '' }}"
                                    placeholder="e.g. Steve Wozniak"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="$event.target.closest('.cofounder-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addCoFounderRow($refs.coFoundersContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add co-founder</button>
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 3: Birth --}}
            <x-common.component-card title="3. Birth Information">
                <div class="space-y-5" data-section="birth">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Date of birth</label>
                        <x-form.date-picker id="date_of_birth" name="date_of_birth" placeholder="Select date"
                            :defaultDate="old('date_of_birth', $memorial->date_of_birth?->format('Y-m-d'))" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <x-form.country-select id="edit_birth_country" name="birth_country" label="Country"
                                :value="old('birth_country', $memorial->birth_country)" :emitNationality="true" />
                        </div>
                        <div>
                            <x-form.state-select id="edit_birth_state" name="birth_state"
                                :value="old('birth_state', $memorial->birth_state)" countryFieldId="edit_birth_country" />
                        </div>
                        <div>
                            <x-form.city-select id="edit_birth_city" name="birth_city"
                                :value="old('birth_city', $memorial->birth_city)" stateFieldId="edit_birth_state" />
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 3: Passed away --}}
            <x-common.component-card title="3. Passed Away">
                <div class="space-y-5" data-section="death">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Date of passing</label>
                        <x-form.date-picker id="date_of_passing" name="date_of_passing" placeholder="Select date"
                            :defaultDate="old('date_of_passing', $memorial->date_of_passing?->format('Y-m-d'))" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <x-form.country-select id="edit_death_country" name="death_country" label="Country"
                                :value="old('death_country', $memorial->death_country)" />
                        </div>
                        <div>
                            <x-form.state-select id="edit_death_state" name="death_state"
                                :value="old('death_state', $memorial->death_state)" countryFieldId="edit_death_country" />
                        </div>
                        <div>
                            <x-form.city-select id="edit_death_city" name="death_city"
                                :value="old('death_city', $memorial->death_city)" stateFieldId="edit_death_state" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="cause_of_death">Designation</label>
                        @php $causeVal = old('cause_of_death', $memorial->cause_of_death); $causeOptions = ['COVID-19 victim','War veteran','First responder','Substance abuse victim','Cancer victim','Victim of an accident','Crime victim','Miscarriage, stillborn and infant loss','Child loss','Other','No designation']; @endphp
                        <select id="cause_of_death" name="cause_of_death" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                            <option value="">— Select designation —</option>
                            @if($causeVal && !in_array($causeVal, $causeOptions))
                            <option value="{{ e($causeVal) }}" selected>{{ $causeVal }}</option>
                            @endif
                            <option value="COVID-19 victim" {{ $causeVal === 'COVID-19 victim' ? 'selected' : '' }}>COVID-19 victim</option>
                            <option value="War veteran" {{ old('cause_of_death', $memorial->cause_of_death) === 'War veteran' ? 'selected' : '' }}>War veteran</option>
                            <option value="First responder" {{ old('cause_of_death', $memorial->cause_of_death) === 'First responder' ? 'selected' : '' }}>First responder</option>
                            <option value="Substance abuse victim" {{ old('cause_of_death', $memorial->cause_of_death) === 'Substance abuse victim' ? 'selected' : '' }}>Substance abuse victim</option>
                            <option value="Cancer victim" {{ old('cause_of_death', $memorial->cause_of_death) === 'Cancer victim' ? 'selected' : '' }}>Cancer victim</option>
                            <option value="Victim of an accident" {{ old('cause_of_death', $memorial->cause_of_death) === 'Victim of an accident' ? 'selected' : '' }}>Victim of an accident</option>
                            <option value="Crime victim" {{ old('cause_of_death', $memorial->cause_of_death) === 'Crime victim' ? 'selected' : '' }}>Crime victim</option>
                            <option value="Miscarriage, stillborn and infant loss" {{ old('cause_of_death', $memorial->cause_of_death) === 'Miscarriage, stillborn and infant loss' ? 'selected' : '' }}>Miscarriage, stillborn and infant loss</option>
                            <option value="Child loss" {{ old('cause_of_death', $memorial->cause_of_death) === 'Child loss' ? 'selected' : '' }}>Child loss</option>
                            <option value="Other" {{ old('cause_of_death', $memorial->cause_of_death) === 'Other' ? 'selected' : '' }}>Other</option>
                            <option value="No designation" {{ old('cause_of_death', $memorial->cause_of_death) === 'No designation' ? 'selected' : '' }}>No designation</option>
                        </select>
                        <label class="mt-2 flex cursor-pointer items-center gap-2">
                            <input type="hidden" name="cause_of_death_private" value="0" />
                            <input type="checkbox" name="cause_of_death_private" value="1" {{ old('cause_of_death_private', $memorial->cause_of_death_private) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700">Keep designation private</span>
                        </label>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 5: Family --}}
            <x-common.component-card title="5. Family Relationships" desc="Optional, can be added later">
                <div class="space-y-6" data-section="family">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Children</label>
                        <div class="space-y-2" x-ref="childrenContainer">
                            @php $children = old('children', $memorial->children->map(fn($c) => ['child_name' => $c->child_name, 'birth_year' => $c->birth_year])->toArray() ?: []); @endphp
                            @foreach($children as $i => $child)
                            <div class="flex gap-2 items-center child-row">
                                <input type="text" name="children[{{ $i }}][child_name]" value="{{ $child['child_name'] ?? '' }}" placeholder="Name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <input type="number" name="children[{{ $i }}][birth_year]" value="{{ $child['birth_year'] ?? '' }}" placeholder="Year" min="1900" max="2100"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <button type="button" @click="$event.target.closest('.child-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addChildRow($refs.childrenContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add child</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Spouses</label>
                        <div class="space-y-2" x-ref="spousesContainer">
                            @php $spouses = old('spouses', $memorial->spouses->map(fn($s) => ['spouse_name' => $s->spouse_name, 'marriage_start_year' => $s->marriage_start_year, 'marriage_end_year' => $s->marriage_end_year])->toArray() ?: []); @endphp
                            @foreach($spouses as $i => $spouse)
                            <div class="flex flex-wrap gap-2 items-center spouse-row">
                                <input type="text" name="spouses[{{ $i }}][spouse_name]" value="{{ $spouse['spouse_name'] ?? '' }}" placeholder="Name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 min-w-[120px] rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <input type="number" name="spouses[{{ $i }}][marriage_start_year]" value="{{ $spouse['marriage_start_year'] ?? '' }}" placeholder="Start year" min="1900" max="2100"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <input type="number" name="spouses[{{ $i }}][marriage_end_year]" value="{{ $spouse['marriage_end_year'] ?? '' }}" placeholder="End year" min="1900" max="2100"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <button type="button" @click="$event.target.closest('.spouse-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addSpouseRow($refs.spousesContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add spouse</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Parents</label>
                        <div class="space-y-2" x-ref="parentsContainer">
                            @php $parents = old('parents', $memorial->parents->map(fn($p) => ['parent_name' => $p->parent_name, 'relationship_type' => $p->relationship_type])->toArray() ?: []); @endphp
                            @foreach($parents as $i => $parent)
                            <div class="flex gap-2 items-center parent-row">
                                <input type="text" name="parents[{{ $i }}][parent_name]" value="{{ $parent['parent_name'] ?? '' }}" placeholder="Name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <select name="parents[{{ $i }}][relationship_type]" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm">
                                    <option value="biological" {{ ($parent['relationship_type'] ?? '') === 'biological' ? 'selected' : '' }}>Biological</option>
                                    <option value="adoptive" {{ ($parent['relationship_type'] ?? '') === 'adoptive' ? 'selected' : '' }}>Adoptive</option>
                                </select>
                                <button type="button" @click="$event.target.closest('.parent-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addParentRow($refs.parentsContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add parent</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Siblings</label>
                        <div class="space-y-2" x-ref="siblingsContainer">
                            @php $siblings = old('siblings', $memorial->siblings->map(fn($s) => ['sibling_name' => $s->sibling_name])->toArray() ?: []); @endphp
                            @foreach($siblings as $i => $sibling)
                            <div class="flex gap-2 items-center sibling-row">
                                <input type="text" name="siblings[{{ $i }}][sibling_name]" value="{{ $sibling['sibling_name'] ?? '' }}" placeholder="Name"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <button type="button" @click="$event.target.closest('.sibling-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addSiblingRow($refs.siblingsContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add sibling</button>
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 6: Education --}}
            <x-common.component-card title="6. Education" desc="Optional">
                <div class="space-y-2" x-ref="educationContainer" data-section="education">
                    @php $education = old('education', $memorial->education->map(fn($e) => ['institution_name' => $e->institution_name, 'start_year' => $e->start_year, 'end_year' => $e->end_year, 'degree' => $e->degree])->toArray() ?: []); @endphp
                    @foreach($education as $i => $edu)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4 items-end education-row">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Institution</label>
                            <input type="text" name="education[{{ $i }}][institution_name]" value="{{ $edu['institution_name'] ?? '' }}" placeholder="e.g. Reed College"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Start year</label>
                            <input type="number" name="education[{{ $i }}][start_year]" value="{{ $edu['start_year'] ?? '' }}" placeholder="1972" min="1900" max="2100"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">End year</label>
                            <input type="number" name="education[{{ $i }}][end_year]" value="{{ $edu['end_year'] ?? '' }}" placeholder="1974" min="1900" max="2100"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Degree (optional)</label>
                            <input type="text" name="education[{{ $i }}][degree]" value="{{ $edu['degree'] ?? '' }}" placeholder="e.g. B.A."
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button type="button" @click="$event.target.closest('.education-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    @endforeach
                    <button type="button" @click="addEducationRow($refs.educationContainer)" class="text-sm text-brand-600 hover:text-brand-700">+ Add education</button>
                </div>
            </x-common.component-card>

            {{-- Biography --}}
            <x-common.component-card id="biography" title="Biography" desc="Preview your biography. Click edit to modify, or generate from template/AI.">
                <div class="space-y-6" x-data="bioGenerator({{ $memorial->id }}, {{ json_encode($memorial->biography) }})">
                    <script type="application/json" id="edit-page-biography-initial">{{ json_encode($memorial->biography ?? '') }}</script>

                    {{-- Preview mode (default): show biography with edit icon --}}
                    <div x-show="!showEditor" class="rounded-lg border border-brand-200 dark:border-brand-800 bg-brand-50/30 dark:bg-brand-500/10 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-brand-700 dark:text-brand-400 mb-2">Biography</p>
                                <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm max-w-none" x-html="formatBio(previewContent)"></div>
                                <p x-show="!previewContent || !previewContent.trim()" class="text-gray-500 dark:text-gray-400 italic">Add biography... Use the buttons below to generate, or click edit to write.</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" @click="openEditor(previewContent)" class="rounded p-1.5 text-gray-500 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-white/10 transition-colors" title="Edit">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button type="button" x-show="previewContent && previewContent.trim()" @click="publishContent(previewContent)" :disabled="publishing"
                                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                    Publish
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Editor mode: rendered only when shown so Quill initializes in visible container --}}
                    <template x-if="showEditor">
                        <div class="rounded-lg border border-brand-200 dark:border-brand-800 bg-brand-50/30 dark:bg-brand-500/10 p-4">
                            <p class="text-sm font-medium text-brand-700 dark:text-brand-400 mb-2">Your story</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">Share your memories with text, photos, videos, or documents.</p>
                            <div id="edit-page-biography-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" x-init="$nextTick(() => { window.initEditPageBiographyEditor && window.initEditPageBiographyEditor(contentToLoad); })"></div>
                            <div class="flex gap-2 mt-3">
                                <button type="button" id="edit-page-biography-publish" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                    Publish
                                </button>
                                <button type="button" @click="showEditor = false; contentToLoad = ''; window.editPageBioQuill = null" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- AI suggestions: only shown when AI generation returns results --}}
                    <div x-show="aiOptions.length > 0" class="space-y-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30 p-4">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            <span x-text="aiProviderName"></span> suggestions — click "Use this" to edit and publish:
                        </p>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <template x-for="(opt, idx) in aiOptions" :key="idx">
                                <div class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 p-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm max-w-none mb-4" x-html="formatBio(opt.text)"></div>
                                    <button type="button" @click="useAiOption(opt.text)"
                                        class="w-full rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                                        Use this →
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Generate buttons --}}
                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="button" @click="generateTemplate()" :disabled="templateLoading"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">
                            <span x-show="!templateLoading">Generate Bio</span>
                            <span x-show="templateLoading">Generating...</span>
                        </button>
                        @if($aiEnabled ?? false)
                        <button type="button" @click="generateAI()" :disabled="aiLoading"
                            class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50">
                            <span x-show="!aiLoading">Generate with AI</span>
                            <span x-show="aiLoading">Generating...</span>
                        </button>
                        @endif
                    </div>
                </div>
            </x-common.component-card>

            {{-- Settings --}}
            <x-common.component-card title="Settings">
                <div class="space-y-5" data-section="settings">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Theme</label>
                        <div class="relative z-20 bg-transparent">
                            <select name="theme"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                                <option value="free" {{ old('theme', $memorial->theme) === 'free' ? 'selected' : '' }}>Classic</option>
                                <option value="premium" {{ old('theme', $memorial->theme) === 'premium' ? 'selected' : '' }}>Premium</option>
                                <option value="modern" {{ old('theme', $memorial->theme) === 'modern' ? 'selected' : '' }}>Modern</option>
                                <option value="garden" {{ old('theme', $memorial->theme) === 'garden' ? 'selected' : '' }}>Garden</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700">Plan</label>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="plan" value="free" {{ old('plan', $memorial->plan ?? 'free') === 'free' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Free</span>
                            </label>
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="plan" value="paid" {{ old('plan', $memorial->plan ?? 'free') === 'paid' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Paid</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="hidden" name="is_public" value="0" />
                            <input type="checkbox" name="is_public" value="1" {{ old('is_public', $memorial->is_public) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700">Public memorial (visible to everyone)</span>
                        </label>
                    </div>
                </div>
            </x-common.component-card>

            <div class="flex gap-3">
                <button type="submit" :disabled="savingAll"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50 transition-colors"
                    :class="savedAll ? 'bg-green-500' : 'bg-brand-500 hover:bg-brand-600'">
                    <span x-show="!savingAll && !savedAll">Save</span>
                    <span x-show="savingAll">Saving...</span>
                    <span x-show="savedAll && !savingAll">Saved!</span>
                </button>
                <a href="{{ route('memorials.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('autoSave', { status: '' });
        });

        function memorialEditForm(memorialId) {
            const RELATION_KEYS = ['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'];
            return {
                memorialId,
                savingAll: false,
                savedAll: false,
                pendingFields: {},
                autoSaveTimer: null,
                autoSaveInFlight: false,
                lastSavedEvent: null,
                init() {
                    window.collectMemorialFormData = () => this.collectFormData();
                },
                fieldChanged(ev) {
                    const el = ev?.target;
                    if (!el || !el.name || el.name === '_token' || el.name === '_method') return;
                    if (this.lastSavedEvent === ev) return;
                    this.lastSavedEvent = ev;

                    const name = el.name;
                    if (name.includes('[')) {
                        const relName = name.split('[')[0];
                        if (RELATION_KEYS.includes(relName)) {
                            this.queueRelationship(relName);
                        }
                    } else {
                        if (el.type === 'checkbox') {
                            this.pendingFields[name] = el.checked ? 1 : 0;
                        } else if (el.type === 'radio') {
                            const checked = this.$el.querySelector(`input[name="${name}"]:checked`);
                            if (checked) this.pendingFields[name] = checked.value;
                        } else {
                            this.pendingFields[name] = el.value;
                        }
                    }
                    this.scheduleAutoSave();
                },
                queueRelationship(relName) {
                    const all = this.collectFormData();
                    this.pendingFields[relName] = (relName in all) ? all[relName] : [];
                },
                scheduleAutoSave() {
                    if (this.autoSaveTimer) clearTimeout(this.autoSaveTimer);
                    this.autoSaveTimer = setTimeout(() => this.autoSave(), 800);
                },
                async autoSave() {
                    if (this.autoSaveInFlight) {
                        this.scheduleAutoSave();
                        return;
                    }
                    const fields = { ...this.pendingFields };
                    if (!Object.keys(fields).length) return;
                    this.pendingFields = {};
                    this.autoSaveInFlight = true;
                    Alpine.store('autoSave').status = 'saving';

                    const csrfEl = document.querySelector('meta[name="csrf-token"]');
                    if (!csrfEl) {
                        Alpine.store('autoSave').status = 'error';
                        this.autoSaveInFlight = false;
                        return;
                    }
                    const controller = new AbortController();
                    const timeout = setTimeout(() => controller.abort(), 15000);
                    try {
                        const url = this.$el.dataset.fieldsUrl || `/memorials/${this.memorialId}/fields`;
                        const r = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfEl.content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ fields }),
                            signal: controller.signal
                        });
                        clearTimeout(timeout);
                        const res = await r.json().catch(() => ({}));
                        if (r.ok && res.success) {
                            if (res.completion_percentage !== undefined) this.updateProgressBar(res.completion_percentage);
                            Alpine.store('autoSave').status = 'saved';
                            setTimeout(() => {
                                if (Alpine.store('autoSave').status === 'saved') Alpine.store('autoSave').status = '';
                            }, 2000);
                        } else {
                            Alpine.store('autoSave').status = 'error';
                            const msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Save failed';
                            $toast('error', msg);
                            setTimeout(() => {
                                if (Alpine.store('autoSave').status === 'error') Alpine.store('autoSave').status = '';
                            }, 4000);
                        }
                    } catch (e) {
                        clearTimeout(timeout);
                        Alpine.store('autoSave').status = 'error';
                        $toast('error', e.name === 'AbortError' ? 'Save timed out.' : 'Save failed. Check your connection.');
                        setTimeout(() => {
                            if (Alpine.store('autoSave').status === 'error') Alpine.store('autoSave').status = '';
                        }, 4000);
                    } finally {
                        this.autoSaveInFlight = false;
                        if (Object.keys(this.pendingFields).length) this.scheduleAutoSave();
                    }
                },
                updateProgressBar(percent) {
                    const bar = document.getElementById('completion-bar');
                    const label = document.getElementById('completion-label');
                    if (bar) bar.style.width = percent + '%';
                    if (label) label.textContent = percent + '%';
                },
                collectFormData() {
                    const form = this.$el;
                    const fd = new FormData(form);
                    const obj = {};
                    for (const [key, value] of fd.entries()) {
                        if (key.includes('[')) {
                            const parts = key.split(/[\[\]]/).filter(Boolean);
                            let cur = obj;
                            for (let i = 0; i < parts.length - 1; i++) {
                                const p = parts[i];
                                const next = parts[i + 1];
                                if (!(p in cur)) cur[p] = (next && !isNaN(parseInt(next))) ? [] : {};
                                cur = cur[p];
                            }
                            cur[parts[parts.length - 1]] = value;
                        } else {
                            obj[key] = value;
                        }
                    }
                    const cdp = form.querySelector('input[name="cause_of_death_private"][type="checkbox"]');
                    obj.cause_of_death_private = cdp ? (cdp.checked ? 1 : 0) : 0;
                    const ip = form.querySelector('input[name="is_public"][type="checkbox"]');
                    obj.is_public = ip ? (ip.checked ? 1 : 0) : 0;
                    return obj;
                },
                async saveAll() {
                    if (this.autoSaveTimer) clearTimeout(this.autoSaveTimer);
                    this.pendingFields = {};
                    this.savingAll = true;
                    this.savedAll = false;
                    const data = this.collectFormData();
                    delete data._token;
                    delete data._method;
                    RELATION_KEYS.forEach(k => { if (!(k in data)) data[k] = []; });
                    try {
                        const r = await fetch(this.$el.action, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify(data)
                        });
                        const res = await r.json().catch(() => ({}));
                        if (r.ok && res.success) {
                            this.savedAll = true;
                            if (res.completion_percentage !== undefined) this.updateProgressBar(res.completion_percentage);
                            $toast('success', res.message || 'Memorial saved successfully.');
                            setTimeout(() => this.savedAll = false, 2000);
                        } else {
                            const msg = res.message || (res.errors ? Object.values(res.errors).flat().join(' ') : null) || 'Failed to save.';
                            $toast('error', msg);
                        }
                    } catch (e) {
                        $toast('error', 'Failed to save. Check your connection.');
                    } finally {
                        this.savingAll = false;
                    }
                },
                addCompanyRow(container) {
                    const idx = container.querySelectorAll('.company-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center company-row';
                    div.innerHTML = `<input type="text" name="companies[${idx}][company_name]" placeholder="e.g. Apple Inc." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addCoFounderRow(container) {
                    const idx = container.querySelectorAll('.cofounder-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center cofounder-row';
                    div.innerHTML = `<input type="text" name="co_founders[${idx}][name]" placeholder="e.g. Steve Wozniak" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addChildRow(container) {
                    const idx = container.querySelectorAll('.child-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center child-row';
                    div.innerHTML = `<input type="text" name="children[${idx}][child_name]" placeholder="Name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <input type="number" name="children[${idx}][birth_year]" placeholder="Year" min="1900" max="2100" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSpouseRow(container) {
                    const idx = container.querySelectorAll('.spouse-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex flex-wrap gap-2 items-center spouse-row';
                    div.innerHTML = `<input type="text" name="spouses[${idx}][spouse_name]" placeholder="Name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 min-w-[120px] rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <input type="number" name="spouses[${idx}][marriage_start_year]" placeholder="Start year" min="1900" max="2100" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <input type="number" name="spouses[${idx}][marriage_end_year]" placeholder="End year" min="1900" max="2100" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addParentRow(container) {
                    const idx = container.querySelectorAll('.parent-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center parent-row';
                    div.innerHTML = `<input type="text" name="parents[${idx}][parent_name]" placeholder="Name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <select name="parents[${idx}][relationship_type]" class="dark:bg-dark-900 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm"><option value="biological">Biological</option><option value="adoptive">Adoptive</option></select>
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSiblingRow(container) {
                    const idx = container.querySelectorAll('.sibling-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center sibling-row';
                    div.innerHTML = `<input type="text" name="siblings[${idx}][sibling_name]" placeholder="Name" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addEducationRow(container) {
                    const idx = container.querySelectorAll('.education-row').length;
                    const div = document.createElement('div');
                    div.className = 'grid grid-cols-1 gap-2 sm:grid-cols-4 items-end education-row';
                    div.innerHTML = `<div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Institution</label><input type="text" name="education[${idx}][institution_name]" placeholder="e.g. Reed College" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">Start year</label><input type="number" name="education[${idx}][start_year]" placeholder="1972" min="1900" max="2100" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">End year</label><input type="number" name="education[${idx}][end_year]" placeholder="1974" min="1900" max="2100" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Degree (optional)</label><input type="text" name="education[${idx}][degree]" placeholder="e.g. B.A." class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="flex items-end"><button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                }
            };
        }

        function bioGenerator(memorialId, currentBio) {
            return {
                memorialId,
                currentBio: currentBio || '',
                showEditor: false,
                previewContent: currentBio || '',
                contentToLoad: '',
                templateLoading: false,
                aiOptions: [],
                aiProviderName: '',
                aiLoading: false,
                publishing: false,
                formatBio(text) {
                    if (!text || !String(text).trim()) return '';
                    if (text.includes('<')) return text;
                    const div = document.createElement('div');
                    div.textContent = text;
                    const escaped = div.innerHTML;
                    const withBold = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                    const paragraphs = withBold.split(/\n\n+/).filter(p => p.trim());
                    if (paragraphs.length === 0) return withBold.replace(/\n/g, '<br>');
                    return paragraphs.map(p => '<p>' + p.trim().replace(/\n/g, '<br>') + '</p>').join('');
                },
                toStorageFormat(text) {
                    if (!text || !String(text).trim()) return '';
                    if (text.includes('<')) return text;
                    const div = document.createElement('div');
                    div.textContent = text;
                    const escaped = div.innerHTML;
                    const withBold = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                    const paragraphs = withBold.split(/\n\n+/).filter(p => p.trim());
                    if (paragraphs.length === 0) return withBold.replace(/\n/g, '<br>');
                    return paragraphs.map(p => '<p>' + p.trim().replace(/\n/g, '<br>') + '</p>').join('');
                },
                openEditor(content) {
                    this.contentToLoad = content || '';
                    this.showEditor = true;
                },
                useAiOption(text) {
                    this.showEditor = false;
                    window.editPageBioQuill = null;
                    this.contentToLoad = text || '';
                    this.aiOptions = [];
                    this.$nextTick(() => {
                        this.showEditor = true;
                    });
                },
                async publishContent(content) {
                    if (!content || !String(content).trim()) return;
                    this.publishing = true;
                    const toSave = content.includes('<') ? content : this.toStorageFormat(content);
                    try {
                        const r = await fetch(`/memorials/${this.memorialId}/biography`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ biography: toSave })
                        });
                        const data = await r.json().catch(() => ({}));
                        if (r.ok && data.success) {
                            this.previewContent = toSave;
                            this.showEditor = false;
                            window.editPageBioQuill = null;
                            $toast('success', 'Biography published successfully.');
                        } else {
                            $toast('error', data.message || data.error || 'Failed to publish.');
                        }
                    } catch (e) {
                        $toast('error', 'Failed to publish. Please check your connection.');
                    } finally {
                        this.publishing = false;
                    }
                },
                generateTemplate() {
                    this.templateLoading = true;
                    const formData = typeof window.collectMemorialFormData === 'function' ? window.collectMemorialFormData() : {};
                    fetch(`/memorials/${this.memorialId}/generate-template-biography`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ form_data: formData })
                    })
                    .then(r => r.json())
                    .then(data => {
                        const bio = (data.biography ?? '').toString().trim();
                        if (!bio) {
                            $toast('warning', 'No biography generated. Please add more details (name, dates, profession) and try again.');
                            return;
                        }
                        this.aiOptions = [];
                        this.contentToLoad = bio;
                        this.showEditor = true;
                    })
                    .catch(() => $toast('error', 'Failed to generate biography. Save the form first if you added new fields.'))
                    .finally(() => this.templateLoading = false);
                },
                generateAI() {
                    this.aiLoading = true;
                    const formData = typeof window.collectMemorialFormData === 'function' ? window.collectMemorialFormData() : {};
                    fetch(`/memorials/${this.memorialId}/generate-biography`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ form_data: formData, no_cache: true })
                    })
                    .then(r => {
                        if (!r.ok) {
                            return r.json().catch(() => ({})).then(err => {
                                throw new Error(err.message || 'AI generation failed. Please try again.');
                            });
                        }
                        return r.json();
                    })
                    .then(data => {
                        const o1 = (data.option_1 ?? '').toString().trim();
                        const o2 = (data.option_2 ?? '').toString().trim();
                        const o3 = (data.option_3 ?? '').toString().trim();
                        if (!o1 && !o2 && !o3) {
                            $toast('warning', 'No suggestions were generated. Please add more details (name, dates, profession) and try again.');
                            return;
                        }
                        this.aiProviderName = data.ai_provider || 'AI';
                        this.aiOptions = [
                            { text: o1 || 'No content generated for this option.' },
                            { text: o2 || 'No content generated for this option.' },
                            { text: o3 || 'No content generated for this option.' }
                        ];
                    })
                    .catch((e) => $toast('error', e.message || 'Failed to generate AI biography. Please try again.'))
                    .finally(() => this.aiLoading = false);
                }
            };
        }
    </script>

    {{-- Edit page biography Quill editor: init only when editor is shown --}}
    <script>
        (function() {
            if (typeof Quill === 'undefined') return;
            const quillToolbar = [
                [{ 'size': ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline'],
                [{ 'color': [] }],
                ['link', 'blockquote'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['clean'],
                ['code-block']
            ];

            function plainToHtml(text) {
                if (!text || !String(text).trim()) return '';
                const div = document.createElement('div');
                div.textContent = text;
                const escaped = div.innerHTML;
                const withBold = escaped.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                const paragraphs = withBold.split(/\n\n+/).filter(p => p.trim());
                if (paragraphs.length === 0) return withBold.replace(/\n/g, '<br>');
                return paragraphs.map(p => '<p>' + p.trim().replace(/\n/g, '<br>') + '</p>').join('');
            }

            window.initEditPageBiographyEditor = function(content) {
                const editorEl = document.getElementById('edit-page-biography-editor');
                const publishBtn = document.getElementById('edit-page-biography-publish');
                if (!editorEl || !publishBtn) return;

                if (window.editPageBioQuill) {
                    window.editPageBioQuill = null;
                }
                editorEl.innerHTML = '';

                const q = new Quill('#edit-page-biography-editor', {
                    theme: 'snow',
                    placeholder: 'Share your memories...',
                    modules: { toolbar: quillToolbar }
                });
                window.editPageBioQuill = q;

                if (content && String(content).trim()) {
                    q.setContents([]);
                    if (content.includes('<')) {
                        q.clipboard.dangerouslyPasteHTML(0, content);
                    } else {
                        q.clipboard.dangerouslyPasteHTML(0, plainToHtml(content));
                    }
                }

                const memorialId = document.querySelector('[data-memorial-id]')?.dataset?.memorialId;
                publishBtn.onclick = async function() {
                    const html = q.root.innerHTML?.trim() ?? '';
                    const toSave = (html === '<p><br></p>' || !html) ? '' : html;
                    if (!toSave) {
                        $toast('warning', 'Please add some content before publishing.');
                        return;
                    }
                    publishBtn.disabled = true;
                    publishBtn.textContent = 'Publishing...';
                    try {
                        const r = await fetch(`/memorials/${memorialId}/biography`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ biography: toSave })
                        });
                        const data = await r.json().catch(() => ({}));
                        if (r.ok && data.success) {
                            const bioEl = publishBtn.closest('[x-data]');
                            if (bioEl && typeof Alpine !== 'undefined') {
                                const bioData = Alpine.$data(bioEl);
                                bioData.previewContent = toSave;
                                bioData.showEditor = false;
                            }
                            window.editPageBioQuill = null;
                            $toast('success', 'Biography published successfully.');
                        } else {
                            $toast('error', data.message || data.error || 'Failed to publish. Please try again.');
                        }
                    } catch (e) {
                        $toast('error', 'Failed to publish. Please check your connection.');
                    } finally {
                        publishBtn.disabled = false;
                        publishBtn.textContent = 'Publish';
                    }
                };
            };

        })();
    </script>
@endsection
