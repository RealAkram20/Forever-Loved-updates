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
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" data-memorial-slug="{{ $memorial->slug }}" data-tribute-url="{{ route('memorial.api.tribute', ['slug' => $memorial->slug]) }}" data-can-edit="{{ $canEdit ? '1' : '0' }}" data-is-authenticated="{{ $isAuthenticated ? '1' : '0' }}" data-can-upload="{{ $canEdit ? '1' : '0' }}" data-scroll-tribute="{{ $scrollToTributeId ?? '' }}" data-scroll-chapter="{{ $scrollToChapterId ?? '' }}">
    <x-home-header />

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
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Column 1: Profile card (narrow) --}}
            <aside class="lg:col-span-3 xl:col-span-3">
                <div class="sticky top-20 space-y-4">
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm">
                        <div class="p-6">
                            <div class="flex flex-col items-center text-center">
                                {{-- Profile photo with upload --}}
                                <div class="relative group mb-4">
                                    <div class="h-24 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                        @if ($memorial->profile_photo_path)
                                            <img src="{{ $memorial->profile_photo_url ?? '' }}" alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-3xl text-gray-400 dark:text-gray-500">?</div>
                                        @endif
                                    </div>
                                    @if ($canEdit)
                                        <label class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 opacity-0 transition group-hover:opacity-100">
                                            <input type="file" id="profile-photo-input" accept="image/*" class="hidden" />
                                            <span class="text-white text-xs">Change</span>
                                        </label>
                                    @endif
                                </div>
                                <div data-editable="full_name" class="relative group">
                                    <h2 data-display class="text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->full_name ?: 'Full name' }}</h2>
                                    @if ($canEdit)
                                        <button type="button" data-edit-trigger class="absolute -right-6 top-0 rounded p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:text-brand-500">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </button>
                                        <div data-edit class="hidden mt-1">
                                            <input type="text" value="{{ $memorial->full_name }}" class="w-full rounded border px-2 py-1 text-sm" />
                                            <button type="button" data-save class="mt-1 text-xs text-brand-500">Save</button>
                                        </div>
                                    @endif
                                </div>
                                @if ($canEdit || ($memorial->designation && !$memorial->cause_of_death_private))
                                    <div data-editable="designation" class="relative group mt-0.5">
                                        <p data-display class="text-sm text-gray-500 dark:text-gray-400">{{ $memorial->designation ?: ($canEdit ? 'Add designation' : '') }}</p>
                                        @if ($canEdit)
                                            <button type="button" data-edit-trigger class="absolute -right-6 top-0 rounded p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:text-brand-500">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            <div data-edit class="hidden">
                                                <input type="text" value="{{ $memorial->designation }}" class="w-full rounded border px-2 py-1 text-sm" placeholder="Designation" />
                                                <button type="button" data-save class="mt-1 text-xs text-brand-500">Save</button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                                <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-success-50 dark:bg-success-500/20 px-3 py-1 text-xs font-medium text-success-700 dark:text-success-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                    In Loving Memory
                                </span>
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
                                <div data-editable="dates" class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4 text-center">
                                    <p data-display class="text-sm text-gray-600 dark:text-gray-400">
                                        @if ($memorial->date_of_birth){{ $memorial->date_of_birth->format('Y-m-d') }}@endif
                                        @if ($memorial->date_of_birth && $memorial->date_of_passing) &ndash; @endif
                                        @if ($memorial->date_of_passing){{ $memorial->date_of_passing->format('Y-m-d') }}@endif
                                        @if (!$memorial->date_of_birth && !$memorial->date_of_passing && $canEdit) Add dates @endif
                                    </p>
                                    @if ($canEdit)
                                        <div data-edit class="hidden mt-2 space-y-1">
                                            <input type="date" data-date-type="birth" value="{{ $memorial->date_of_birth?->format('Y-m-d') }}" class="rounded border px-2 py-1 text-sm" />
                                            <input type="date" data-date-type="death" value="{{ $memorial->date_of_passing?->format('Y-m-d') }}" class="rounded border px-2 py-1 text-sm ml-1" />
                                            <button type="button" data-save class="block mt-1 text-xs text-brand-500">Save</button>
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
            <section class="lg:col-span-6 xl:col-span-6">
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] shadow-theme-sm">
                    {{-- Tab buttons (equal width) --}}
                    <div class="flex border-b border-gray-100 dark:border-gray-800">
                        <button type="button" data-tab-panel="biography" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-brand-600 dark:text-brand-400 border-b-2 border-brand-500 bg-brand-50/50 dark:bg-brand-500/10">Biography</button>
                        <button type="button" data-tab-panel="life" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Life</button>
                        <button type="button" data-tab-panel="gallery" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Gallery</button>
                        <button type="button" data-tab-panel="tributes" class="memorial-tab-btn min-w-0 flex-1 px-2 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Tributes</button>
                    </div>

                    {{-- Tab: Biography (first) --}}
                    <div id="tab-biography" class="memorial-tab-panel p-6">
                        <div data-editable="biography" class="relative group">
                            @if ($canEdit)
                                <button type="button" data-edit-trigger class="absolute right-0 top-0 rounded p-1 text-gray-400 opacity-0 group-hover:opacity-100 hover:text-brand-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            @endif
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Biography</h2>
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
                    <div id="tab-life" class="memorial-tab-panel hidden p-6">
                        <div class="mb-4">
                            <button type="button" id="add-story-btn-top" class="w-full rounded-xl border-2 border-dashed border-brand-400 dark:border-brand-500 bg-brand-50/50 dark:bg-brand-500/10 px-4 py-3 text-sm font-semibold text-brand-600 dark:text-brand-400 hover:bg-brand-100 dark:hover:bg-brand-500/20 transition">
                                + Your Chapter
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <div class="flex flex-wrap gap-1">
                                <button type="button" class="chapter-filter rounded-md bg-brand-50 dark:bg-brand-500/20 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400" data-chapter="">All</button>
                                @foreach ($memorial->storyChapters as $chapter)
                                    <button type="button" class="chapter-filter rounded-md px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/10" data-chapter="{{ $chapter->id }}">{{ $chapter->title }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="space-y-4" id="life-feed">
                            @php $lifePosts = $memorial->posts->where('is_published', true)->sortByDesc('created_at'); @endphp
                            @foreach ($lifePosts as $post)
                                <article id="chapter-{{ $post->id }}" class="relative overflow-visible rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]" data-post-id="{{ $post->id }}" data-chapter-id="{{ $post->story_chapter_id ?? '' }}">
                                    <div class="p-4">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/30 text-brand-600 dark:text-brand-400 text-sm font-semibold">
                                                {{ strtoupper(substr($post->user?->name ?? $memorial->full_name ?? '?', 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white/90">{{ $post->user?->name ?? $memorial->full_name ?? 'Anonymous' }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400"><span class="time-ago" data-created-at="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</span> · {{ $post->storyChapter?->title ?? 'Life' }}</p>
                                            </div>
                                        </div>
                                        @if ($post->title)
                                            <h3 class="mt-2 font-medium text-gray-900 dark:text-white/90">{{ $post->title }}</h3>
                                        @endif
                                        @if ($post->content)
                                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\HtmlHelper::sanitize($post->content) !!}</div>
                                        @endif
                                        @if ($post->media->isNotEmpty())
                                            <div class="mt-3 space-y-2">
                                                @foreach ($post->media as $m)
                                                    @if ($m->type === 'photo')
                                                        <img src="{{ $m->url }}" alt="{{ $m->caption }}" class="max-w-full rounded-lg" />
                                                    @elseif ($m->type === 'video')
                                                        <video src="{{ $m->url }}" controls class="max-w-full rounded-lg"></video>
                                                    @elseif ($m->type === 'music')
                                                        <audio src="{{ $m->url }}" controls class="w-full"></audio>
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
                                    <div class="relative z-10 flex items-center gap-4 border-t border-gray-100 dark:border-gray-800 px-4 py-2">
                                        <div class="flex items-center gap-1" data-reaction-container="{{ $post->id }}">
                                            <button type="button" data-reaction-btn data-reactionable-type="post" data-reactionable-id="{{ $post->id }}" data-reaction-type="like" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-rose-500 dark:hover:text-rose-400">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                                <span data-post-id="{{ $post->id }}" data-reaction-count class="text-sm text-gray-600 dark:text-gray-400">{{ $post->reactions->count() }}</span>
                                            </button>
                                        </div>
                                        <div class="relative flex items-center gap-1" data-comment-container="{{ $post->id }}">
                                            <button type="button" data-comment-toggle data-post-id="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                <span data-post-id="{{ $post->id }}" data-comment-count class="text-sm text-gray-600 dark:text-gray-400">{{ $post->comments->count() + $post->comments->sum(fn($c) => $c->replies->count()) }}</span>
                                            </button>
                                            <div data-comment-dropdown="{{ $post->id }}" class="absolute left-0 top-full z-[9999] mt-1 hidden w-full min-w-[320px] max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                                                <div class="border-b border-gray-100 dark:border-gray-700 p-3">
                                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Add your comment</p>
                                                    <div class="flex gap-2">
                                                        <input type="text" data-comment-input="{{ $post->id }}" placeholder="Write a comment..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm" />
                                                        <button type="button" data-comment-submit data-post-id="{{ $post->id }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Post</button>
                                                    </div>
                                                </div>
                                                <div class="max-h-48 overflow-y-auto p-3" data-comments-list="{{ $post->id }}">
                                                    @foreach ($post->comments as $comment)
                                                        @include('pages.memorials.partials.comment-item', ['comment' => $comment, 'postId' => $post->id])
                                                    @endforeach
                                                </div>
                                                <p data-comments-empty="{{ $post->id }}" class="px-3 py-4 text-center text-sm text-gray-500 {{ $post->comments->isEmpty() ? '' : 'hidden' }}">No comments yet. Add a comment.</p>
                                            </div>
                                        </div>
                                        <div class="relative ml-auto" data-share-container="{{ $post->id }}">
                                            <button type="button" data-share-toggle data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" data-post-id="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                                Share
                                            </button>
                                            <div data-share-dropdown="{{ $post->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1">
                                                <a href="#" data-share="whatsapp" data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">WhatsApp</a>
                                                <a href="#" data-share="facebook" data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Facebook</a>
                                                <a href="#" data-share="linkedin" data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">LinkedIn</a>
                                                <button type="button" data-share="copy" data-share-url="{{ route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]) }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Link</button>
                                            </div>
                                        </div>
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
                                <div class="grid grid-cols-2 gap-4 mb-4">
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

                    {{-- Tab: Gallery (permitted users only) --}}
                    <div id="tab-gallery" class="memorial-tab-panel hidden p-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Gallery</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Photos and videos (under 100MB) from those permitted to contribute.</p>
                        @if ($canEdit)
                            <div class="mt-4">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5">
                                    <input type="file" id="gallery-upload" accept="image/*,video/*" class="hidden" />
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Add photo or video
                                </label>
                            </div>
                        @endif
                        <div class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-3" id="gallery-grid">
                            @php $galleryItems = $memorial->galleryMedia()->orderBy('sort_order')->get(); @endphp
                            @foreach ($galleryItems as $media)
                                @if ($media->type === 'photo')
                                    <a href="{{ $media->url }}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700">
                                        <img src="{{ $media->url }}" alt="{{ $media->caption ?? 'Photo' }}" class="h-full w-full object-cover" loading="lazy" />
                                    </a>
                                @else
                                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700">
                                        <video src="{{ $media->url }}" controls class="h-full w-full object-cover"></video>
                                    </div>
                                @endif
                            @endforeach
                            @if ($galleryItems->isEmpty())
                                <div class="col-span-full rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <p class="text-gray-500 dark:text-gray-400">No photos or videos yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab: Tributes --}}
                    <div id="tab-tributes" class="memorial-tab-panel hidden p-6">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Tributes (<span data-tribute-count>{{ $tributes->total() + (isset($highlightTribute) ? 1 : 0) }}</span>)</h2>
                            <button type="button" id="add-tribute-btn" class="rounded-lg border border-dashed border-brand-400 dark:border-brand-500 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/20">Add a tribute</button>
                        </div>
                        <div class="mt-4 space-y-4" data-tributes-list>
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
                                <div class="grid grid-cols-2 gap-4">
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
            <aside class="lg:col-span-3 xl:col-span-3">
                <div class="sticky top-20 space-y-6">
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
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 shadow-theme-sm">
                        <h3 class="font-semibold text-gray-900 dark:text-white/90">Leave a Tribute</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Honor their memory with a flower, candle, or note.</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" data-tribute-btn="flower" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Flower</button>
                            <button type="button" data-tribute-btn="candle" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Candle</button>
                            <button type="button" data-tribute-btn="note" class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/10">Note</button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</div>

@vite('resources/js/memorial-public.js')
@endsection
