{{--
    The Stories tab: one composer, one set of filters, one feed.

    Tributes and stories used to be two sub-tabs of this panel, each with its own list and
    its own way in. They are the same act — somebody saying something — so they are one
    list now, and what used to be the choice of tab is a marker on the thing you write.

    @param \Illuminate\Support\Collection $stories  Published stories, newest first.
    @param array $storyCounts                       total + per-marker tallies for the chips.
--}}
@php
    $markerFilters = [
        ['value' => 'story', 'label' => 'Stories', 'art' => 'story'],
        ['value' => 'flower', 'label' => 'Flowers', 'art' => 'flower'],
        ['value' => 'candle', 'label' => 'Candles', 'art' => 'candle'],
        ['value' => 'prayer', 'label' => 'Prayers', 'art' => 'prayer'],
    ];

    // Filters earn their place only once there is something to sift. Below that they are
    // four controls sitting above three items, which is furniture, not help — and the one
    // thing this tab is meant to stop being is busy.
    $kindsPresent = collect($markerFilters)->filter(fn ($f) => ($storyCounts[$f['value']] ?? 0) > 0);
    $showMarkerFilters = ($storyCounts['total'] ?? 0) >= 5 && $kindsPresent->count() > 1;

    $storyChapters = $memorial->storyChapters;
@endphp

@include('pages.memorials.partials.story-composer')

@if ($showMarkerFilters)
    <div class="story-filters mt-5 flex flex-wrap gap-2" role="group" aria-label="Filter stories">
        <button type="button" class="story-filter story-filter--all is-active" data-story-marker="">
            All <span class="story-filter__count tabular-nums">{{ $storyCounts['total'] }}</span>
        </button>
        @foreach ($kindsPresent as $filter)
            <button type="button" class="story-filter" data-story-marker="{{ $filter['value'] }}">
                <span class="story-filter__art" aria-hidden="true">
                    @include('pages.memorials.partials.tribute-art', ['type' => $filter['art'], 'image' => $filter['art'].'.png'])
                </span>
                {{ $filter['label'] }}
                <span class="story-filter__count tabular-nums">{{ $storyCounts[$filter['value']] }}</span>
            </button>
        @endforeach
    </div>
@endif

@if ($storyChapters->isNotEmpty())
    {{-- The memorial owner's own grouping, kept separate from the marker chips above: one
         is what a visitor said their story was, the other is where the family filed it. --}}
    <div class="mt-4 flex flex-wrap items-center gap-2" id="chapter-filters">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Chapters</span>
        <button type="button" class="chapter-filter rounded-md bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-600 dark:bg-brand-500/20 dark:text-brand-400" data-chapter="">All</button>
        @foreach ($storyChapters as $chapter)
            @php $chapterPostCount = $stories->where('story_chapter_id', $chapter->id)->count(); @endphp
            <div class="group relative inline-flex items-center" data-chapter-pill="{{ $chapter->id }}">
                <button type="button" class="chapter-filter relative rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/10" data-chapter="{{ $chapter->id }}">
                    {{ $chapter->title }}
                    <span class="ml-1 inline-block h-2 w-2 rounded-full {{ $chapterPostCount >= 3 ? 'bg-emerald-500' : ($chapterPostCount >= 1 ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600') }}" title="{{ $chapterPostCount }} {{ Str::plural('story', $chapterPostCount) }}"></span>
                </button>
                @if ($canEdit)
                    <div class="absolute -right-1 -top-1 z-10 flex items-center gap-0.5">
                        <button type="button" data-edit-chapter="{{ $chapter->id }}" data-chapter-title="{{ $chapter->title }}" data-chapter-desc="{{ $chapter->description }}"
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500 text-white shadow-sm ring-2 ring-white transition hover:bg-brand-600 dark:ring-gray-900" title="Edit chapter">
                            <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button type="button" data-delete-chapter="{{ $chapter->id }}"
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-white shadow-sm ring-2 ring-white transition hover:bg-red-600 dark:ring-gray-900" title="Delete chapter">
                            <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif

{{-- Edit chapter modal --}}
@if ($canEdit && $storyChapters->isNotEmpty())
<div id="edit-chapter-modal" role="dialog" aria-modal="true" aria-labelledby="edit-chapter-modal-title" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-800">
        <h3 id="edit-chapter-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white/90">Edit Chapter</h3>
        <form id="edit-chapter-form" class="mt-4 space-y-4">
            <input type="hidden" id="edit-chapter-id" />
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Title</label>
                <input type="text" id="edit-chapter-title" required
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-900" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
                <textarea id="edit-chapter-desc" rows="2"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-900"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-md">Save</button>
                <button type="button" data-close-edit-chapter-modal class="btn btn-secondary btn-md">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="mt-5 space-y-4" id="life-feed">
    @foreach ($stories as $post)
        @include('pages.memorials.partials.life-post-article', [
            'post' => $post,
            'memorial' => $memorial,
            'canEdit' => $canEdit,
            'quotaInfo' => $quotaInfo,
            'embedInBiography' => false,
        ])
    @endforeach
</div>

{{-- Two empty states, because they are two different situations. One says the wall is
     bare; the other says your filter is. --}}
<div id="story-feed-empty" class="{{ $stories->isEmpty() ? '' : 'hidden' }}">
    <div class="rounded-xl border border-dashed border-gray-200 px-6 py-10 text-center dark:border-gray-700">
        <p class="text-gray-600 dark:text-gray-300">Nothing has been written here yet.</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Be the first — a sentence is enough.</p>
        <button type="button" data-open-story-composer class="btn btn-primary btn-sm mt-4">Write something</button>
    </div>
</div>
<p id="story-filter-empty" class="hidden py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nothing here yet. Try another filter.</p>