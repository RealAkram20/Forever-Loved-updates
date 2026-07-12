@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    <x-common.page-breadcrumb pageTitle="Create Memorial" />
    <form method="POST" action="{{ route('memorials.store') }}" x-data="memorialCreateForm()">
        @csrf

        @if ($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-600">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-6">
            {{-- Phase 1: Identity --}}
            <x-common.component-card title="1. Identity" desc="Core information that generates the opening sentence">
                <div class="space-y-5">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="middle_name">Middle name (optional)</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name') }}"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                            @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="short_description">Short description</label>
                        <input type="text" id="short_description" name="short_description" value="{{ old('short_description') }}"
                            placeholder="e.g. Loving mother, teacher, community leader"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div x-data="{ nationalityVal: '{{ old('nationality') }}' }" @nationality-detected.window="if ($event.detail.source === 'create_birth_country') nationalityVal = $event.detail.nationality">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="nationality">Nationality</label>
                            <input type="text" id="nationality" name="nationality" x-model="nationalityVal"
                                placeholder="Auto-filled from birth country"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="primary_profession">Primary profession</label>
                            <input type="text" id="primary_profession" name="primary_profession" value="{{ old('primary_profession') }}"
                                placeholder="e.g. Teacher"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="notable_title">Notable title (optional)</label>
                        <input type="text" id="notable_title" name="notable_title" value="{{ old('notable_title') }}"
                            placeholder="e.g. Respected community elder"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                        <div class="flex flex-wrap items-center gap-6">
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="gender" value="male" {{ old('gender') === 'male' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Male</span>
                            </label>
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                <input type="radio" name="gender" value="female" {{ old('gender') === 'female' ? 'checked' : '' }}
                                    class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                <span class="ml-2">Female</span>
                            </label>
                        </div>
                    </div>

                    <x-form.relationship-select
                        :value="old('relationship')"
                        :other="old('relationship_other')" />
                </div>
            </x-common.component-card>

            {{-- Phase 2: Biography Summary --}}
            <x-common.component-card title="2. Biography Summary" desc="For auto-generating the top paragraph">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="major_achievements">Major achievements</label>
                        <textarea id="major_achievements" name="major_achievements" rows="3" placeholder="e.g. Built the family business and mentored many young people..."
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden">{{ old('major_achievements') }}</textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="known_for">Known for</label>
                        <input type="text" id="known_for" name="known_for" value="{{ old('known_for') }}"
                            placeholder="e.g. Founding the community school"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="active_year_start">Active year start</label>
                            <x-form.year-select id="active_year_start" name="active_year_start"
                                :value="old('active_year_start')" placeholder="Select start year" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" for="active_year_end">Active year end</label>
                            <x-form.year-select id="active_year_end" name="active_year_end"
                                :value="old('active_year_end')" placeholder="Select end year" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notable companies</label>
                        <div class="space-y-2" x-ref="companiesContainer">
                            @php $companiesData = old('companies', [['company_name' => '']]); @endphp
                            @foreach($companiesData as $i => $company)
                            <div class="flex gap-2 items-center company-row">
                                <input type="text" name="companies[{{ $i }}][company_name]" value="{{ $company['company_name'] ?? '' }}"
                                    placeholder="e.g. Business or organisation name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="$event.target.closest('.company-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addCompanyRow($refs.companiesContainer)" class="btn btn-link btn-md">+ Add company</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Co-founders</label>
                        <div class="space-y-2" x-ref="coFoundersContainer">
                            @php $coFoundersData = old('co_founders', [['name' => '']]); @endphp
                            @foreach($coFoundersData as $i => $founder)
                            <div class="flex gap-2 items-center cofounder-row">
                                <input type="text" name="co_founders[{{ $i }}][name]" value="{{ $founder['name'] ?? '' }}"
                                    placeholder="e.g. Business partner name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                <button type="button" @click="$event.target.closest('.cofounder-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addCoFounderRow($refs.coFoundersContainer)" class="btn btn-link btn-md">+ Add co-founder</button>
                        </div>
                    </div>

                </div>
            </x-common.component-card>

            {{-- Phase 3: Birth --}}
            <x-common.component-card title="3. Birth Information">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of birth</label>
                        <x-form.date-picker id="date_of_birth" name="date_of_birth" placeholder="Select date"
                            :defaultDate="old('date_of_birth')" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <x-form.country-select id="create_birth_country" name="birth_country" label="Country"
                                :value="old('birth_country')" :autoDetect="true" :emitNationality="true" />
                        </div>
                        <div>
                            <x-form.state-select id="create_birth_state" name="birth_state"
                                :value="old('birth_state')" countryFieldId="create_birth_country" />
                        </div>
                        <div>
                            <x-form.city-select id="create_birth_city" name="birth_city"
                                :value="old('birth_city')" stateFieldId="create_birth_state" />
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 3: Passed Away --}}
            <x-common.component-card title="4. Passed Away">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of passing</label>
                        <x-form.date-picker id="date_of_passing" name="date_of_passing" placeholder="Select date"
                            :defaultDate="old('date_of_passing')" />
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <x-form.country-select id="create_death_country" name="death_country" label="Country"
                                :value="old('death_country')" />
                        </div>
                        <div>
                            <x-form.state-select id="create_death_state" name="death_state"
                                :value="old('death_state')" countryFieldId="create_death_country" />
                        </div>
                        <div>
                            <x-form.city-select id="create_death_city" name="death_city"
                                :value="old('death_city')" stateFieldId="create_death_state" />
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 5: Family --}}
            <x-common.component-card title="5. Family Relationships" desc="Optional, can be added later">
                <div class="space-y-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Children</label>
                        <div class="space-y-2" x-ref="childrenContainer">
                            @php $children = old('children', []); @endphp
                            @foreach($children as $i => $child)
                            <div class="flex flex-wrap gap-2 items-center child-row">
                                <input type="text" name="children[{{ $i }}][child_name]" value="{{ $child['child_name'] ?? '' }}" placeholder="Name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <input type="number" name="children[{{ $i }}][birth_year]" value="{{ $child['birth_year'] ?? '' }}" placeholder="Year" min="1900" max="2100"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <button type="button" @click="$event.target.closest('.child-row').remove()" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addChildRow($refs.childrenContainer)" class="btn btn-link btn-md">+ Add child</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Spouses</label>
                        <div class="space-y-2" x-ref="spousesContainer">
                            @php $spouses = old('spouses', []); @endphp
                            @foreach($spouses as $i => $spouse)
                            <div class="spouse-row space-y-2 rounded-lg border border-gray-100 bg-gray-50/50 p-2.5 dark:border-gray-700 dark:bg-white/[0.02]">
                                <input type="text" name="spouses[{{ $i }}][spouse_name]" value="{{ $spouse['spouse_name'] ?? '' }}" placeholder="Name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm dark:bg-transparent" />
                                <div class="flex gap-2 items-center">
                                    <input type="number" name="spouses[{{ $i }}][marriage_start_year]" value="{{ $spouse['marriage_start_year'] ?? '' }}" placeholder="Start year" min="1900" max="2100"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" />
                                    <span class="text-gray-400 shrink-0">&ndash;</span>
                                    <input type="number" name="spouses[{{ $i }}][marriage_end_year]" value="{{ $spouse['marriage_end_year'] ?? '' }}" placeholder="End year" min="1900" max="2100"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" />
                                    <button type="button" @click="$event.target.closest('.spouse-row').remove()" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                            @endforeach
                            <button type="button" @click="addSpouseRow($refs.spousesContainer)" class="btn btn-link btn-md">+ Add spouse</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Parents</label>
                        <div class="space-y-2" x-ref="parentsContainer">
                            @php $parents = old('parents', []); @endphp
                            @foreach($parents as $i => $parent)
                            <div class="flex flex-wrap gap-2 items-center parent-row">
                                <input type="text" name="parents[{{ $i }}][parent_name]" value="{{ $parent['parent_name'] ?? '' }}" placeholder="Name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <select name="parents[{{ $i }}][relationship_type]" class="h-11 shrink-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent dark:bg-gray-900/80 px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100">
                                    <option value="biological" {{ ($parent['relationship_type'] ?? '') === 'biological' ? 'selected' : '' }}>Biological</option>
                                    <option value="adoptive" {{ ($parent['relationship_type'] ?? '') === 'adoptive' ? 'selected' : '' }}>Adoptive</option>
                                </select>
                                <button type="button" @click="$event.target.closest('.parent-row').remove()" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addParentRow($refs.parentsContainer)" class="btn btn-link btn-md">+ Add parent</button>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Siblings</label>
                        <div class="space-y-2" x-ref="siblingsContainer">
                            @php $siblings = old('siblings', []); @endphp
                            @foreach($siblings as $i => $sibling)
                            <div class="flex gap-2 items-center sibling-row">
                                <input type="text" name="siblings[{{ $i }}][sibling_name]" value="{{ $sibling['sibling_name'] ?? '' }}" placeholder="Name"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                                <button type="button" @click="$event.target.closest('.sibling-row').remove()" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            @endforeach
                            <button type="button" @click="addSiblingRow($refs.siblingsContainer)" class="btn btn-link btn-md">+ Add sibling</button>
                        </div>
                    </div>
                </div>
            </x-common.component-card>

            {{-- Phase 6: Education --}}
            <x-common.component-card title="6. Education" desc="Optional">
                <div class="space-y-2" x-ref="educationContainer">
                    @php $education = old('education', []); @endphp
                    @foreach($education as $i => $edu)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-4 items-end education-row">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Institution</label>
                            <input type="text" name="education[{{ $i }}][institution_name]" value="{{ $edu['institution_name'] ?? '' }}" placeholder="e.g. School or university name"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Start year</label>
                            <input type="number" name="education[{{ $i }}][start_year]" value="{{ $edu['start_year'] ?? '' }}" placeholder="1990" min="1900" max="2100"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">End year</label>
                            <input type="number" name="education[{{ $i }}][end_year]" value="{{ $edu['end_year'] ?? '' }}" placeholder="1994" min="1900" max="2100"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-xs text-gray-500">Degree (optional)</label>
                            <input type="text" name="education[{{ $i }}][degree]" value="{{ $edu['degree'] ?? '' }}" placeholder="e.g. Diploma"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button type="button" @click="$event.target.closest('.education-row').remove()" class="text-red-500 hover:text-red-700 p-2" title="Remove">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                    @endforeach
                    <button type="button" @click="addEducationRow($refs.educationContainer)" class="btn btn-link btn-md">+ Add education</button>
                </div>
            </x-common.component-card>

            {{-- Biography --}}
            <x-common.component-card title="Biography" desc="Write or paste a biography. You can also use AI to generate one after creation.">
                <div>
                    <input type="hidden" name="biography" id="biography-hidden" value="{{ old('biography') }}" />
                    <div id="create-biography-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                    <p class="mt-2 text-xs text-gray-500">AI-generated biography options will be available after creating the memorial.</p>
                </div>
            </x-common.component-card>

            {{-- Settings --}}
            <x-common.component-card title="Settings">
                <div class="space-y-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Theme</label>
                        <input type="hidden" name="theme" value="free" />
                        <div class="flex h-11 w-full items-center rounded-lg border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 dark:border-gray-600 dark:bg-white/5 dark:text-gray-300">
                            Classic
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                        <input type="hidden" name="plan" value="free" />
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                Free
                            </span>
                            <a href="{{ route('pricing') }}"
                                class="btn btn-primary btn-sm">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                Upgrade
                            </a>
                        </div>
                    </div>
                    <div>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="hidden" name="is_public" value="0" />
                            <input type="checkbox" name="is_public" value="1" {{ old('is_public', true) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Public memorial (visible to everyone)</span>
                        </label>
                    </div>
                </div>
            </x-common.component-card>

            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary btn-md">
                    Create Memorial
                </button>
                <a href="{{ route('memorials.index') }}" class="btn btn-secondary btn-md">
                    Cancel
                </a>
            </div>
        </div>
    </form>

    <script>
        function memorialCreateForm() {
            return {
                addCompanyRow(container) {
                    const idx = container.querySelectorAll('.company-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center company-row';
                    div.innerHTML = `<input type="text" name="companies[${idx}][company_name]" placeholder="e.g. Business or organisation name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addCoFounderRow(container) {
                    const idx = container.querySelectorAll('.cofounder-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center cofounder-row';
                    div.innerHTML = `<input type="text" name="co_founders[${idx}][name]" placeholder="e.g. Business partner name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                        <button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addChildRow(container) {
                    const idx = container.querySelectorAll('.child-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex flex-wrap gap-2 items-center child-row';
                    div.innerHTML = `<input type="text" name="children[${idx}][child_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <input type="number" name="children[${idx}][birth_year]" placeholder="Year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-24 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSpouseRow(container) {
                    const idx = container.querySelectorAll('.spouse-row').length;
                    const div = document.createElement('div');
                    div.className = 'spouse-row space-y-2 rounded-lg border border-gray-100 bg-gray-50/50 p-2.5 dark:border-gray-700 dark:bg-white/[0.02]';
                    div.innerHTML = `<input type="text" name="spouses[${idx}][spouse_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm dark:bg-transparent" />
                        <div class="flex gap-2 items-center"><input type="number" name="spouses[${idx}][marriage_start_year]" placeholder="Start year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" /><span class="text-gray-400 shrink-0">&ndash;</span><input type="number" name="spouses[${idx}][marriage_end_year]" placeholder="End year" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm dark:bg-transparent" /><button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addParentRow(container) {
                    const idx = container.querySelectorAll('.parent-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex flex-wrap gap-2 items-center parent-row';
                    div.innerHTML = `<input type="text" name="parents[${idx}][parent_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 basis-40 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <select name="parents[${idx}][relationship_type]" class="h-11 shrink-0 rounded-lg border border-gray-300 dark:border-gray-600 bg-transparent dark:bg-gray-900/80 px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100"><option value="biological">Biological</option><option value="adoptive">Adoptive</option></select>
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addSiblingRow(container) {
                    const idx = container.querySelectorAll('.sibling-row').length;
                    const div = document.createElement('div');
                    div.className = 'flex gap-2 items-center sibling-row';
                    div.innerHTML = `<input type="text" name="siblings[${idx}][sibling_name]" placeholder="Name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" />
                        <button type="button" class="shrink-0 text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                },
                addEducationRow(container) {
                    const idx = container.querySelectorAll('.education-row').length;
                    const div = document.createElement('div');
                    div.className = 'grid grid-cols-1 gap-2 sm:grid-cols-4 items-end education-row';
                    div.innerHTML = `<div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Institution</label><input type="text" name="education[${idx}][institution_name]" placeholder="e.g. School or university name" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">Start year</label><input type="number" name="education[${idx}][start_year]" placeholder="1990" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div><label class="mb-1 block text-xs text-gray-500">End year</label><input type="number" name="education[${idx}][end_year]" placeholder="1994" min="1900" max="2100" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="sm:col-span-2"><label class="mb-1 block text-xs text-gray-500">Degree (optional)</label><input type="text" name="education[${idx}][degree]" placeholder="e.g. Diploma" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm" /></div>
                        <div class="flex items-end"><button type="button" class="text-red-500 hover:text-red-700 p-2" title="Remove"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></div>`;
                    div.querySelector('button').addEventListener('click', () => div.remove());
                    container.insertBefore(div, container.lastElementChild);
                }
            };
        }
    </script>

    <script>
        (function() {
            if (typeof Quill === 'undefined') return;
            const editorEl = document.getElementById('create-biography-editor');
            const hiddenField = document.getElementById('biography-hidden');
            if (!editorEl || !hiddenField) return;

            const q = new Quill('#create-biography-editor', {
                theme: 'snow',
                placeholder: 'Share your memories... (optional)',
                modules: {
                    toolbar: [
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        ['bold', 'italic', 'underline'],
                        [{ 'color': [] }],
                        ['link', 'blockquote'],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        [{ 'align': [] }],
                        ['clean']
                    ]
                }
            });

            const initial = hiddenField.value;
            if (initial && initial.trim()) {
                if (initial.includes('<')) {
                    q.clipboard.dangerouslyPasteHTML(0, initial);
                } else {
                    q.setText(initial);
                }
            }

            q.on('text-change', function() {
                const html = q.root.innerHTML?.trim() ?? '';
                hiddenField.value = (html === '<p><br></p>' || !html) ? '' : html;
            });
        })();
    </script>
@endsection
