{{--
    The Stories half of the Tributes & Stories tab: chapter filters, the published
    story feed, and the form for adding one. Was its own "Life" tab until stories and
    tributes were folded together.
--}}
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
<div id="edit-chapter-modal" role="dialog" aria-modal="true" aria-labelledby="edit-chapter-modal-title" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-xl">
        <h3 id="edit-chapter-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white/90">Edit Chapter</h3>
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
                <button type="submit" class="btn btn-primary btn-md">Save</button>
                <button type="button" data-close-edit-chapter-modal class="btn btn-secondary btn-md">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
<div class="space-y-4" id="life-feed">
    @php $lifePosts = $memorial->posts->where('is_published', true)->sortByDesc('created_at'); @endphp
    @foreach ($lifePosts as $post)
        @include('pages.memorials.partials.life-post-article', [
            'post' => $post,
            'memorial' => $memorial,
            'canEdit' => $canEdit,
            'quotaInfo' => $quotaInfo,
            'embedInBiography' => false,
        ])
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
                <input type="text" name="title" placeholder="Title (optional)" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm" />
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
                <button type="submit" class="btn btn-primary btn-md">Post</button>
                <button type="button" id="cancel-story-btn" class="btn btn-secondary btn-md">Cancel</button>
            </div>
        </form>
    </div>
