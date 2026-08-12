{{--
    Identity — the core facts the opening sentence is generated from.

    Shared by /memorials/create and the reseller's intake screen, so a memorial taken
    in by a funeral home asks for exactly what a family would enter itself. Pass $step
    to number the card; omit it on pages whose cards aren't a numbered sequence.
--}}
<x-common.component-card :title="($step ?? null) ? $step.'. Identity' : 'Identity'" desc="Core information that generates the opening sentence">
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
