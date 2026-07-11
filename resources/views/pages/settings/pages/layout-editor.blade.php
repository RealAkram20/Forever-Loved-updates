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
                <button type="button" @click="save()"
                    :disabled="saving"
                    class="btn btn-primary btn-md disabled:opacity-50">
                    <span x-show="!saving" x-text="createMode ? 'Create page' : 'Save layout'"></span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-12 min-h-[70vh]" @keydown.escape.window="selectedId = null; seoOpen = false">
            <aside class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Widgets</p>
                <div class="flex flex-col gap-2 max-h-[65vh] overflow-y-auto">
                    @php
                        $byCat = collect($widgetDefinitions)->groupBy('category');
                    @endphp
                    @foreach ($byCat as $category => $defs)
                        <p class="pt-2 text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500">{{ $category }}</p>
                        @foreach ($defs as $def)
                            <button type="button" @click="addWidget('{{ $def['type'] }}')"
                                class="flex w-full cursor-pointer items-center gap-2 rounded-lg border border-gray-100 bg-gray-50 px-2 py-2 text-left text-sm text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/5">
                                <span class="text-brand-500">+</span>
                                {{ $def['label'] }}
                            </button>
                        @endforeach
                    @endforeach
                </div>
            </aside>

            <div class="lg:col-span-6 rounded-xl border border-gray-200 bg-gray-50/80 p-4 dark:border-gray-700 dark:bg-gray-900/50">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Page canvas</p>
                    <span class="text-xs text-gray-500 dark:text-gray-400"><span x-text="widgets.length"></span> sections</span>
                </div>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Add widgets from the left. Drag blocks to reorder. Click a block to edit its properties.</p>
                <div class="min-h-[50vh] rounded-lg border-2 border-dashed border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-900">
                    <div x-ref="widgetCanvas" class="flex min-h-[45vh] flex-col gap-2">
                        <template x-for="w in widgets" :key="w.id">
                            <div data-pb-item
                                :class="selectedId === w.id ? 'ring-2 ring-brand-500 border-brand-300' : 'border-gray-200 dark:border-gray-700'"
                                class="cursor-grab cursor-pointer rounded-lg border bg-gray-50/80 p-3 text-sm shadow-sm active:cursor-grabbing dark:bg-gray-800/80"
                                @click="selectWidget(w.id)">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 100 4h6a2 2 0 100-4H7zM5 8a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V8z"/></svg>
                                            <span class="font-medium text-gray-900 dark:text-white" x-text="definitions.find(d => d.type === w.type)?.label || w.type"></span>
                                        </div>
                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400" x-text="widgetPreview(w)"></p>
                                        <p class="mt-0.5 text-[10px] font-mono text-gray-400 dark:text-gray-500" x-text="w.type"></p>
                                    </div>
                                    <button type="button" @click.stop="removeWidget(w.id)"
                                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-900/20 transition" title="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <p x-show="widgets.length === 0" class="py-10 text-center text-sm text-gray-400 dark:text-gray-500">No sections yet — click a widget type on the left to add your first block.</p>
                </div>
            </div>

            <aside class="lg:col-span-4 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 flex flex-col max-h-[80vh]">
                <template x-if="!selectedWidget">
                    <div class="p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Properties</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Select a widget on the canvas.</p>
                    </div>
                </template>
                <template x-if="selectedWidget && selectedDefinition">
                    <div class="flex flex-col min-h-0 flex-1" x-data="{ propTab: 'content' }">
                        {{-- Widget name header --}}
                        <div class="border-b border-gray-200 dark:border-gray-700 px-4 pt-3 pb-0">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="h-4 w-4 shrink-0 text-brand-500" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 100 4h6a2 2 0 100-4H7zM5 8a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V8z"/></svg>
                                <span class="text-sm font-semibold text-gray-900 dark:text-white" x-text="selectedDefinition.label"></span>
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
                                <template x-if="selectedDefinition.fields.filter(f => f.tab === 'style').length === 0">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No style options for this widget.</p>
                                </template>
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
                            </div>

                        </div>
                    </div>
                </template>
            </aside>
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
