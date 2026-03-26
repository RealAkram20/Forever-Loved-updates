@extends('layouts.fullscreen-layout')

@push('head')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
@if($shareMeta ?? null)
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $shareMeta['title'] }}">
<meta property="og:description" content="{{ $shareMeta['description'] }}">
<meta property="og:url" content="{{ $shareMeta['url'] }}">
@if($shareMeta['image'] ?? null)
<meta property="og:image" content="{{ $shareMeta['image'] }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $shareMeta['title'] }}">
<meta name="twitter:description" content="{{ $shareMeta['description'] }}">
@if($shareMeta['image'] ?? null)
<meta name="twitter:image" content="{{ $shareMeta['image'] }}">
@endif
@endif
@endpush

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" data-memorial-slug="{{ $memorial->slug }}" data-tribute-url="{{ route('memorial.api.tribute', ['slug' => $memorial->slug]) }}" data-can-edit="{{ $canEdit ? '1' : '0' }}" data-is-authenticated="{{ $isAuthenticated ? '1' : '0' }}" data-can-upload="{{ $canEdit ? '1' : '0' }}" data-scroll-tribute="{{ $scrollToTributeId ?? '' }}" data-scroll-chapter="{{ $scrollToChapterId ?? '' }}" data-deceased-first="{{ \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'them') }}" data-user-initial="{{ strtoupper(substr(auth()->user()?->name ?? 'G', 0, 1)) }}">
    <x-home-header />

    @if ($canEdit)
        {{-- Owner edit affordance: hover-only controls are invisible on touch; banner + mobile-visible pencils fix that --}}
        <div class="sticky top-14 z-30 border-b border-amber-300/80 bg-amber-50 px-4 py-2.5 shadow-sm dark:border-amber-500/45 dark:bg-amber-950/95 sm:px-6" role="status" aria-live="polite">
            <div class="mx-auto flex max-w-7xl items-start gap-2.5">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-200/90 text-amber-900 dark:bg-amber-500/25 dark:text-amber-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-50">You’re editing this memorial</p>
                    <p class="mt-0.5 text-xs leading-snug text-amber-900/85 dark:text-amber-100/85">Tap any <span class="font-medium">pencil</span> button or a <span class="font-medium">dashed outline</span> to update content. This strip is only shown to you.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Guest modal: name + email for tributes/reactions --}}
    <div id="guest-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white/90">Enter your details</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Please provide your name and email to continue.</p>
            <form id="guest-form" class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your name</label>
                    <input type="text" id="guest-name" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="Your name" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your email</label>
                    <input type="email" id="guest-email" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Continue</button>
                    <button type="button" onclick="hideGuestModal()" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Three-column layout --}}
    <main class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-12">
            {{-- Column 1: Profile card (narrow) --}}
            <aside class="md:col-span-4 lg:col-span-3">
                <div class="md:sticky md:top-16 space-y-4">
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm">
                        <div class="p-4 sm:p-6">
                            <div class="flex flex-col items-center text-center">
                                {{-- Profile photo with upload + age bubble --}}
                                @php
                                    $ageLabel = null;
                                    if ($memorial->date_of_birth && $memorial->date_of_passing) {
                                        $days   = (int) abs($memorial->date_of_birth->diffInDays($memorial->date_of_passing));
                                        $months = (int) abs($memorial->date_of_birth->diffInMonths($memorial->date_of_passing));
                                        $years  = (int) abs($memorial->date_of_birth->diffInYears($memorial->date_of_passing));

                                        if ($years >= 1) {
                                            $ageLabel = $years . 'yr' . ($years !== 1 ? 's' : '');
                                        } elseif ($months >= 1) {
                                            $ageLabel = $months . 'mth' . ($months !== 1 ? 's' : '');
                                        } else {
                                            $ageLabel = $days . 'day' . ($days !== 1 ? 's' : '');
                                        }
                                    }
                                @endphp
                                <div class="relative group mb-4">
                                    <div class="h-24 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        @if ($memorial->profile_photo_path)
                                            <img src="{{ $memorial->profile_photo_url ?? '' }}" alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-3xl text-gray-400 dark:text-gray-500">?</div>
                                        @endif
                                    </div>
                                    @if ($ageLabel)
                                        <span class="absolute -right-2 top-0 z-10 rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white shadow-md shadow-brand-500/30 ring-2 ring-white dark:ring-gray-900">{{ $ageLabel }}</span>
                                    @endif
                                    @if ($canEdit)
                                        <label class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/55">
                                            <input type="file" id="profile-photo-input" accept="image/*" class="hidden" />
                                            <span class="rounded-md bg-white/95 px-2 py-0.5 text-[11px] font-semibold text-gray-900 shadow-sm dark:bg-gray-900/95 dark:text-white">Photo</span>
                                        </label>
                                    @endif
                                </div>
                                <div data-editable="full_name" class="relative group w-full @if($canEdit) rounded-lg border border-dashed border-brand-400/55 bg-brand-50/40 px-2 py-2 dark:border-brand-400/40 dark:bg-brand-500/[0.08] @endif">
                                    @if ($canEdit)
                                        <div class="flex items-start justify-center gap-2">
                                            <h2 data-display class="min-w-0 flex-1 text-center text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->full_name ?: 'Full name' }}</h2>
                                            <button type="button" data-edit-trigger class="memorial-edit-fab shrink-0 rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit name">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        </div>
                                        <div data-edit class="hidden mt-1">
                                            <input type="text" value="{{ $memorial->full_name }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                            <button type="button" data-save class="mt-2 rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white">Save</button>
                                        </div>
                                    @else
                                        <h2 data-display class="text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->full_name ?: 'Full name' }}</h2>
                                    @endif
                                </div>
                                @if ($canEdit || ($memorial->designation && !$memorial->cause_of_death_private))
                                    <div data-editable="designation" class="relative group mt-0.5 w-full @if($canEdit) rounded-lg border border-dashed border-brand-400/45 bg-brand-50/35 px-2 py-1.5 dark:border-brand-400/35 dark:bg-brand-500/[0.06] @endif">
                                        @if ($canEdit)
                                            <div class="flex items-start justify-center gap-1.5">
                                                <p data-display class="min-w-0 flex-1 text-center text-sm text-gray-600 dark:text-gray-300">{{ $memorial->designation ?: 'Add designation' }}</p>
                                                <button type="button" data-edit-trigger class="memorial-edit-fab shrink-0 rounded-md border border-brand-300/90 bg-white p-1 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit designation">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                            </div>
                                            <div data-edit class="hidden mt-1">
                                                <input type="text" value="{{ $memorial->designation }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Designation" />
                                                <button type="button" data-save class="mt-2 rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white">Save</button>
                                            </div>
                                        @else
                                            <p data-display class="text-sm text-gray-500 dark:text-gray-400">{{ $memorial->designation }}</p>
                                        @endif
                                    </div>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center justify-center gap-1.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 dark:bg-success-500/20 px-3 py-1 text-xs font-medium text-success-700 dark:text-success-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        In Loving Memory
                                    </span>
                                    @if ($memorial->is_public)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-500/15 px-2.5 py-0.5 text-[11px] font-medium text-blue-600 dark:text-blue-400">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Public
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Private
                                        </span>
                                    @endif
                                    @php
                                        $qualityPercent = $memorial->completion_percentage;
                                        $qualityColor = $qualityPercent >= 75 ? 'green' : ($qualityPercent >= 40 ? 'amber' : 'red');
                                        $qualityColors = [
                                            'green' => ['bg' => 'bg-green-50 dark:bg-green-500/15', 'text' => 'text-green-600 dark:text-green-400', 'dot' => 'bg-green-500'],
                                            'amber' => ['bg' => 'bg-amber-50 dark:bg-amber-500/15', 'text' => 'text-amber-600 dark:text-amber-400', 'dot' => 'bg-amber-500'],
                                            'red' => ['bg' => 'bg-red-50 dark:bg-red-500/15', 'text' => 'text-red-600 dark:text-red-400', 'dot' => 'bg-red-500'],
                                        ];
                                        $qc = $qualityColors[$qualityColor];
                                    @endphp
                                    @if ($canEdit)
                                        <span class="inline-flex items-center gap-1 rounded-full {{ $qc['bg'] }} px-2.5 py-0.5 text-[11px] font-medium {{ $qc['text'] }}" title="Profile completeness: {{ $qualityPercent }}%">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $qc['dot'] }}"></span>
                                            {{ $qualityPercent }}% Complete
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-4 flex gap-6">
                                    <div class="text-center">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-tribute-count>{{ $tributes->total() }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Tributes</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->galleryMedia()->count() }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Photos</p>
                                    </div>
                                </div>
                            </div>
                            @if ($memorial->date_of_birth || $memorial->date_of_passing || $canEdit)
                                <div data-editable="dates" class="mt-4 pt-4 text-center @if($canEdit) rounded-lg border border-dashed border-brand-400/50 bg-brand-50/30 px-2 pb-3 dark:border-brand-400/35 dark:bg-brand-500/[0.06] @else border-t border-gray-100 dark:border-gray-800 @endif">
                                    <p data-display class="text-sm text-gray-600 dark:text-gray-400">
                                        @if ($memorial->date_of_birth){{ $memorial->date_of_birth->format('M d, Y') }}@endif
                                        @if ($memorial->date_of_birth && $memorial->date_of_passing) &ndash; @endif
                                        @if ($memorial->date_of_passing){{ $memorial->date_of_passing->format('M d, Y') }}@endif
                                        @if (!$memorial->date_of_birth && !$memorial->date_of_passing && $canEdit) Add dates @endif
                                    </p>
                                    @if ($ageLabel)
                                        <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400">Died at {{ $ageLabel }}</p>
                                    @endif
                                    @if ($canEdit)
                                        <div data-edit class="hidden mt-2 space-y-2 text-left sm:text-center">
                                            <input type="date" data-date-type="birth" value="{{ $memorial->date_of_birth?->format('Y-m-d') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:w-auto" />
                                            <input type="date" data-date-type="death" value="{{ $memorial->date_of_passing?->format('Y-m-d') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:ml-1 sm:w-auto" />
                                            <button type="button" data-save class="mt-1 rounded-md bg-brand-500 px-3 py-1.5 text-xs font-medium text-white">Save</button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="flex border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-white/[0.02] p-2">
                            <a href="#tab-biography" data-tab="biography" class="memorial-tab min-w-0 flex-1 rounded-lg px-2 py-2 text-center text-xs font-medium text-brand-600 dark:text-brand-400 hover:bg-white dark:hover:bg-white/10">Biography</a>
                            <a href="#tab-life" data-tab="life" class="memorial-tab min-w-0 flex-1 rounded-lg px-2 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-white/10 hover:text-brand-600 dark:hover:text-brand-400">Life</a>
                            <a href="#tab-gallery" data-tab="gallery" class="memorial-tab min-w-0 flex-1 rounded-lg px-2 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-white/10 hover:text-brand-600 dark:hover:text-brand-400">Gallery</a>
                            <a href="#tab-tributes" data-tab="tributes" class="memorial-tab min-w-0 flex-1 rounded-lg px-2 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-white dark:hover:bg-white/10 hover:text-brand-600 dark:hover:text-brand-400">Tributes</a>
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Column 2: Tabbed content (Life, Biography, Gallery, Tributes) --}}
            <section class="md:col-span-8 lg:col-span-6">
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm">
                    {{-- Tab buttons (equal width) --}}
                    <div class="flex border-b border-gray-100 dark:border-gray-800">
                        <button type="button" data-tab-panel="biography" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-brand-600 dark:text-brand-400 border-b-2 border-brand-500 bg-brand-50/50 dark:bg-brand-500/10">Biography</button>
                        <button type="button" data-tab-panel="life" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Life</button>
                        <button type="button" data-tab-panel="gallery" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Gallery</button>
                        <button type="button" data-tab-panel="tributes" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Tributes</button>
                    </div>

                    {{-- Tab: Biography (first) --}}
                    <div id="tab-biography" class="memorial-tab-panel p-4 sm:p-6">
                        <div data-editable="biography" class="relative group rounded-xl @if($canEdit) border border-dashed border-brand-400/55 bg-brand-50/35 p-3 dark:border-brand-400/40 dark:bg-brand-500/[0.07] @endif">
                            @if ($canEdit)
                                <button type="button" data-edit-trigger class="memorial-edit-fab absolute right-0 top-0 z-10 rounded-lg border border-brand-300/90 bg-white p-2 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit biography">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            @endif
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90 @if($canEdit) pr-12 @endif">Biography</h2>
                            <div data-display class="mt-3 text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\BiographyFormatter::format($memorial->biography) ?: 'Add biography...' !!}</div>
                            @if ($canEdit)
                                <div data-edit class="hidden mt-3 space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your story</label>
                                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Share your memories with text, photos, videos, or documents.</p>
                                        <div id="biography-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                        <input type="hidden" id="biography-content" />
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" data-save class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600" data-save-text="Save" data-saving-text="Saving...">Save</button>
                                        <a href="{{ route('memorials.edit', $memorial) }}#biography" class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit in full memorial
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab: Life (Story chapters + posts) --}}
                    <div id="tab-life" class="memorial-tab-panel hidden p-4 sm:p-6">
                        <div class="mb-4">
                            <button type="button" id="add-story-btn-top" class="w-full rounded-xl border-2 border-dashed border-brand-400 dark:border-brand-500 bg-brand-50/50 dark:bg-brand-500/10 px-4 py-3 text-sm font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-500/20 transition">
                                + Your Chapter
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <div class="flex flex-wrap gap-1" id="chapter-filters">
                                <button type="button" class="chapter-filter rounded-md bg-brand-50 dark:bg-brand-500/20 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400" data-chapter="">All</button>
                                @foreach ($memorial->storyChapters as $chapter)
                                    @php $chapterPostCount = $memorial->posts->where('story_chapter_id', $chapter->id)->where('is_published', true)->count(); @endphp
                                    <div class="group relative inline-flex items-center" data-chapter-pill="{{ $chapter->id }}">
                                        <button type="button" class="chapter-filter relative rounded-md px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/10" data-chapter="{{ $chapter->id }}">
                                            {{ $chapter->title }}
                                            <span class="ml-1 inline-block h-2 w-2 rounded-full {{ $chapterPostCount >= 3 ? 'bg-emerald-500' : ($chapterPostCount >= 1 ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600') }}" title="{{ $chapterPostCount }} {{ Str::plural('post', $chapterPostCount) }}"></span>
                                        </button>
                                        @if ($canEdit)
                                            <div class="absolute -top-1 -right-1 z-10 flex items-center gap-0.5">
                                                <button type="button" data-edit-chapter="{{ $chapter->id }}" data-chapter-title="{{ $chapter->title }}" data-chapter-desc="{{ $chapter->description }}"
                                                    class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white shadow-sm ring-2 ring-white dark:ring-gray-900 hover:bg-brand-600 transition" title="Edit chapter">
                                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                <button type="button" data-delete-chapter="{{ $chapter->id }}"
                                                    class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow-sm ring-2 ring-white dark:ring-gray-900 hover:bg-red-600 transition" title="Delete chapter">
                                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Edit chapter modal --}}
                        @if ($canEdit)
                        <div id="edit-chapter-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
                            <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-xl">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white/90">Edit Chapter</h3>
                                <form id="edit-chapter-form" class="mt-4 space-y-4">
                                    <input type="hidden" id="edit-chapter-id" />
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                                        <input type="text" id="edit-chapter-title" required
                                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
                                        <textarea id="edit-chapter-desc" rows="2"
                                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm"></textarea>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Save</button>
                                        <button type="button" onclick="document.getElementById('edit-chapter-modal').classList.add('hidden')" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @endif
                        <div class="space-y-4" id="life-feed">
                            @php $lifePosts = $memorial->posts->where('is_published', true)->sortByDesc('created_at'); @endphp
                            @foreach ($lifePosts as $post)
                                <article id="chapter-{{ $post->id }}" class="group/post relative overflow-visible rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]" data-post-id="{{ $post->id }}" data-chapter-id="{{ $post->story_chapter_id ?? '' }}">
                                    <div class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/30 text-brand-600 dark:text-brand-400 text-sm font-semibold">
                                                {{ strtoupper(substr($post->user?->name ?? $memorial->full_name ?? '?', 0, 1)) }}
                                            </div>
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900 dark:text-white/90">{{ $post->user?->name ?? $memorial->full_name ?? 'Anonymous' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400"><span class="time-ago" data-created-at="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</span> · {{ $post->storyChapter?->title ?? 'Life' }}</p>
                                            </div>
                                            @if ($canEdit)
                                                <button type="button" data-post-edit-trigger="{{ $post->id }}" class="memorial-edit-fab rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit post">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                        <div data-post-display="{{ $post->id }}">
                                            @if ($post->title)
                                                <h3 class="mt-2 font-medium text-gray-900 dark:text-white/90">{{ $post->title }}</h3>
                                            @endif
                                            @if ($post->content)
                                                <div class="mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none break-words overflow-hidden">{!! \App\Helpers\HtmlHelper::sanitize($post->content) !!}</div>
                                            @endif
                                            @if ($post->media->isNotEmpty())
                                                <div class="mt-3 space-y-3">
                                                    @foreach ($post->media as $m)
                                                        @if ($m->type === 'photo')
                                                            <img src="{{ $m->url }}" alt="{{ $m->caption }}" class="max-w-full rounded-lg" />
                                                        @elseif ($m->type === 'video')
                                                            <x-media.video-player :src="$m->url" :caption="$m->caption" />
                                                        @elseif ($m->type === 'music')
                                                            <x-media.audio-player :src="$m->url" :caption="$m->caption" :filename="$m->filename" />
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($post->location)
                                                <div class="mt-3 flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                                    <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $post->location }}</span>
                                                </div>
                                            @endif
                                        </div>
                                        @if ($canEdit)
                                            <div data-post-edit="{{ $post->id }}" class="hidden mt-3 space-y-3">
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                                                    <input type="text" data-post-edit-title="{{ $post->id }}" value="{{ $post->title ?? '' }}" placeholder="Post title (optional)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Content</label>
                                                    <div id="post-editor-{{ $post->id }}" class="min-h-[120px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-3">
                                                    <button type="button" data-post-save="{{ $post->id }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">Save</button>
                                                    <button type="button" data-post-cancel="{{ $post->id }}" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 transition">Cancel</button>
                                                    <button type="button" data-post-delete="{{ $post->id }}" class="ml-auto inline-flex items-center gap-1.5 text-sm text-red-500 hover:text-red-600 transition">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="relative z-10 flex items-center gap-4 border-t border-gray-100 dark:border-gray-800 px-4 py-2">
                                        <div class="flex items-center gap-1" data-reaction-container="{{ $post->id }}">
                                            <button type="button" data-reaction-btn data-reactionable-type="post" data-reactionable-id="{{ $post->id }}" data-reaction-type="like" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-rose-500 dark:hover:text-rose-400">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                                <span data-post-id="{{ $post->id }}" data-reaction-count class="text-sm text-gray-600 dark:text-gray-400">{{ $post->reactions->count() }}</span>
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1" data-comment-container="{{ $post->id }}">
                                            <button type="button" data-comment-toggle data-post-id="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                <span data-post-id="{{ $post->id }}" data-comment-count class="text-sm text-gray-600 dark:text-gray-400">{{ $post->comments->count() + $post->comments->sum(fn($c) => $c->replies->count()) }}</span>
                                            </button>
                                        </div>
                                        @if ($quotaInfo['share_memories'] ?? false)
                                            <div class="relative ml-auto" data-share-container="{{ $post->id }}">
                                                <button type="button" data-share-toggle data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" data-post-id="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                                    Share
                                                </button>
                                                <div data-share-dropdown="{{ $post->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                                                    @include('pages.memorials.partials.share-dropdown', ['shareUrl' => route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id])])
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    {{-- Inline comment thread (hidden by default, toggled by comment button) --}}
                                    <div data-comment-section="{{ $post->id }}" class="hidden border-t border-gray-100 dark:border-gray-800 overflow-hidden">
                                        {{-- Comment input --}}
                                        <div class="flex flex-wrap items-center gap-2 px-3 py-3 sm:px-4">
                                            <div class="flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/25 text-brand-600 dark:text-brand-400 text-[11px] sm:text-xs font-semibold">
                                                {{ strtoupper(substr(auth()->user()?->name ?? 'G', 0, 1)) }}
                                            </div>
                                            <input type="text" data-comment-input="{{ $post->id }}" placeholder="Add a comment..." class="h-9 min-w-0 flex-1 basis-36 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                            <button type="button" data-comment-submit data-post-id="{{ $post->id }}" class="h-9 shrink-0 rounded-full bg-brand-500 px-3 sm:px-4 text-xs font-semibold text-white hover:bg-brand-600 transition active:scale-95">Post</button>
                                        </div>
                                        {{-- Thread list --}}
                                        <div class="px-3 pb-3 space-y-0 sm:px-4" data-comments-list="{{ $post->id }}">
                                            @foreach ($post->comments as $comment)
                                                @include('pages.memorials.partials.comment-item', ['comment' => $comment, 'postId' => $post->id, 'canDelete' => $canEdit])
                                            @endforeach
                                        </div>
                                        <p data-comments-empty="{{ $post->id }}" class="px-3 pb-4 text-center text-xs text-gray-400 dark:text-gray-500 sm:px-4 {{ $post->comments->isEmpty() ? '' : 'hidden' }}">No comments yet. Be the first to comment.</p>
                                    </div>
                                </article>
                            @endforeach
                            @if ($lifePosts->isEmpty())
                                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <p class="text-gray-500 dark:text-gray-400">No stories yet.</p>
                                    @if ($canEdit)
                                        <p class="mt-1 text-sm text-brand-500">Add a tribute story with text, images, or videos.</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div id="chapter-form-anchor" class="mt-8 scroll-mt-24"></div>
                        <div id="add-story-form" class="mt-4 rounded-xl border-2 border-brand-200 dark:border-brand-600 bg-brand-50/30 dark:bg-brand-500/10 p-5 shadow-sm">
                            @if (!$isAuthenticated)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your name</label>
                                        <input type="text" id="chapter-guest-name" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm" placeholder="Your name" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your email</label>
                                        <input type="email" id="chapter-guest-email" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                                    </div>
                                </div>
                            @endif
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white/90 mb-1">Write your chapter</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Share your memories with text, photos, videos, or documents.</p>
                                <form id="tribute-post-form" class="mt-3 space-y-3">
                                    <div>
                                        <input type="text" name="title" placeholder="Title (optional)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your story</label>
                                        <div id="chapter-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                        <input type="hidden" name="content" id="chapter-content" />
                                    </div>
                                    <div class="rounded-lg border-2 border-dashed border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-900/10 p-4">
                                        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Add a document, picture, song, or video</p>
                                        <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">You can illustrate your story with a photo, video, song, or PDF document attachment.</p>
                                        <input type="file" name="files[]" multiple accept="image/*,video/*,audio/*,.pdf" class="mt-2 w-full text-sm" />
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Post</button>
                                        <button type="button" id="cancel-story-btn" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm">Cancel</button>
                                    </div>
                                </form>
                            </div>
                    </div>

                    {{-- Tab: Gallery with Images/Videos sub-tabs + lightbox --}}
                    @php
                        $galleryItems = $memorial->galleryMedia()->orderBy('sort_order')->get();
                        $galleryImages = $galleryItems->where('type', 'photo')->values();
                        $galleryVideos = $galleryItems->where('type', 'video')->values();
                    @endphp
                    <div id="tab-gallery" class="memorial-tab-panel hidden p-4 sm:p-6"
                        x-data="{
                            subTab: 'images',
                            lightboxOpen: false,
                            currentIndex: 0,
                            images: {{ Js::from($galleryImages->map(fn($m) => ['url' => $m->url, 'caption' => $m->caption ?? 'Photo'])->toArray()) }},
                            playing: false,
                            speed: 3000,
                            interval: null,
                            get currentImage() { return this.images[this.currentIndex] || {} },
                            get total() { return this.images.length },
                            openLightbox(idx) {
                                this.currentIndex = idx;
                                this.lightboxOpen = true;
                                document.body.style.overflow = 'hidden';
                            },
                            closeLightbox() {
                                this.stopSlideshow();
                                this.lightboxOpen = false;
                                document.body.style.overflow = '';
                            },
                            next() {
                                if (this.total === 0) return;
                                this.currentIndex = (this.currentIndex + 1) % this.total;
                            },
                            prev() {
                                if (this.total === 0) return;
                                this.currentIndex = (this.currentIndex - 1 + this.total) % this.total;
                            },
                            toggleSlideshow() {
                                this.playing ? this.stopSlideshow() : this.startSlideshow();
                            },
                            startSlideshow() {
                                if (this.total <= 1) return;
                                this.playing = true;
                                this.interval = setInterval(() => this.next(), this.speed);
                            },
                            stopSlideshow() {
                                this.playing = false;
                                clearInterval(this.interval);
                                this.interval = null;
                            },
                            setSpeed(ms) {
                                this.speed = ms;
                                if (this.playing) {
                                    clearInterval(this.interval);
                                    this.interval = setInterval(() => this.next(), this.speed);
                                }
                            },
                            addImage(url, caption) {
                                this.images.push({ url, caption: caption || 'Photo' });
                            }
                        }"
                        @keydown.escape.window="if (lightboxOpen) closeLightbox()"
                        @keydown.arrow-right.window="if (lightboxOpen) next()"
                        @keydown.arrow-left.window="if (lightboxOpen) prev()">

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Gallery</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Photos and videos shared in memory.</p>
                            </div>
                            @if ($canEdit)
                                <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5">
                                    <input type="file" id="gallery-upload" accept="image/*,video/*" class="hidden" />
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Upload
                                </label>
                            @endif
                        </div>
                        @if (isset($quotaInfo) && ($quotaInfo['gallery_images']['max'] > 0 || $quotaInfo['gallery_videos']['max'] > 0))
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if ($quotaInfo['gallery_images']['max'] > 0)
                                    <span data-quota-images data-current="{{ $quotaInfo['gallery_images']['current'] }}" data-max="{{ $quotaInfo['gallery_images']['max'] }}" class="{{ !$quotaInfo['gallery_images']['allowed'] ? 'text-red-500 dark:text-red-400 font-medium' : '' }}">
                                        Images: {{ $quotaInfo['gallery_images']['current'] }}/{{ $quotaInfo['gallery_images']['max'] }}
                                    </span>
                                @endif
                                @if ($quotaInfo['gallery_videos']['max'] > 0)
                                    <span data-quota-videos data-current="{{ $quotaInfo['gallery_videos']['current'] }}" data-max="{{ $quotaInfo['gallery_videos']['max'] }}" class="{{ !$quotaInfo['gallery_videos']['allowed'] ? 'text-red-500 dark:text-red-400 font-medium' : '' }}">
                                        Videos: {{ $quotaInfo['gallery_videos']['current'] }}/{{ $quotaInfo['gallery_videos']['max'] }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Sub-tabs: Images / Videos --}}
                        <div class="mt-4 flex gap-1 rounded-lg bg-gray-100 dark:bg-white/[0.04] p-1">
                            <button type="button" @click="subTab = 'images'"
                                :class="subTab === 'images' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition">
                                Images <span class="ml-1 text-xs opacity-60" x-text="'(' + images.length + ')'"></span>
                            </button>
                            <button type="button" @click="subTab = 'videos'"
                                :class="subTab === 'videos' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition">
                                Videos <span class="ml-1 text-xs opacity-60">({{ $galleryVideos->count() }})</span>
                            </button>
                        </div>

                        {{-- Images grid --}}
                        <div x-show="subTab === 'images'" x-cloak class="mt-4">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" id="gallery-grid-images">
                                @foreach ($galleryImages as $idx => $media)
                                    <div class="group/img relative aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700" data-gallery-item data-media-id="{{ $media->id }}" data-media-type="photo" data-gallery-index="{{ $idx }}">
                                        <button type="button" @click="openLightbox({{ $idx }})"
                                            class="block h-full w-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                                            <img src="{{ $media->url }}" alt="{{ $media->caption ?? 'Photo' }}"
                                                class="h-full w-full object-cover transition duration-300 group-hover/img:scale-105" loading="lazy" />
                                            <div class="absolute inset-0 bg-black/0 transition group-hover/img:bg-black/10"></div>
                                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/40 to-transparent p-2 opacity-0 transition group-hover/img:opacity-100">
                                                <svg class="mx-auto h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                            </div>
                                        </button>
                                        @if ($canEdit)
                                            <div class="absolute top-1 right-1 z-10 flex items-center gap-1">
                                                <button type="button" data-gallery-edit-caption="{{ $media->id }}" data-current-caption="{{ e($media->caption ?? '') }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white hover:bg-brand-500 transition" title="Edit caption">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                <button type="button" data-gallery-delete="{{ $media->id }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white hover:bg-red-500 transition" title="Delete">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div id="gallery-images-empty" class="{{ $galleryImages->isEmpty() ? '' : 'hidden' }}">
                                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400">No photos yet.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Videos grid --}}
                        <div x-show="subTab === 'videos'" x-cloak class="mt-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" id="gallery-grid-videos">
                                @foreach ($galleryVideos as $media)
                                    <div class="group/vid relative" data-gallery-item data-media-id="{{ $media->id }}" data-media-type="video">
                                        <x-media.video-player :src="$media->url" :caption="$media->caption" />
                                        @if ($canEdit)
                                            <div class="absolute top-2 right-2 z-20 flex items-center gap-1">
                                                <button type="button" data-gallery-edit-caption="{{ $media->id }}" data-current-caption="{{ e($media->caption ?? '') }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white hover:bg-brand-500 transition" title="Edit caption">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                <button type="button" data-gallery-delete="{{ $media->id }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white hover:bg-red-500 transition" title="Delete">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div id="gallery-videos-empty" class="{{ $galleryVideos->isEmpty() ? '' : 'hidden' }}">
                                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400">No videos yet.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Caption edit popover --}}
                        @if ($canEdit)
                            <div id="gallery-caption-editor" class="hidden fixed inset-0 z-[99998] flex items-center justify-center bg-black/50 backdrop-blur-sm">
                                <div class="mx-4 w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
                                    <div class="p-5">
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Edit caption</h3>
                                        <input type="text" id="gallery-caption-input" placeholder="Enter caption..." class="mt-3 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                        <input type="hidden" id="gallery-caption-media-id" />
                                    </div>
                                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-700 px-5 py-3">
                                        <button type="button" id="gallery-caption-cancel" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition">Cancel</button>
                                        <button type="button" id="gallery-caption-save" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">Save</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Lightbox overlay --}}
                        <template x-teleport="body">
                            <div x-show="lightboxOpen" x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="fixed inset-0 z-[99999] flex flex-col bg-black/95" @click.self="closeLightbox()">

                                {{-- Top bar --}}
                                <div class="flex items-center justify-between px-4 py-3 text-white">
                                    <span class="text-sm font-medium" x-text="(currentIndex + 1) + ' / ' + total"></span>
                                    <div class="flex items-center gap-3">
                                        {{-- Slideshow toggle --}}
                                        <button type="button" @click="toggleSlideshow()"
                                            :class="playing ? 'bg-white/20' : 'hover:bg-white/10'"
                                            class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition"
                                            :title="playing ? 'Pause slideshow' : 'Start slideshow'">
                                            <template x-if="!playing">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </template>
                                            <template x-if="playing">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                            </template>
                                            <span x-text="playing ? 'Pause' : 'Slideshow'"></span>
                                        </button>
                                        {{-- Speed control --}}
                                        <div class="flex items-center gap-1.5 rounded-lg bg-white/10 px-2 py-1">
                                            <svg class="h-3.5 w-3.5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <button type="button" @click="setSpeed(1500)"
                                                :class="speed === 1500 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">1.5s</button>
                                            <button type="button" @click="setSpeed(3000)"
                                                :class="speed === 3000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">3s</button>
                                            <button type="button" @click="setSpeed(5000)"
                                                :class="speed === 5000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">5s</button>
                                            <button type="button" @click="setSpeed(8000)"
                                                :class="speed === 8000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">8s</button>
                                        </div>
                                        {{-- Close --}}
                                        <button type="button" @click="closeLightbox()" class="rounded-lg p-1.5 hover:bg-white/10 transition">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Main image area --}}
                                <div class="relative flex flex-1 items-center justify-center px-16" @click.self="closeLightbox()">
                                    {{-- Previous button --}}
                                    <button type="button" @click="prev()" x-show="total > 1"
                                        class="absolute left-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white backdrop-blur-sm transition hover:bg-white/20">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>

                                    {{-- Image --}}
                                    <img :src="currentImage.url" :alt="currentImage.caption"
                                        class="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl select-none"
                                        @click.stop />

                                    {{-- Next button --}}
                                    <button type="button" @click="next()" x-show="total > 1"
                                        class="absolute right-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white backdrop-blur-sm transition hover:bg-white/20">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>

                                {{-- Caption --}}
                                <div class="px-4 py-3 text-center" x-show="currentImage.caption && currentImage.caption !== 'Photo'">
                                    <p class="text-sm text-white/70" x-text="currentImage.caption"></p>
                                </div>

                                {{-- Thumbnail strip --}}
                                <div class="border-t border-white/10 px-4 py-3" x-show="total > 1">
                                    <div class="flex justify-center gap-1.5 overflow-x-auto">
                                        <template x-for="(img, i) in images" :key="i">
                                            <button type="button" @click="currentIndex = i; if (playing) { stopSlideshow(); startSlideshow(); }"
                                                :class="i === currentIndex ? 'ring-2 ring-white opacity-100' : 'opacity-50 hover:opacity-80'"
                                                class="h-12 w-12 shrink-0 overflow-hidden rounded-md transition">
                                                <img :src="img.url" :alt="img.caption" class="h-full w-full object-cover" />
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Slideshow progress bar --}}
                                <div x-show="playing" class="h-0.5 bg-white/10">
                                    <div class="h-full bg-brand-500 transition-all"
                                        :style="'animation: slideshow-progress ' + speed + 'ms linear infinite'"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Tab: Tributes --}}
                    @php $tc = $tributeCounts ?? ['flower' => 0, 'candle' => 0, 'note' => 0, 'total' => 0]; @endphp
                    <div id="tab-tributes" class="memorial-tab-panel hidden p-4 sm:p-6" x-data="{ tributeFilter: 'all' }">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Tributes (<span data-tribute-count>{{ $tributes->total() + (isset($highlightTribute) ? 1 : 0) }}</span>)</h2>
                            <button type="button" id="add-tribute-btn" class="rounded-lg border border-dashed border-brand-400 dark:border-brand-500 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/20">Add a tribute</button>
                        </div>

                        {{-- Type filter tabs --}}
                        <div class="flex flex-wrap gap-2 mb-5">
                            <button type="button" @click="tributeFilter = 'all'" :class="tributeFilter === 'all' ? 'bg-gray-900 dark:bg-white/90 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/10'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
                                All
                                <span class="rounded-full bg-white/20 dark:bg-gray-900/20 px-2 py-0.5 text-xs" :class="tributeFilter === 'all' ? 'bg-white/20 dark:bg-gray-900/20' : 'bg-gray-200 dark:bg-white/10'" data-count-all>{{ $tc['total'] }}</span>
                            </button>
                            <button type="button" @click="tributeFilter = 'flower'" :class="tributeFilter === 'flower' ? 'bg-pink-600 dark:bg-pink-500 text-white' : 'bg-pink-50 dark:bg-pink-950/30 text-pink-700 dark:text-pink-400 hover:bg-pink-100 dark:hover:bg-pink-900/40'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.5 2 7.5 4.5 7.5 7c0 1.8 1 3.4 2.5 4.2V22h4V11.2c1.5-.8 2.5-2.4 2.5-4.2 0-2.5-2-5-4.5-5zm-2 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4 0c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
                                Flowers
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'flower' ? 'bg-white/20' : 'bg-pink-100 dark:bg-pink-900/40'" data-count-flower>{{ $tc['flower'] }}</span>
                            </button>
                            <button type="button" @click="tributeFilter = 'candle'" :class="tributeFilter === 'candle' ? 'bg-amber-600 dark:bg-amber-500 text-white' : 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 0-1 .19-1.41.59l-1.3 1.3C8.78 4.4 8.5 5.13 8.5 5.91c0 1.97 1.6 3.59 3.5 3.59s3.5-1.62 3.5-3.59c0-.78-.28-1.51-.79-2.02l-1.3-1.3C13 2.19 12.5 2 12 2zm-1 8.5V22h2V10.5h-2z"/></svg>
                                Candles
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'candle' ? 'bg-white/20' : 'bg-amber-100 dark:bg-amber-900/40'" data-count-candle>{{ $tc['candle'] }}</span>
                            </button>
                            <button type="button" @click="tributeFilter = 'note'" :class="tributeFilter === 'note' ? 'bg-gray-700 dark:bg-gray-500 text-white' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/10'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                Notes
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'note' ? 'bg-white/20' : 'bg-gray-200 dark:bg-white/10'" data-count-note>{{ $tc['note'] }}</span>
                            </button>
                        </div>

                        <div class="space-y-4" data-tributes-list>
                            @if($highlightTribute ?? null)
                                @foreach([$highlightTribute] as $tribute)
                                    @include('pages.memorials.partials.tribute-item', ['tribute' => $tribute, 'shareUrl' => route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])])
                                @endforeach
                            @endif
                            @foreach ($tributes as $tribute)
                                @include('pages.memorials.partials.tribute-item', ['tribute' => $tribute, 'shareUrl' => route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])])
                            @endforeach
                            <p data-tributes-empty class="py-8 text-center text-gray-500 dark:text-gray-400 {{ ($tributes->isEmpty() && !isset($highlightTribute)) ? '' : 'hidden' }}">No tributes yet. Be the first to leave one.</p>
                        </div>
                        @if ($tributes->hasPages())
                            <div class="mt-4">{{ $tributes->links() }}</div>
                        @endif
                        <div id="tribute-form-anchor" class="mt-8 scroll-mt-4"></div>
                        <div id="tribute-note-ajax" class="mt-4 space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <h3 class="font-medium text-gray-900 dark:text-white/90">Leave a Tribute</h3>
                            @if (!$isAuthenticated)
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your name</label>
                                        <input type="text" id="tribute-note-name" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm" placeholder="Your name" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your email</label>
                                        <input type="email" id="tribute-note-email" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                                    </div>
                                </div>
                            @endif
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">What kind of tribute is this?</label>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                                        <input type="radio" name="tribute-type" value="flower" class="sr-only" />Flower
                                    </label>
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                                        <input type="radio" name="tribute-type" value="candle" class="sr-only" />Candle
                                    </label>
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                                        <input type="radio" name="tribute-type" value="note" class="sr-only" checked />Note
                                    </label>
                                </div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your message</label>
                                <div id="tribute-editor" class="min-h-[120px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                <input type="hidden" id="tribute-note-message" />
                            </div>
                            <button type="button" id="tribute-note-submit" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Leave Tribute</button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Column 3: Views & Shares stats + Leave tribute --}}
            <aside class="md:col-span-12 lg:col-span-3">
                <div class="lg:sticky lg:top-16 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4 sm:gap-6">
                    @php $stats = $memorialStats ?? ['views_today' => 0, 'views_last_week' => 0, 'views_all_time' => 0, 'shares_today' => 0, 'shares_last_week' => 0, 'shares_all_time' => 0]; @endphp
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm">
                        <div class="border-b border-gray-100 dark:border-gray-800 px-4 py-3">
                            <h3 class="font-semibold text-gray-900 dark:text-white/90">Views & Shares</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Unique people who have visited or shared this memorial</p>
                        </div>
                        <div class="p-4 space-y-4">
                            <div>
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Views</label>
                                <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-views-today>{{ $stats['views_today'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Today</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-views-week>{{ $stats['views_last_week'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Last Week</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-views-all>{{ $stats['views_all_time'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">All Time</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Shares</label>
                                <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-shares-today>{{ $stats['shares_today'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Today</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-shares-week>{{ $stats['shares_last_week'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Last Week</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 dark:bg-white/[0.03] p-2">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-stats-shares-all>{{ $stats['shares_all_time'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">All Time</p>
                                    </div>
                                </div>
                            </div>
                            @if ($quotaInfo['share_memories'] ?? false)
                                @php $deceasedFirstName = \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'their'); @endphp
                                <div class="border-t border-gray-100 dark:border-gray-800 pt-4 mt-4">
                                    <button type="button" id="invite-share-btn" data-share-url="{{ url()->current() }}" class="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-dashed border-brand-400 dark:border-brand-500 bg-brand-50/30 dark:bg-brand-500/10 px-4 py-3 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-500/20 transition">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                        Invite {{ $deceasedFirstName }}'s family and friends
                                    </button>
                                    <div id="invite-share-dropdown" class="mt-2 hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2">
                                        <button type="button" data-share="invite" data-share-url="{{ url()->current() }}" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Copy link</button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Subscribe to memorial (plan-gated) --}}
                    @if ($quotaInfo['guest_notifications'] ?? false)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm overflow-hidden"
                         x-data="{
                            subscribed: false,
                            loading: true,
                            submitting: false,
                            showForm: false,
                            guestName: '',
                            guestEmail: '',
                            subName: '',
                            notifyLifeChapters: true,
                            notifyTributes: true,
                            isAuth: {{ $isAuthenticated ? 'true' : 'false' }},
                            baseUrl: '{{ route('memorial.api.tribute', ['slug' => $memorial->slug]) }}'.replace(/\/tribute$/, ''),
                            csrf: document.querySelector('meta[name=csrf-token]')?.content,

                            init() {
                                if (this.isAuth) {
                                    this._check();
                                } else {
                                    this.loading = false;
                                }
                            },

                            _fetchOpts(method, body) {
                                return {
                                    method,
                                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                    body: body ? JSON.stringify(body) : null,
                                };
                            },

                            _check(email) {
                                const url = this.baseUrl + '/subscribe/check' + (email ? '?email=' + encodeURIComponent(email) : '');
                                fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                                    .then(r => r.json())
                                    .then(data => {
                                        this.loading = false;
                                        if (data.subscribed) {
                                            this.subscribed = true;
                                            this.subName = data.subscription.name;
                                            this.notifyLifeChapters = data.subscription.notify_life_chapters;
                                            this.notifyTributes = data.subscription.notify_tributes;
                                        }
                                    })
                                    .catch(() => { this.loading = false; });
                            },

                            handleSubscribe() {
                                if (this.isAuth) {
                                    this._doSubscribe();
                                } else {
                                    this.showForm = true;
                                    this.$nextTick(() => this.$refs.subNameInput?.focus());
                                }
                            },

                            submitGuestForm() {
                                if (!this.guestName.trim() || !this.guestEmail.trim()) return;
                                this._doSubscribe(this.guestName.trim(), this.guestEmail.trim());
                            },

                            _doSubscribe(name, email) {
                                this.submitting = true;
                                const body = { notify_life_chapters: this.notifyLifeChapters, notify_tributes: this.notifyTributes };
                                if (name) body.guest_name = name;
                                if (email) body.guest_email = email;
                                fetch(this.baseUrl + '/subscribe', this._fetchOpts('POST', body))
                                    .then(r => r.json())
                                    .then(data => {
                                        this.submitting = false;
                                        if (data.success) {
                                            this.subscribed = true;
                                            this.subName = data.subscription.name;
                                            this.notifyLifeChapters = data.subscription.notify_life_chapters;
                                            this.notifyTributes = data.subscription.notify_tributes;
                                            this.showForm = false;
                                            if (email) this.guestEmail = email;
                                        } else if (data.error) {
                                            $toast('error', data.error);
                                        }
                                    })
                                    .catch(() => { this.submitting = false; $toast('error', 'Something went wrong.'); });
                            },

                            updatePrefs() {
                                const body = { notify_life_chapters: this.notifyLifeChapters, notify_tributes: this.notifyTributes };
                                if (!this.isAuth && this.guestEmail) body.guest_email = this.guestEmail;
                                fetch(this.baseUrl + '/subscribe', this._fetchOpts('PUT', body));
                            },

                            unsubscribe() {
                                const body = {};
                                if (!this.isAuth && this.guestEmail) body.guest_email = this.guestEmail;
                                fetch(this.baseUrl + '/subscribe', this._fetchOpts('DELETE', body))
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.success) {
                                            this.subscribed = false;
                                            this.subName = '';
                                            this.notifyLifeChapters = true;
                                            this.notifyTributes = true;
                                        }
                                    });
                            }
                         }" x-cloak>
                        <div class="p-4">
                            {{-- Loading --}}
                            <template x-if="loading">
                                <div class="flex items-center justify-center py-4">
                                    <svg class="h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                </div>
                            </template>

                            {{-- Not subscribed --}}
                            <template x-if="!loading && !subscribed && !showForm">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/20">
                                            <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Stay Updated</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Get notified about new stories &amp; tributes</p>
                                        </div>
                                    </div>
                                    <button @click="handleSubscribe()" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition active:scale-[0.98]">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        Subscribe
                                    </button>
                                </div>
                            </template>

                            {{-- Guest form --}}
                            <template x-if="!loading && !subscribed && showForm">
                                <div>
                                    <div class="flex items-center gap-2 mb-3">
                                        <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                        </button>
                                        <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Subscribe</h3>
                                    </div>
                                    <form @submit.prevent="submitGuestForm()" class="space-y-3">
                                        <input x-model="guestName" x-ref="subNameInput" type="text" required placeholder="Your name" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3.5 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                        <input x-model="guestEmail" type="email" required placeholder="your@email.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3.5 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                        <div class="space-y-2 rounded-lg bg-gray-50 dark:bg-white/[0.03] p-3">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Notify me about:</p>
                                            <label class="flex items-center gap-2.5 cursor-pointer">
                                                <input type="checkbox" x-model="notifyLifeChapters" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">New life chapters &amp; stories</span>
                                            </label>
                                            <label class="flex items-center gap-2.5 cursor-pointer">
                                                <input type="checkbox" x-model="notifyTributes" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Tributes (flowers, candles, notes)</span>
                                            </label>
                                        </div>
                                        <button type="submit" :disabled="submitting" class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition active:scale-[0.98] disabled:opacity-50">
                                            <template x-if="submitting"><svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></template>
                                            <span x-text="submitting ? 'Subscribing...' : 'Subscribe'"></span>
                                        </button>
                                    </form>
                                </div>
                            </template>

                            {{-- Subscribed: show preferences --}}
                            <template x-if="!loading && subscribed">
                                <div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-500/20">
                                            <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Subscribed</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Hi <span x-text="subName" class="font-medium text-gray-700 dark:text-gray-300"></span>, you'll get notified.</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 space-y-2 rounded-lg bg-gray-50 dark:bg-white/[0.03] p-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notification preferences</p>
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" x-model="notifyLifeChapters" @change="updatePrefs()" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Life chapters &amp; stories</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 cursor-pointer">
                                            <input type="checkbox" x-model="notifyTributes" @change="updatePrefs()" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Tributes (flowers, candles, notes)</span>
                                        </label>
                                    </div>
                                    <button @click="unsubscribe()" class="mt-3 w-full rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 hover:border-red-200 dark:hover:border-red-500/30 transition">
                                        Unsubscribe
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                    @endif

                    {{-- Leave a Tribute --}}
                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 shadow-theme-sm">
                        <h3 class="font-semibold text-gray-900 dark:text-white/90">Leave a Tribute</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Honor their memory with a flower, candle, or note.</p>
                        @if (isset($quotaInfo) && $quotaInfo['tributes']['max'] > 0 && !$quotaInfo['tributes']['allowed'])
                            <p class="mt-3 text-xs font-medium text-red-500 dark:text-red-400">Tribute limit reached ({{ $quotaInfo['tributes']['current'] }}/{{ $quotaInfo['tributes']['max'] }}).</p>
                        @else
                            @if (isset($quotaInfo) && $quotaInfo['tributes']['max'] > 0)
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ $quotaInfo['tributes']['current'] }}/{{ $quotaInfo['tributes']['max'] }} tributes used</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" data-tribute-btn="flower" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Flower</button>
                                <button type="button" data-tribute-btn="candle" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Candle</button>
                                <button type="button" data-tribute-btn="note" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Note</button>
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </main>

    {{-- Background music: floating mute/unmute button (plan-gated) --}}
    @php
        $bgMusicAllowed = $quotaInfo['background_music'] ?? false;
        $bgMusicUrl = ($bgMusicAllowed && $memorial->background_music) ? \App\Helpers\StorageHelper::publicUrl($memorial->background_music) : null;
    @endphp
    <div id="bg-music-widget"
        x-data="{
            hasMusic: {{ $bgMusicUrl ? 'true' : 'false' }},
            muted: false,
            showTooltip: false,
            storageKey: 'bgm_muted_{{ $memorial->slug }}',
            _bound: false,
            init() {
                this.muted = localStorage.getItem(this.storageKey) === '1';
                const audio = this.$refs.bgAudio;
                if (!audio || !audio.src) return;
                audio.volume = 0.3;
                if (this.muted) { audio.muted = true; return; }
                audio.muted = false;
                this._tryAutoplay(audio);
            },
            _tryAutoplay(audio) {
                if (this._bound) return;
                const attempt = () => {
                    if (this.muted || !audio.paused) return;
                    audio.play().then(() => { this._cleanup(); }).catch(() => {});
                };
                attempt();
                audio.addEventListener('canplaythrough', () => attempt(), { once: true });
                setTimeout(() => attempt(), 500);
                setTimeout(() => attempt(), 1500);
                this._bound = true;
                this._handler = () => {
                    if (this.muted) return;
                    audio.play().then(() => { this._cleanup(); }).catch(() => {});
                };
                ['click','touchstart','touchend','scroll','keydown','pointerdown','pointerup'].forEach(e =>
                    document.addEventListener(e, this._handler, { capture: true })
                );
            },
            _cleanup() {
                if (!this._handler) return;
                ['click','touchstart','touchend','scroll','keydown','pointerdown','pointerup'].forEach(e =>
                    document.removeEventListener(e, this._handler, { capture: true })
                );
                this._handler = null;
            },
            toggle() {
                this.muted = !this.muted;
                const audio = this.$refs.bgAudio;
                audio.muted = this.muted;
                localStorage.setItem(this.storageKey, this.muted ? '1' : '0');
                if (!this.muted && audio.paused) audio.play().catch(() => {});
            },
            setMusic(url) {
                this.hasMusic = true;
                const audio = this.$refs.bgAudio;
                audio.src = url;
                audio.load();
                this.muted = false;
                localStorage.setItem(this.storageKey, '0');
                audio.muted = false;
                audio.play().catch(() => {});
            },
            removeMusic() {
                this.hasMusic = false;
                const audio = this.$refs.bgAudio;
                audio.pause();
                audio.src = '';
            }
        }"
        x-show="hasMusic"
        x-cloak
        class="fixed bottom-6 right-6 z-50">

        @if ($bgMusicUrl)
            <audio x-ref="bgAudio" loop preload="auto" autoplay src="{{ $bgMusicUrl }}"></audio>
        @else
            <audio x-ref="bgAudio" loop preload="auto"></audio>
        @endif

        <div class="flex flex-col items-center gap-1.5"
            @mouseenter="showTooltip = true" @mouseleave="showTooltip = false">

            {{-- Tooltip --}}
            <div x-show="showTooltip" x-cloak x-transition
                class="rounded-lg bg-gray-900 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-white shadow-lg whitespace-nowrap">
                <span x-text="muted ? 'Tap to unmute' : 'Tap to mute'"></span>
            </div>

            {{-- Mute/Unmute button --}}
            <button type="button" @click="toggle()"
                :class="muted ? 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' : 'bg-brand-500 text-white shadow-lg shadow-brand-500/30'"
                class="flex h-12 w-12 items-center justify-center rounded-full transition-all duration-300 hover:scale-110 active:scale-95">
                {{-- Unmuted: music note with animated rings --}}
                <template x-if="!muted">
                    <span class="relative flex items-center justify-center">
                        <span class="absolute h-10 w-10 animate-ping rounded-full bg-brand-400/20"></span>
                        <svg class="relative h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                    </span>
                </template>
                {{-- Muted: muted speaker --}}
                <template x-if="muted">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                </template>
            </button>

            {{-- Label --}}
            <span class="text-[10px] font-medium leading-tight text-center"
                :class="muted ? 'text-gray-400 dark:text-gray-500' : 'text-brand-600 dark:text-brand-400'"
                x-text="muted ? 'Muted' : 'Playing'"></span>
        </div>
    </div>

    {{-- Background music: upload control for owner/admin (plan-gated) --}}
    @if ($canEdit && ($quotaInfo['background_music'] ?? false))
        <div id="bg-music-admin"
            x-data="{ uploading: false }"
            class="fixed bottom-6 right-20 z-50">
            <div class="flex flex-col items-center gap-1.5">
                <label :class="uploading ? 'opacity-50 pointer-events-none' : ''"
                    class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 shadow-md transition hover:scale-110 hover:border-brand-300 hover:text-brand-500 active:scale-95">
                    <input type="file" accept="audio/*" class="hidden"
                        @change="
                            if (!$event.target.files[0]) return;
                            uploading = true;
                            const fd = new FormData();
                            fd.append('file', $event.target.files[0]);
                            fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                            fetch(document.querySelector('[data-memorial-slug]').dataset.tributeUrl.replace(/\/tribute$/, '/background-music'), {
                                method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd
                            }).then(r => r.json()).then(data => {
                                uploading = false;
                                if (data.success) {
                                    const widget = document.getElementById('bg-music-widget');
                                    if (widget && widget.__x) widget.__x.$data.setMusic(data.url);
                                    else if (typeof Alpine !== 'undefined') Alpine.$data(widget).setMusic(data.url);
                                } else { $toast('error', data.error || 'Upload failed'); }
                            }).catch(() => { uploading = false; $toast('error', 'Upload failed'); });
                            $event.target.value = '';
                        ">
                    <template x-if="!uploading">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                    </template>
                    <template x-if="uploading">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500"></div>
                    </template>
                </label>
                <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $bgMusicUrl ? 'Change' : 'Add' }} Music</span>
            </div>
        </div>
    @endif
</div>

@vite('resources/js/memorial-public.js')
@endsection
