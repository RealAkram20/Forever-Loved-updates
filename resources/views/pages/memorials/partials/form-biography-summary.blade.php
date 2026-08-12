{{-- Biography summary — the facts the generated opening paragraph is built from. --}}
<x-common.component-card :title="($step ?? null) ? $step.'. Biography Summary' : 'Biography Summary'" desc="For auto-generating the top paragraph">
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
