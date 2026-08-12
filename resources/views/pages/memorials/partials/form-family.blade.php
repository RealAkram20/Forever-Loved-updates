{{-- Family. Four repeaters, all optional — rows are added by the shared Alpine factory. --}}
<x-common.component-card :title="($step ?? null) ? $step.'. Family Relationships' : 'Family Relationships'" desc="Optional, can be added later">
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
