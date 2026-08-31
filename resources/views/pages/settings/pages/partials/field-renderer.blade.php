{{-- Text input.

     `field.max` comes from the widget's own limit declaration (App\PageBuilder\Support\TextLimits),
     the same number the validator enforces. maxlength stops the overrun as it happens; the
     counter below explains why the field stopped accepting characters, which maxlength alone
     never does. --}}
<template x-if="field.kind === 'text'">
    <div>
        <input type="text" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-600 dark:text-white"
            :value="selectedWidget.props[field.name]" @input="updateProp(field.name, $event.target.value)"
            :maxlength="field.max || null"
            :placeholder="field.placeholder || ''" />
        @include('pages.settings.pages.partials.field-counter')
    </div>
</template>

{{-- Textarea --}}
<template x-if="field.kind === 'textarea'">
    <div>
        <textarea :rows="field.rows || 3" class="w-full rounded-lg border border-gray-300 bg-transparent px-2 py-2 text-sm dark:border-gray-600 dark:text-white"
            :value="selectedWidget.props[field.name]" @input="updateProp(field.name, $event.target.value)"
            :maxlength="field.max || null"
            :placeholder="field.placeholder || ''"></textarea>
        @include('pages.settings.pages.partials.field-counter')
    </div>
</template>

{{-- Number --}}
<template x-if="field.kind === 'number'">
    <input type="number" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-600 dark:text-white"
        :value="selectedWidget.props[field.name]" @input="updateProp(field.name, parseFloat($event.target.value) || 0)" />
</template>

{{-- Checkbox --}}
<template x-if="field.kind === 'checkbox'">
    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600"
            :checked="!!selectedWidget.props[field.name]" @change="updateProp(field.name, $event.target.checked)" />
        Enable
    </label>
</template>

{{-- Select --}}
<template x-if="field.kind === 'select'">
    <select class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 text-sm dark:border-gray-600 dark:text-white"
        :value="selectedWidget.props[field.name]"
        @change="updateProp(field.name, field.cast === 'int' ? parseInt($event.target.value, 10) : $event.target.value)">
        <template x-for="opt in field.options" :key="String(opt)">
            <option :value="opt" x-text="opt === '' ? 'Default' : (field.prefix ? field.prefix + opt : String(opt))"></option>
        </template>
    </select>
</template>

{{-- Repeater: a list of rows, each its own small form. --}}
<template x-if="field.kind === 'repeater'">
    @include('pages.settings.pages.partials.field-repeater')
</template>

{{-- JSON --}}
<template x-if="field.kind === 'json'">
    <div>
        <textarea rows="6" class="w-full rounded-lg border bg-transparent px-2 py-2 font-mono text-xs dark:text-white"
            :class="jsonError(field.name) ? 'border-red-400 focus:border-red-400 focus:ring-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-600'"
            :value="jsonProp(field.name)"
            @input.debounce.400ms="setJsonProp(field.name, $event.target.value)"></textarea>
        <p x-show="jsonError(field.name)" x-cloak class="mt-1 text-xs text-red-500" x-text="jsonError(field.name)"></p>
    </div>
</template>

{{-- Rich text (Quill) --}}
<template x-if="field.kind === 'richtext'">
    <div>
        <div :id="'pb-quill-' + selectedWidget.id + '-' + field.name"
             class="min-h-[180px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"
             x-init="$nextTick(() => initQuill(selectedWidget, field.name))"
             x-effect="destroyQuillOnDeselect(selectedWidget, field.name)"></div>
    </div>
</template>

{{-- Alignment (icon buttons) --}}
<template x-if="field.kind === 'alignment'">
    <div class="flex gap-1">
        <template x-for="opt in [{v:'left',d:'M4 6h16M4 12h10M4 18h14'},{v:'center',d:'M4 6h16M7 12h10M5 18h14'},{v:'right',d:'M4 6h16M10 12h10M6 18h14'},{v:'justify',d:'M4 6h16M4 12h16M4 18h16'}]" :key="opt.v">
            <button type="button" @click="updateProp(field.name, opt.v)"
                class="flex h-9 w-9 items-center justify-center rounded-lg border transition"
                :class="selectedWidget.props[field.name] === opt.v
                    ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400'
                    : 'border-gray-300 dark:border-gray-600 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="opt.d"/></svg>
            </button>
        </template>
    </div>
</template>

{{-- Color picker --}}
<template x-if="field.kind === 'color'">
    <div class="flex items-center gap-2">
        <label class="relative shrink-0 cursor-pointer">
            <span class="block h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden"
                :style="'background:' + (selectedWidget.props[field.name] || '#000000')"></span>
            <input type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                :value="selectedWidget.props[field.name] || '#000000'"
                @input="updateProp(field.name, $event.target.value)" />
        </label>
        <input type="text" class="h-9 flex-1 min-w-0 rounded-lg border border-gray-300 bg-transparent px-2 text-sm font-mono dark:border-gray-600 dark:text-white"
            :value="selectedWidget.props[field.name]"
            @input="updateProp(field.name, $event.target.value)"
            placeholder="#hex or CSS color" />
        <button type="button" x-show="selectedWidget.props[field.name]" @click="updateProp(field.name, '')"
            class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-500 transition" title="Clear">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</template>

{{-- Size + unit (e.g. font size) --}}
<template x-if="field.kind === 'size_unit'">
    <div class="flex items-center gap-1.5">
        <input type="text" inputmode="numeric"
            class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-sm text-center dark:border-gray-600 dark:text-white"
            :value="selectedWidget.props[field.name]"
            @input="updateProp(field.name, $event.target.value)"
            placeholder="—" />
        <div class="flex gap-0.5">
            <template x-for="u in (field.units || ['px','em','rem','%'])" :key="u">
                <button type="button" @click="updateProp(field.unit_prop || (field.name + '_unit'), u)"
                    class="px-1.5 py-1 text-[10px] font-semibold uppercase rounded transition"
                    :class="selectedWidget.props[field.unit_prop || (field.name + '_unit')] === u ? 'bg-brand-500 text-white' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                    x-text="u"></button>
            </template>
        </div>
    </div>
</template>

{{-- Every kind above is guarded by its own `x-if`, and the repeater is no exception — it is
     included at `field.kind === 'repeater'` near the top of this file. An unguarded include
     used to sit here as well, which drew a second repeater under *every* field of every
     widget: a stray "Add item" list after each text box, and the real one rendered twice. --}}
