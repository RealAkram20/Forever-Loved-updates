@php
    $value = old(str_replace(['[', ']'], ['.', ''], $name), $settings[$dotName] ?? $default);
@endphp

<div x-data="colorPicker('{{ $value }}', '{{ $name }}')"
     @keydown.escape.window="close()"
     @mousemove.window="onWindowMove($event)"
     @mouseup.window="onWindowUp()"
     class="relative">

    <label class="mb-2 block text-xs font-medium text-gray-600 dark:text-gray-400">{{ $label }}</label>

    {{-- Trigger: swatch + hex --}}
    <div class="flex items-center gap-3 cursor-pointer group" @click="toggle()">
        <span class="block h-10 w-10 shrink-0 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm transition group-hover:ring-2 group-hover:ring-brand-400/30"
              :style="'background-color:' + hex"></span>
        <span class="h-10 flex-1 flex items-center rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 text-sm text-gray-600 dark:text-gray-300 transition group-hover:border-brand-400"
              x-text="hex"></span>
    </div>

    {{-- Hidden form input --}}
    <input type="hidden" :name="'{{ $name }}'" x-ref="hiddenInput" :value="hex" data-color-picker-input />

    {{-- Picker popover --}}
    <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         @click.outside="close()"
         class="absolute z-50 mt-2 left-0 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-4"
         style="min-width: 288px;">

        {{-- SV Canvas --}}
        <div class="relative mb-3 rounded-lg overflow-hidden cursor-crosshair select-none" style="height: 160px;"
             @mousedown.prevent="onCanvasDown($event)">
            <canvas x-ref="svCanvas" width="256" height="160" class="w-full h-full block"></canvas>
            <div class="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md"
                 :style="'left:' + cursorX + '%; top:' + cursorY + '%; background:' + hex"></div>
        </div>

        {{-- Hue slider --}}
        <div class="relative mb-4 h-3 rounded-full cursor-pointer select-none"
             style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);"
             x-ref="hueBar"
             @mousedown.prevent="onHueDown($event)">
            <div class="pointer-events-none absolute top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md"
                 :style="'left:' + huePercent + '%; background:' + hex"></div>
        </div>

        {{-- Hex input --}}
        <div class="mb-3 flex items-center gap-2">
            <span class="block h-8 w-8 shrink-0 rounded-md border border-gray-200 dark:border-gray-600" :style="'background:' + hex"></span>
            <input type="text" maxlength="7" :value="hex" @input="onHexInput($event)" @change="onHexInput($event)"
                   class="h-8 flex-1 rounded-md border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2.5 text-xs font-mono text-gray-700 dark:text-gray-200 focus:border-brand-400 focus:ring-1 focus:ring-brand-400/30 focus:outline-hidden" />
        </div>

        {{-- Active colors --}}
        <template x-if="activeColors.length > 0">
            <div class="mb-3">
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Active Colors</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="c in activeColors" :key="c">
                        <button type="button" @click="pickColor(c)"
                                class="h-6 w-6 rounded-full border border-gray-200 dark:border-gray-600 transition hover:scale-110 hover:ring-2 hover:ring-brand-400/40"
                                :style="'background:' + c" :title="c"></button>
                    </template>
                </div>
            </div>
        </template>

        {{-- History --}}
        <template x-if="history.length > 0">
            <div>
                <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Recent</p>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="c in history" :key="c">
                        <button type="button" @click="pickColor(c)"
                                class="h-6 w-6 rounded-full border border-gray-200 dark:border-gray-600 transition hover:scale-110 hover:ring-2 hover:ring-brand-400/40"
                                :style="'background:' + c" :title="c"></button>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
