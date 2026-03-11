@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0" x-data="{ doProfileLater: false, doDatesLater: false }">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-6 pt-10 lg:px-12">
                <x-memorial-signup.step-tabs :currentStep="1" />
                <a href="{{ route('home') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back to home
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 1 of 3</span>
                        <span class="text-sm text-gray-500">Deceased details</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">This memorial is dedicated to</h1>
                    <p class="mb-6 text-sm text-gray-500">Share information about your loved one. You can update this later.</p>

                    @if (session('error'))
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('memorial.create.storeStep1') }}" class="space-y-6">
                        @csrf
                        <div class="space-y-5">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="first_name">First name</label>
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $data['first_name'] ?? '') }}" required
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="middle_name">Middle name</label>
                                    <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $data['middle_name'] ?? '') }}"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="last_name">Last name</label>
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $data['last_name'] ?? '') }}" required
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700">Gender</label>
                                <div class="flex flex-wrap items-center gap-6">
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                        <input type="radio" name="gender" value="male" {{ old('gender', $data['gender'] ?? '') === 'male' ? 'checked' : '' }}
                                            class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                        <span class="ml-2">Male</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none">
                                        <input type="radio" name="gender" value="female" {{ old('gender', $data['gender'] ?? '') === 'female' ? 'checked' : '' }}
                                            class="border-gray-300 text-brand-600 focus:ring-brand-500" />
                                        <span class="ml-2">Female</span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="relationship">Relationship</label>
                                @php $relVal = old('relationship', $data['relationship'] ?? ''); @endphp
                                <select id="relationship" name="relationship" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
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
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-gray-600">Dates, location & designation:</p>
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 select-none">
                                    <div class="relative">
                                        <input type="checkbox" class="sr-only" x-model="doDatesLater" />
                                        <div class="block h-6 w-11 rounded-full" :class="doDatesLater ? 'bg-brand-500' : 'bg-gray-200'"></div>
                                        <div :class="doDatesLater ? 'translate-x-full' : 'translate-x-0'" class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"></div>
                                    </div>
                                    <span>Do this later</span>
                                </label>
                            </div>
                            <div class="space-y-5" x-show="!doDatesLater" x-collapse>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Born</label>
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
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Passed away</label>
                                    <x-form.date-picker id="date_of_passing" name="date_of_passing" placeholder="Select date"
                                        :defaultDate="old('date_of_passing', $data['date_of_passing'] ?? null)" />
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                    <div>
                                        <x-form.country-select id="step1_death_country" name="death_country" label="Country"
                                            :value="old('death_country', $data['death_country'] ?? '')" :autoDetect="true" />
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
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="cause_of_death">Designation</label>
                                    @php $causeVal = old('cause_of_death', $data['cause_of_death'] ?? ''); @endphp
                                    <select id="cause_of_death" name="cause_of_death" class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
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
                                            class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                        <span class="text-sm text-gray-700">Keep designation private</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Do it later #2: Profile enrichment --}}
                        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-gray-600">Help us generate a richer memorial profile:</p>
                                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 select-none">
                                    <div class="relative">
                                        <input type="checkbox" class="sr-only" x-model="doProfileLater" />
                                        <div class="block h-6 w-11 rounded-full" :class="doProfileLater ? 'bg-brand-500' : 'bg-gray-200'"></div>
                                        <div :class="doProfileLater ? 'translate-x-full' : 'translate-x-0'" class="shadow-theme-sm absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white duration-300 ease-linear"></div>
                                    </div>
                                    <span>Do this later</span>
                                </label>
                            </div>
                            <div class="space-y-5" x-show="!doProfileLater" x-collapse>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="short_description">Short description</label>
                                    <input type="text" id="short_description" name="short_description" value="{{ old('short_description', $data['short_description'] ?? '') }}"
                                        placeholder="e.g. American businessman, co-inventor, investor"
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                </div>
                                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div x-data="{ nationalityVal: '{{ old('nationality', $data['nationality'] ?? '') }}' }" @nationality-detected.window="if ($event.detail.source === 'step1_birth_country') nationalityVal = $event.detail.nationality">
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="nationality">Nationality</label>
                                        <input type="text" id="nationality" name="nationality" x-model="nationalityVal"
                                            placeholder="Auto-filled from birth country"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700" for="primary_profession">Primary profession</label>
                                        <input type="text" id="primary_profession" name="primary_profession" value="{{ old('primary_profession', $data['primary_profession'] ?? '') }}"
                                            placeholder="e.g. Entrepreneur"
                                            class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" />
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="major_achievements">Major achievements</label>
                                    <textarea id="major_achievements" name="major_achievements" rows="3" placeholder="e.g. Co-founded Apple Inc. with Steve Wozniak in 1976..."
                                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden">{{ old('major_achievements', $data['major_achievements'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
