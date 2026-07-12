@extends('layouts.app')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@endpush

@section('content')
    @php
        $isCreateMode = $isCreateMode ?? false;
        $cmsPathPrefix = '';
        if ($isCreateMode) {
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => true,
                'storeUrl' => route('settings.pages.store'),
                'previewUrl' => route('settings.pages.preview'),
                'slugEditable' => true,
                'originalSlug' => '',
                'cmsPathPrefix' => $cmsPathPrefix,
                'publicUrlBase' => rtrim(config('app.url'), '/') . $cmsPathPrefix . '/',
                'seo' => [
                    'slug' => old('slug', ''),
                    'title' => old('title', ''),
                    'meta_title' => old('meta_title', ''),
                    'meta_description' => old('meta_description', ''),
                    'is_published' => (bool) old('is_published', true),
                    'og_image_url' => null,
                ],
            ];
        } else {
            $ogPublicUrl = null;
            if (is_string($page->og_image) && $page->og_image !== '') {
                $ogPublicUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image);
            }
            $pageBuilderPayload = [
                'definitions' => $widgetDefinitions,
                'initial' => $initialDocument,
                'createMode' => false,
                'saveUrl' => route('settings.pages.layout.update', $page->slug),
                'previewUrl' => route('settings.pages.preview'),
                'seoUrl' => route('settings.pages.meta.update', $page->slug),
                'slugEditable' => ! $page->isSystemLayoutPage(),
                'originalSlug' => $page->slug,
                'cmsPathPrefix' => $cmsPathPrefix,
                'publicUrl' => $page->publicUrl(),
                'seo' => [
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'meta_title' => $page->meta_title ?? '',
                    'meta_description' => $page->meta_description ?? '',
                    'is_published' => (bool) $page->is_published,
                    'og_image_url' => $ogPublicUrl,
                ],
            ];
        }
    @endphp
    <script>
        window.__PAGE_BUILDER__ = @json($pageBuilderPayload);
    </script>
    <div x-data="pageBuilder()">
        <x-common.page-breadcrumb :pageTitle="$layoutHeading" />

        @if (session('success'))
            <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
                <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-1">Please fix the following:</p>
                <ul class="list-disc list-inside text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('settings.pages.index') }}"
                    class="btn btn-secondary btn-md">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Pages
                </a>
                <div class="min-w-0 flex-1 max-w-md">
                    <input type="text" x-model="seo.title"
                        placeholder="Add page title"
                        class="w-full border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 dark:text-white dark:placeholder-gray-500 truncate" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate" x-text="pageUrl"></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (! $isCreateMode)
                    <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener"
                        class="btn btn-secondary btn-md">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        View live
                    </a>
                @endif
                <button type="button" @click="seoOpen = true"
                    class="btn btn-secondary btn-md">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z"/><circle cx="12" cy="12" r="3"/></svg>
                    Page details
                </button>
                <span x-show="dirty && !saving" x-cloak class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    Unsaved changes
                </span>
                <button type="button" @click="save()"
                    :disabled="saving"
                    class="btn btn-primary btn-md disabled:opacity-50">
                    <span x-show="!saving" x-text="createMode ? 'Create page' : 'Save layout'"></span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-12" @keydown.escape.window="selectedId = null; seoOpen = false; navOpen = false">

            {{-- SIDE PANEL: Elements ⇄ Edit widget (Elementor-style single panel) --}}
            <aside class="lg:col-span-3 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 flex flex-col overflow-hidden lg:h-[calc(100vh-230px)] lg:min-h-[560px]">

                {{-- ELEMENTS MODE --}}
                <template x-if="!selectedWidget">
                    <div class="flex min-h-0 flex-1 flex-col">
                        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Elements</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Click a widget to add it to the page, then click it in the preview to edit.</p>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3">
                            @php
                                $byCat = collect($widgetDefinitions)->groupBy('category');
                            @endphp
                            @foreach ($byCat as $category => $defs)
                                <p class="mb-2 mt-3 first:mt-0 text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ $category }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($defs as $def)
                                        <button type="button" @click="addWidget('{{ $def['type'] }}')"
                                            draggable="true"
                                            @dragstart="onPaletteDragStart($event, '{{ $def['type'] }}')"
                                            class="flex cursor-grab active:cursor-grabbing flex-col items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2 py-3 text-center text-xs font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50/50 hover:text-brand-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-brand-500/50 dark:hover:bg-brand-500/10 dark:hover:text-brand-300">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                                            {{ $def['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </template>

                {{-- EDIT MODE --}}
                <template x-if="selectedWidget && selectedDefinition">
                    <div class="flex flex-col min-h-0 flex-1" x-data="{ propTab: 'content' }">
                        <div class="border-b border-gray-200 dark:border-gray-700 px-3 pt-3 pb-0">
                            <div class="mb-3 flex items-center gap-2">
                                <button type="button" @click="selectWidget(null)"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                                    title="Back to elements" aria-label="Back to elements">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    Edit <span x-text="selectedDefinition.label"></span>
                                </span>
                                <button type="button" @click="removeWidget(selectedId)"
                                    class="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                    title="Remove widget" aria-label="Remove widget">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            {{-- Tabs --}}
                            <div class="flex">
                                <template x-for="tab in [{id:'content',icon:'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'},{id:'style',icon:'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01'},{id:'advanced',icon:'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z'}]" :key="tab.id">
                                    <button type="button" @click="propTab = tab.id"
                                        class="flex-1 flex flex-col items-center gap-1 px-2 pb-2 pt-1 text-[11px] font-medium border-b-2 transition"
                                        :class="propTab === tab.id
                                            ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                                            : 'border-transparent text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" /><template x-if="tab.id === 'advanced'"><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/></template></svg>
                                        <span x-text="tab.id.charAt(0).toUpperCase() + tab.id.slice(1)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        {{-- Tab panels --}}
                        <div class="flex-1 overflow-y-auto p-4">

                            {{-- CONTENT TAB --}}
                            <div x-show="propTab === 'content'" class="space-y-4">
                                <template x-for="field in selectedDefinition.fields.filter(f => (f.tab || 'content') === 'content')" :key="selectedWidget.id + '-' + field.name">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" x-text="field.label"></label>
                                        @include('pages.settings.pages.partials.field-renderer')
                                    </div>
                                </template>
                            </div>

                            {{-- STYLE TAB --}}
                            <div x-show="propTab === 'style'" x-cloak class="space-y-4">
                                <template x-for="field in selectedDefinition.fields.filter(f => f.tab === 'style')" :key="selectedWidget.id + '-style-' + field.name">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" x-text="field.label"></label>
                                        @include('pages.settings.pages.partials.field-renderer')
                                    </div>
                                </template>

                                {{-- Universal section styles (every widget) --}}
                                <div class="space-y-4" :class="selectedDefinition.fields.filter(f => f.tab === 'style').length > 0 ? 'border-t border-gray-200 dark:border-gray-700 pt-4' : ''">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Section</p>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Background color</label>
                                        <div class="flex items-center gap-2">
                                            <label class="relative shrink-0 cursor-pointer">
                                                <span class="block h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden"
                                                    :style="'background:' + (styleVal('background_color') || 'transparent')"></span>
                                                <input type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                    :value="styleVal('background_color') || '#ffffff'"
                                                    @input="setStyleVal('background_color', $event.target.value)" />
                                            </label>
                                            <input type="text" class="h-9 flex-1 min-w-0 rounded-lg border border-gray-300 bg-transparent px-2 text-sm font-mono dark:border-gray-600 dark:text-white"
                                                :value="styleVal('background_color')"
                                                @input="setStyleVal('background_color', $event.target.value)"
                                                placeholder="Default" />
                                            <button type="button" x-show="styleVal('background_color')" @click="setStyleVal('background_color', '')"
                                                class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-500 transition" title="Clear" aria-label="Clear background color">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Text color</label>
                                        <div class="flex items-center gap-2">
                                            <label class="relative shrink-0 cursor-pointer">
                                                <span class="block h-9 w-9 rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden"
                                                    :style="'background:' + (styleVal('text_color') || 'transparent')"></span>
                                                <input type="color" class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                                    :value="styleVal('text_color') || '#000000'"
                                                    @input="setStyleVal('text_color', $event.target.value)" />
                                            </label>
                                            <input type="text" class="h-9 flex-1 min-w-0 rounded-lg border border-gray-300 bg-transparent px-2 text-sm font-mono dark:border-gray-600 dark:text-white"
                                                :value="styleVal('text_color')"
                                                @input="setStyleVal('text_color', $event.target.value)"
                                                placeholder="Default" />
                                            <button type="button" x-show="styleVal('text_color')" @click="setStyleVal('text_color', '')"
                                                class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-500 transition" title="Clear" aria-label="Clear text color">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Text alignment</label>
                                        <div class="flex gap-1">
                                            <template x-for="opt in [{v:'',d:'M4 6h16M4 12h16M4 18h16',t:'Default'},{v:'left',d:'M4 6h16M4 12h10M4 18h14',t:'Left'},{v:'center',d:'M4 6h16M7 12h10M5 18h14',t:'Center'},{v:'right',d:'M4 6h16M10 12h10M6 18h14',t:'Right'}]" :key="opt.v">
                                                <button type="button" @click="setStyleVal('text_align', opt.v)"
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg border transition"
                                                    :title="opt.t" :aria-label="'Align text: ' + opt.t"
                                                    :class="styleVal('text_align') === opt.v
                                                        ? 'border-brand-500 bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400'
                                                        : 'border-gray-300 dark:border-gray-600 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="opt.d"/></svg>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- ADVANCED TAB --}}
                            <div x-show="propTab === 'advanced'" x-cloak class="space-y-5">
                                <template x-for="group in ['margin', 'padding']" :key="group">
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" x-text="group"></span>
                                            <div class="flex gap-0.5">
                                                <template x-for="u in ['px','em','%','rem']" :key="u">
                                                    <button type="button" @click="setSpacingUnit(group, u)"
                                                        class="px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded transition"
                                                        :class="spacingUnit(group) === u ? 'bg-brand-500 text-white' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                                        x-text="u"></button>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <template x-for="side in ['top','right','bottom','left']" :key="side">
                                                <div class="flex-1 min-w-0">
                                                    <input type="text" inputmode="numeric"
                                                        class="h-8 w-full rounded border border-gray-300 dark:border-gray-600 bg-transparent px-1 text-center text-xs dark:text-white focus:border-brand-400 focus:ring-1 focus:ring-brand-400"
                                                        :value="spacingVal(group, side)"
                                                        @input="setSpacingVal(group, side, $event.target.value)"
                                                        :placeholder="side.charAt(0).toUpperCase()" />
                                                    <p class="mt-0.5 text-center text-[9px] uppercase text-gray-400" x-text="side"></p>
                                                </div>
                                            </template>
                                            <button type="button" @click="toggleSpacingLinked(group)"
                                                class="shrink-0 rounded-lg p-1.5 transition"
                                                :class="spacingLinked(group) ? 'text-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                                :title="spacingLinked(group) ? 'Values linked' : 'Values independent'">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                     x-show="spacingLinked(group)">
                                                    <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/>
                                                    <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
                                                </svg>
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                     x-show="!spacingLinked(group)" x-cloak>
                                                    <path d="M15 7h3a5 5 0 015 5 5 5 0 01-5 5h-3m-6 0H6a5 5 0 01-5-5 5 5 0 015-5h3"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <div class="border-t border-gray-200 pt-4 dark:border-gray-700 space-y-4">
                                    <label class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Hide on live site</span>
                                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-500"
                                            :checked="!!advVal('hidden')"
                                            @change="setAdvVal('hidden', $event.target.checked)" />
                                    </label>
                                    <p class="-mt-2 text-[11px] text-gray-400 dark:text-gray-500" x-show="advVal('hidden')" x-cloak>Visitors won't see this section. It stays visible (faded) in the preview.</p>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">CSS ID</label>
                                        <input type="text" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 font-mono text-sm dark:border-gray-600 dark:text-white"
                                            :value="advVal('css_id')"
                                            @input="setAdvVal('css_id', $event.target.value)"
                                            placeholder="e.g. our-story" />
                                        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">Lets links jump to this section: <span class="font-mono">/about#our-story</span></p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">CSS classes</label>
                                        <input type="text" class="h-9 w-full rounded-lg border border-gray-300 bg-transparent px-2 font-mono text-sm dark:border-gray-600 dark:text-white"
                                            :value="advVal('css_class')"
                                            @input="setAdvVal('css_class', $event.target.value)"
                                            placeholder="e.g. my-class another-class" />
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </template>
            </aside>

            {{-- LIVE PREVIEW --}}
            <div class="lg:col-span-9 relative rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 flex flex-col overflow-hidden lg:h-[calc(100vh-230px)] lg:min-h-[560px]">
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Live preview</p>
                        <svg x-show="previewLoading" x-cloak class="h-3.5 w-3.5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24" aria-label="Refreshing preview">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="flex items-center gap-1" role="group" aria-label="Preview device width">
                            <template x-for="d in [
                                {id:'desktop', label:'Desktop', icon:'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'},
                                {id:'tablet', label:'Tablet', icon:'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'},
                                {id:'mobile', label:'Mobile', icon:'M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'}
                            ]" :key="d.id">
                                <button type="button" @click="previewDevice = d.id"
                                    class="rounded-lg p-1.5 transition"
                                    :class="previewDevice === d.id ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                    :title="d.label" :aria-label="'Preview at ' + d.label + ' width'">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="d.icon"/></svg>
                                </button>
                            </template>
                        </div>
                        <span class="mx-1 h-4 w-px bg-gray-200 dark:bg-gray-700"></span>
                        <button type="button" @click="navOpen = !navOpen"
                            class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium transition"
                            :class="navOpen ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                            :aria-expanded="navOpen" aria-label="Toggle structure panel">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            Structure
                            <span class="rounded-full bg-gray-100 px-1.5 text-[10px] text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="widgets.length"></span>
                        </button>
                    </div>
                </div>
                <div class="flex flex-1 justify-center overflow-auto bg-gray-100 dark:bg-gray-950">
                    <iframe x-ref="previewFrame" title="Page preview"
                        class="h-full min-h-[480px] border-0 bg-white shadow-sm transition-[width] duration-200"
                        :style="previewFrameStyle"></iframe>
                </div>

                {{-- Floating Structure panel (Elementor navigator) --}}
                <div x-show="navOpen" x-cloak
                    class="absolute right-3 top-12 z-20 flex max-h-[70%] w-72 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-3 py-2 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Structure</p>
                        <button type="button" @click="navOpen = false"
                            class="rounded-lg p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close structure panel">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="px-3 pt-2 text-[11px] text-gray-400 dark:text-gray-500">Drag to reorder. Click to edit.</p>
                    <div x-ref="widgetCanvas" class="flex flex-1 flex-col gap-1.5 overflow-y-auto p-2">
                        <template x-for="w in widgets" :key="w.id">
                            <div data-pb-item
                                :class="selectedId === w.id ? 'ring-1 ring-brand-500 border-brand-300 bg-brand-50/50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/80'"
                                class="group cursor-grab rounded-lg border px-2 py-1.5 text-sm active:cursor-grabbing"
                                @click="selectWidget(w.id)">
                                <div class="flex items-center justify-between gap-1">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-medium text-gray-900 dark:text-white"
                                            x-text="definitions.find(d => d.type === w.type)?.label || w.type"></p>
                                        <p class="truncate text-[11px] text-gray-500 dark:text-gray-400" x-text="widgetPreview(w)"></p>
                                    </div>
                                    <span x-show="w.props?._advanced?.hidden" x-cloak class="shrink-0 text-gray-400" title="Hidden on live site">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                                    </span>
                                    <button type="button" @click.stop="removeWidget(w.id)"
                                        class="shrink-0 rounded p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        title="Remove" aria-label="Remove section">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <p x-show="widgets.length === 0" class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">No sections yet.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Page details slide-over --}}
        <div x-show="seoOpen" x-cloak class="fixed inset-0 z-50 flex justify-end" aria-modal="true" role="dialog">
            <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="seoOpen = false"></div>
            <div class="relative flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
                @click.stop>
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Page details</h3>
                    <button type="button" @click="seoOpen = false" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400">URL, title, search and social preview settings.</p>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Page title <span class="text-red-500">*</span></label>
                        <input type="text" x-model="seo.title" placeholder="e.g. About Us"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-600 dark:text-white" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="createMode && slugEditable">The URL slug is generated automatically from the title.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">URL slug <span class="text-red-500">*</span></label>
                        <template x-if="slugEditable">
                            <div>
                                <input type="text" x-model="seo.slug" @input="onSlugInput()"
                                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                    placeholder="e.g. our-story"
                                    autocomplete="off"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 font-mono text-sm dark:border-gray-600 dark:text-white" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono" x-text="pageUrl"></p>
                            </div>
                        </template>
                        <template x-if="!slugEditable">
                            <div>
                                <input type="text" readonly :value="seo.slug"
                                    class="h-10 w-full cursor-not-allowed rounded-lg border border-gray-200 bg-gray-50 px-3 font-mono text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This system page uses a fixed URL.</p>
                            </div>
                        </template>
                    </div>
                    <hr class="border-gray-200 dark:border-gray-700" />
                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">SEO & Social</p>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Meta title</label>
                        <input type="text" x-model="seo.meta_title" @input="onMetaTitleInput()" maxlength="120"
                            placeholder="Defaults to page title"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-600 dark:text-white" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The title shown in search engine results. Leave blank to use the page title.</p>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300">Meta description</label>
                            <button type="button" @click="generateMetaDescription()"
                                class="btn btn-link btn-sm">
                                Auto-generate
                            </button>
                        </div>
                        <textarea rows="3" x-model="seo.meta_description" @input="onMetaDescInput()" maxlength="500"
                            placeholder="A short summary for search engines"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm dark:border-gray-600 dark:text-white"></textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><span x-text="(seo.meta_description || '').length"></span>/160 recommended</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Featured / OG image</label>
                        <template x-if="seo.og_image_url">
                            <div class="mb-2">
                                <img :src="seo.og_image_url" alt="" class="h-20 w-auto rounded border border-gray-200 dark:border-gray-700" />
                            </div>
                        </template>
                        <template x-if="!createMode">
                            <input type="file" accept="image/*" x-ref="seoOgFile"
                                class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-medium file:text-brand-700 dark:file:bg-brand-900/30 dark:file:text-brand-300" />
                        </template>
                        <template x-if="createMode">
                            <p class="text-xs text-gray-500 dark:text-gray-400">You can upload an image after you create the page.</p>
                        </template>
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400" x-show="!createMode">
                            <input type="checkbox" x-model="seo.remove_og_image" class="rounded border-gray-300 text-brand-500" />
                            Remove current image
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" x-model="seo.is_published" class="h-4 w-4 rounded border-gray-300 text-brand-500" />
                        Published
                    </label>
                    <template x-if="createMode">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Use <strong class="font-medium text-gray-700 dark:text-gray-300">Create page</strong> in the toolbar to save the URL, title, meta, and layout together.</p>
                    </template>
                </div>
                <div class="border-t border-gray-200 p-4 dark:border-gray-700 flex gap-2 justify-end">
                    <button type="button" @click="seoOpen = false"
                        class="btn btn-secondary btn-md">Cancel</button>
                    <button type="button" @click="saveSeo()" :disabled="seoSaving || createMode"
                        class="btn btn-primary btn-md disabled:opacity-50">
                        <span x-show="!seoSaving">Save details</span>
                        <span x-show="seoSaving" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
