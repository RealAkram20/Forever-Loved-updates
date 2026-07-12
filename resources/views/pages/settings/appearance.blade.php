@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Appearance" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following errors:</p>
            <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $customNames = array_column($customFonts, 'name');
        $inputClass = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 dark:border-gray-700 dark:bg-gray-900/80 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden';
    @endphp

    <div class="space-y-6">
        <form action="{{ route('settings.appearance.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Fonts --}}
            <x-common.component-card title="Fonts" desc="Applied across the whole site — visitor pages and the dashboard. Leave on the theme default (Outfit) to change nothing.">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @foreach ([['key' => 'font_body', 'label' => 'Body font', 'hint' => 'Paragraphs, buttons, forms — everything that is not a heading.'],
                               ['key' => 'font_heading', 'label' => 'Heading font', 'hint' => 'All page and section headings (h1–h6).']] as $picker)
                        @php $current = old('appearance.'.$picker['key'], $settings['appearance.'.$picker['key']] ?? ''); @endphp
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="{{ $picker['key'] }}">{{ $picker['label'] }}</label>
                            <select id="{{ $picker['key'] }}" name="appearance[{{ $picker['key'] }}]" data-font-select class="{{ $inputClass }}">
                                <option value="">Theme default (Outfit)</option>
                                @if (count($customNames))
                                    <optgroup label="Uploaded fonts">
                                        @foreach ($customNames as $name)
                                            <option value="{{ $name }}" data-custom @selected($current === $name)>{{ $name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @foreach ($googleFonts as $category => $families)
                                    <optgroup label="Google Fonts — {{ $category }}">
                                        @foreach ($families as $family)
                                            <option value="{{ $family }}" @selected($current === $family)>{{ $family }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $picker['hint'] }}</p>
                            <p data-font-preview class="mt-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/60 dark:bg-white/[0.02] px-4 py-3 text-base text-gray-800 dark:text-white/90">
                                In loving memory — every life tells a story. AaBbCc 0123456789
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-common.component-card>

            {{-- Text colors --}}
            <x-common.component-card title="Text colors" desc="Applied to the visitor-facing site (home, memorial pages, signup). Leave a color empty to keep the theme default.">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @foreach ([['role' => 'heading', 'label' => 'Headings', 'hint' => 'Titles and section headings.'],
                               ['role' => 'body', 'label' => 'Body text', 'hint' => 'Paragraphs and standard text.'],
                               ['role' => 'muted', 'label' => 'Secondary text', 'hint' => 'Captions, timestamps, helper text.']] as $group)
                        <div class="space-y-4">
                            <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $group['label'] }}</p>
                            @foreach (['light' => 'Light mode', 'dark' => 'Dark mode'] as $mode => $modeLabel)
                                @php
                                    $key = "appearance.text_{$group['role']}_{$mode}";
                                    $value = old($key, $settings[$key] ?? '');
                                @endphp
                                <div x-data="{ hex: '{{ $value }}' }">
                                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400" for="{{ $key }}">{{ $modeLabel }}</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" :value="hex || '#888888'" @input="hex = $event.target.value"
                                            class="h-11 w-12 shrink-0 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent p-1"
                                            aria-label="{{ $group['label'] }} — {{ $modeLabel }} color picker" />
                                        <input type="text" id="{{ $key }}" name="appearance[text_{{ $group['role'] }}_{{ $mode }}]"
                                            x-model="hex" placeholder="Theme default" maxlength="7"
                                            class="{{ $inputClass }}" />
                                        <button type="button" x-show="hex" @click="hex = ''"
                                            class="shrink-0 rounded-lg p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            aria-label="Clear {{ $group['label'] }} {{ $modeLabel }} color">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $group['hint'] }}</p>
                        </div>
                    @endforeach
                </div>
            </x-common.component-card>

            <div>
                <button type="submit" class="btn btn-primary btn-md">Save appearance</button>
            </div>
        </form>

        {{-- Uploaded fonts --}}
        <x-common.component-card title="Uploaded fonts" desc="Upload your own font files (.woff2, .woff, .ttf or .otf — .woff2 is smallest and loads fastest). Uploaded fonts appear in the font pickers above.">
            <form action="{{ route('settings.appearance.fonts.store') }}" method="POST" enctype="multipart/form-data"
                class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="font_name">Font name</label>
                    <input type="text" id="font_name" name="font_name" value="{{ old('font_name') }}"
                        placeholder="e.g. Brand Sans" class="{{ $inputClass }}" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="font_file">Font file</label>
                    <input type="file" id="font_file" name="font_file" accept=".woff2,.woff,.ttf,.otf"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 file:mr-4 file:rounded-md file:border-0 file:bg-brand-500 file:px-4 file:py-1.5 file:text-sm file:text-white hover:file:bg-brand-600" />
                </div>
                <button type="submit" class="btn btn-primary btn-md">Upload font</button>
            </form>

            @if (count($customFonts))
                <div class="mt-6 divide-y divide-gray-100 dark:divide-gray-800 rounded-lg border border-gray-100 dark:border-gray-800">
                    @foreach ($customFonts as $i => $font)
                        <div class="flex flex-wrap items-center gap-4 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $font['name'] }}
                                    <span class="ml-2 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] uppercase text-gray-500 dark:text-gray-400">{{ $font['format'] }}</span>
                                </p>
                                <p class="mt-1 truncate text-lg text-gray-700 dark:text-gray-300" style="font-family: '{{ $font['name'] }}', sans-serif;">
                                    In loving memory — AaBbCc 0123456789
                                </p>
                            </div>
                            <form action="{{ route('settings.appearance.fonts.destroy', $i) }}" method="POST"
                                onsubmit="return confirm('Delete the font “{{ $font['name'] }}”? Pages using it fall back to the theme default.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg p-2 text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20" aria-label="Delete font {{ $font['name'] }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">No fonts uploaded yet.</p>
            @endif
        </x-common.component-card>
    </div>

    <script>
        // Live preview: load the picked Google family on demand and render the
        // sample line in it. Uploaded fonts are already available via the
        // site-wide @font-face rules.
        document.querySelectorAll('[data-font-select]').forEach((select) => {
            const preview = select.closest('div').querySelector('[data-font-preview]');
            const loaded = new Set();
            const apply = () => {
                const family = select.value;
                if (!family) {
                    preview.style.fontFamily = '';
                    return;
                }
                const isCustom = !!select.selectedOptions[0]?.hasAttribute('data-custom');
                if (!isCustom && !loaded.has(family)) {
                    loaded.add(family);
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://fonts.googleapis.com/css?family=' + encodeURIComponent(family).replace(/%20/g, '+') + ':400,600&display=swap';
                    document.head.appendChild(link);
                }
                preview.style.fontFamily = "'" + family + "', sans-serif";
            };
            select.addEventListener('change', apply);
            apply();
        });
    </script>
@endsection
