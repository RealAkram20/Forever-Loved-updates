{{-- Education repeater. --}}
<x-common.component-card :title="($step ?? null) ? $step.'. Education' : 'Education'" desc="Optional">
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
