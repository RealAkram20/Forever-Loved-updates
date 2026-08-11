@php
    $embedInBiography = $embedInBiography ?? false;
    $chapterShareUrl = route('memorial.chapter.public', ['memorial_slug' => $memorial->slug, 'share_id' => $post->share_id]);
    $marker = $post->tribute_type;
    $markerVerb = $post->markerVerb();
@endphp
<article id="{{ $embedInBiography ? 'chapter-preview-' : 'chapter-' }}{{ $post->id }}" class="group/post relative overflow-visible rounded-xl border border-gray-300 dark:border-gray-800 bg-white dark:bg-white/[0.03] life-feed-post" data-post-id="{{ $post->id }}" data-chapter-id="{{ $post->story_chapter_id ?? '' }}" data-marker="{{ $marker ?: 'story' }}">
    <div class="p-4">
        <div class="flex items-center gap-3">
            @if ($post->user?->profile_photo_url)
                <img src="{{ \App\Helpers\ResponsiveImage::url($post->user->profile_photo, 160) ?? $post->user->profile_photo_url }}" alt="{{ $post->user->name }}" class="h-10 w-10 shrink-0 rounded-full object-cover" loading="lazy" decoding="async" />
            @else
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-600 dark:bg-brand-500/30 dark:text-brand-400">
                    {{ strtoupper(substr($post->user?->name ?? $memorial->full_name ?? '?', 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0 flex-1">
                <p class="truncate font-medium text-gray-900 dark:text-white/90">{{ $post->user?->name ?? $memorial->full_name ?? 'Anonymous' }}</p>
                {{-- The marker reads as something the author did, next to their name, rather
                     than as a label filed against their writing. --}}
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="time-ago" data-created-at="{{ $post->created_at->toIso8601String() }}">{{ $post->created_at->diffForHumans() }}</span>
                    <span data-post-marker-verb>@if ($markerVerb) · {{ $markerVerb }} @endif</span>
                    @if ($post->storyChapter?->title) · {{ $post->storyChapter->title }} @endif
                </p>
            </div>
            @if ($canEdit && ! $embedInBiography)
                <button type="button" data-post-edit-trigger="{{ $post->id }}" class="memorial-edit-fab rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit story">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            @endif
            {{-- The artwork itself, at the size it is legible and no larger. It is the same
                 drawing as the card somebody taps to light a candle, so the gesture and the
                 writing that carries it are recognisably one thing. An unmarked story shows
                 nothing here — most stories are unmarked, and a row of placeholders would
                 make the plain ones look unfinished. --}}
            <span data-post-marker-art class="pointer-events-none block h-9 w-9 shrink-0 {{ $marker ? '' : 'hidden' }}" aria-hidden="true">
                @if ($marker)
                    @include('pages.memorials.partials.tribute-art', ['type' => $marker, 'image' => $marker.'.png'])
                @endif
            </span>
        </div>
        <div data-post-display="{{ $post->id }}">
            @if ($embedInBiography && ($post->title || $post->content))
                <div x-data="{ expanded: false }" class="min-w-0">
                    <div
                        data-post-text-body
                        class="transition-[max-height] duration-200"
                        :class="expanded ? 'max-h-none overflow-visible' : 'max-h-[5.25rem] overflow-hidden'"
                    >
                        @if ($post->title)
                            <h3 class="mt-2 font-medium text-gray-900 dark:text-white/90">{{ $post->title }}</h3>
                        @endif
                        @if ($post->content)
                            <div class="mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none break-words">{!! \App\Helpers\HtmlHelper::sanitize($post->content) !!}</div>
                        @endif
                    </div>
                    <button type="button" @click="expanded = !expanded" class="btn btn-link btn-sm mt-1.5">
                        <span x-text="expanded ? 'Show less' : 'Read all'"></span>
                    </button>
                </div>
            @else
                @if ($post->title)
                    <h3 class="mt-2 font-medium text-gray-900 dark:text-white/90">{{ $post->title }}</h3>
                @endif
                @if ($post->content)
                    <div class="mt-2 text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none break-words overflow-hidden">{!! \App\Helpers\HtmlHelper::sanitize($post->content) !!}</div>
                @endif
            @endif
            @if ($post->media->isNotEmpty())
                <div class="mt-3 space-y-3">
                    @foreach ($post->media as $m)
                        @if ($m->type === 'photo')
                            <img {!! \App\Helpers\ResponsiveImage::attrs($m->path, '(min-width: 768px) 42rem, 100vw') !!} alt="{{ $m->caption }}" class="max-w-full rounded-lg" loading="lazy" decoding="async" />
                        @elseif ($m->type === 'video')
                            <x-media.video-player :src="$m->url" :caption="$m->caption" />
                        @elseif ($m->type === 'music')
                            <x-media.audio-player :src="$m->url" :caption="$m->caption" :filename="$m->filename" />
                        @endif
                    @endforeach
                </div>
            @endif
            @if ($post->location)
                <div class="mt-3 flex items-center gap-2 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $post->location }}</span>
                </div>
            @endif
        </div>
        @if ($canEdit && ! $embedInBiography)
            <div data-post-edit="{{ $post->id }}" class="mt-3 hidden space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Title</label>
                    <input type="text" data-post-edit-title="{{ $post->id }}" value="{{ $post->title ?? '' }}" placeholder="Post title (optional)" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Content</label>
                    <div id="post-editor-{{ $post->id }}" class="min-h-[120px] rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900"></div>
                </div>
                <fieldset>
                    <legend class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                        Mark it as <span class="font-normal text-gray-400 dark:text-gray-500">— tap again to clear</span>
                    </legend>
                    <div class="story-marker-row flex flex-wrap gap-2" data-marker-group>
                        @foreach ([['flower', 'Flower'], ['candle', 'Candle'], ['prayer', 'Prayer']] as [$value, $label])
                            <label class="story-marker-chip story-marker-chip--sm">
                                <input type="radio" name="post-marker-{{ $post->id }}" value="{{ $value }}" class="sr-only" @checked(($marker ?? '') === $value) />
                                <span class="story-marker-chip__art" aria-hidden="true">
                                    @include('pages.memorials.partials.tribute-art', ['type' => $value, 'image' => $value.'.png'])
                                </span>
                                <span class="story-marker-chip__label">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" data-post-save="{{ $post->id }}" class="btn btn-primary btn-md">Save</button>
                    <button type="button" data-post-cancel="{{ $post->id }}" class="btn btn-secondary btn-md">Cancel</button>
                    <button type="button" data-post-delete="{{ $post->id }}" class="btn btn-danger-soft btn-md ml-auto">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </div>
            </div>
        @endif
    </div>
    <div class="relative z-10 flex items-center gap-4 border-t border-gray-100 px-4 py-2 dark:border-gray-800">
        <div class="flex items-center gap-1" data-reaction-container="{{ $post->id }}">
            <button type="button" data-reaction-btn data-reactionable-type="post" data-reactionable-id="{{ $post->id }}" data-reaction-type="like" class="inline-flex items-center gap-1.5 text-gray-600 hover:text-rose-500 dark:text-gray-400 dark:hover:text-rose-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span data-post-id="{{ $post->id }}" data-reaction-count class="text-sm text-gray-600 dark:text-gray-400">{{ $post->reactions->count() }}</span>
            </button>
        </div>
        {{-- One button, one destination. The preview strip in the Biography tab and the
             card in the feed used to open two different things; both open the sheet. --}}
        <div class="flex items-center gap-1" data-comment-container="{{ $post->id }}">
            <button type="button" data-open-comments="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span data-post-id="{{ $post->id }}" data-comment-count class="text-sm tabular-nums text-gray-600 dark:text-gray-400">{{ $post->comments->count() + $post->comments->sum(fn ($c) => $c->replies->count()) }}</span>
            </button>
        </div>
        @if ($quotaInfo['share_memories'] ?? false)
            @if ($embedInBiography)
                <div class="relative ml-auto" data-share-container>
                    <button type="button" data-share-toggle data-share-url="{{ $chapterShareUrl }}" class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                    <div data-share-dropdown class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                        @include('pages.memorials.partials.share-dropdown', ['shareUrl' => $chapterShareUrl])
                    </div>
                </div>
            @else
                <div class="relative ml-auto" data-share-container="{{ $post->id }}">
                    <button type="button" data-share-toggle data-share-url="{{ $chapterShareUrl }}" data-post-id="{{ $post->id }}" class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                    <div data-share-dropdown="{{ $post->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                        @include('pages.memorials.partials.share-dropdown', ['shareUrl' => $chapterShareUrl])
                    </div>
                </div>
            @endif
        @endif
    </div>
</article>
