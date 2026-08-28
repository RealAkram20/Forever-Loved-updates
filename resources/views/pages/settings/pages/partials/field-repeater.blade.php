{{--
    A repeatable list of rows — the cards in a services grid, the points in a "why choose us".

    Each row is a small form of its own, built from the widget's `item_fields`. Rows can be
    reordered and removed, and the add button disappears at the cap rather than failing on
    click: a control that is present and refuses is worse than one that is visibly spent.

    Deliberately not collapsible. Six cards with three fields each is a long panel, but every
    alternative — accordions, a modal per row — hides the thing being edited from the preview
    beside it, and watching the page change as you type is the only reason to edit here rather
    than in a spreadsheet.
--}}
<div class="space-y-2.5">
    <template x-for="(row, rowIndex) in repeaterRows(field)" :key="field.name + '-' + rowIndex">
        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-white/[0.03]">
            {{-- Row header: what this row is, and what can be done to it. --}}
            <div class="mb-2.5 flex items-center gap-1.5">
                <span class="min-w-0 flex-1 truncate text-[11px] font-semibold text-gray-600 dark:text-gray-300"
                    x-text="repeaterRowLabel(field, row, rowIndex)"></span>

                <button type="button" @click="repeaterMove(field, rowIndex, -1)" :disabled="rowIndex === 0"
                    class="rounded p-1 text-gray-400 transition-colors duration-150 ease-out hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:text-gray-200"
                    title="Move up" aria-label="Move up">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                </button>

                <button type="button" @click="repeaterMove(field, rowIndex, 1)"
                    :disabled="rowIndex === repeaterRows(field).length - 1"
                    class="rounded p-1 text-gray-400 transition-colors duration-150 ease-out hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:text-gray-200"
                    title="Move down" aria-label="Move down">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>

                <button type="button" @click="repeaterRemove(field, rowIndex)"
                    class="rounded p-1 text-gray-400 transition-colors duration-150 ease-out hover:text-red-500"
                    title="Remove" aria-label="Remove">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- The row's own fields. --}}
            <div class="space-y-2.5">
                <template x-for="itemField in (field.item_fields || [])" :key="itemField.name">
                    <div>
                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="itemField.label"></label>

                        <template x-if="itemField.kind === 'text'">
                            <div>
                                <input type="text"
                                    class="h-8 w-full rounded-md border border-gray-300 bg-white px-2 text-[13px] dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                    :value="row[itemField.name] ?? ''"
                                    :maxlength="itemField.max || null"
                                    :placeholder="itemField.placeholder || ''"
                                    @input="repeaterSet(field, rowIndex, itemField.name, $event.target.value)" />
                                @include('pages.settings.pages.partials.field-repeater-counter')
                            </div>
                        </template>

                        <template x-if="itemField.kind === 'textarea'">
                            <div>
                                <textarea rows="2"
                                    class="w-full rounded-md border border-gray-300 bg-white px-2 py-1.5 text-[13px] dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                    :value="row[itemField.name] ?? ''"
                                    :maxlength="itemField.max || null"
                                    :placeholder="itemField.placeholder || ''"
                                    @input="repeaterSet(field, rowIndex, itemField.name, $event.target.value)"></textarea>
                                @include('pages.settings.pages.partials.field-repeater-counter')
                            </div>
                        </template>

                        <template x-if="itemField.kind === 'select'">
                            <select class="h-8 w-full rounded-md border border-gray-300 bg-white px-2 text-[13px] dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                :value="row[itemField.name] ?? ''"
                                @change="repeaterSet(field, rowIndex, itemField.name, $event.target.value)">
                                <template x-for="opt in (itemField.options || [])" :key="String(opt)">
                                    <option :value="opt" x-text="opt === '' ? '— none —' : String(opt)"
                                        :selected="(row[itemField.name] ?? '') === opt"></option>
                                </template>
                            </select>
                        </template>

                        <template x-if="itemField.kind === 'checkbox'">
                            <label class="flex items-center gap-2 text-[13px] text-gray-700 dark:text-gray-300">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600"
                                    :checked="!!row[itemField.name]"
                                    @change="repeaterSet(field, rowIndex, itemField.name, $event.target.checked)" />
                                Enable
                            </label>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- Empty state. A list with no rows and no explanation reads as a broken panel. --}}
    <template x-if="repeaterRows(field).length === 0">
        <p class="rounded-lg border border-dashed border-gray-300 px-3 py-4 text-center text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Nothing here yet. Add your first <span x-text="field.item_label || 'item'"></span>.
        </p>
    </template>

    <div class="flex items-center justify-between gap-2">
        <template x-if="repeaterCanAdd(field)">
            <button type="button" @click="repeaterAdd(field)"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-[11px] font-semibold text-gray-700 transition-colors duration-150 ease-out hover:border-brand-400 hover:text-brand-600 dark:border-gray-600 dark:text-gray-300">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Add <span x-text="field.item_label || 'item'"></span>
            </button>
        </template>

        {{-- Said only at the cap, where it explains why the button has gone. --}}
        <template x-if="!repeaterCanAdd(field)">
            <p class="text-[10px] text-gray-400 dark:text-gray-500">
                That is the most this section holds (<span x-text="field.max_items || 12"></span>).
            </p>
        </template>

        <p class="text-[10px] tabular-nums text-gray-400 dark:text-gray-500">
            <span x-text="repeaterRows(field).length"></span> of <span x-text="field.max_items || 12"></span>
        </p>
    </div>
</div>
