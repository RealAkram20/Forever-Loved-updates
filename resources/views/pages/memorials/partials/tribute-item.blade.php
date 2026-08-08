@php
    $tributeEmbedPreview = $tributeEmbedPreview ?? false;
    $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';
    $initials = collect(explode(' ', $authorName))->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->join('');
    $authorPhoto = $tribute->user?->profile_photo_url;
    $reactionCount = $tribute->reactions->count();
    $commentCount = $tribute->comments->count() + $tribute->comments->sum(fn($c) => $c->replies->count());
    $canEditTribute = ($canEdit ?? false) || (auth()->id() && $tribute->user_id === auth()->id());
    $canDeleteTributeComments = ($canEdit ?? false) && ! $tributeEmbedPreview;
@endphp
<div
    id="tribute-{{ $tributeEmbedPreview ? 'preview-' : '' }}{{ $tribute->id }}"
    data-tribute-id="{{ $tribute->id }}"
    data-tribute-type="{{ $tribute->type }}"
    x-show="tributeFilter === 'all' || tributeFilter === $el.dataset.tributeType"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    class="group rounded-xl border p-4 transition
        @if($tribute->type === 'flower')
            border-violet-200/60 dark:border-violet-800/40 bg-violet-50/40 dark:bg-violet-950/20
        @elseif($tribute->type === 'candle')
            border-amber-200/60 dark:border-amber-800/40 bg-amber-50/40 dark:bg-amber-950/20
        @else
            border-sky-200/60 dark:border-sky-800/40 bg-sky-50/40 dark:bg-sky-950/20
        @endif"
>
    {{-- Header: avatar, name, time, type icon --}}
    <div class="flex items-start gap-3">
        @if($authorPhoto)
            <img src="{{ $authorPhoto }}" alt="{{ $authorName }}" data-tribute-avatar class="h-10 w-10 shrink-0 rounded-full object-cover" />
        @else
            <div data-tribute-avatar-fallback class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold
                @if($tribute->type === 'flower')
                    bg-violet-200/70 dark:bg-violet-800/40 text-violet-700 dark:text-violet-300
                @elseif($tribute->type === 'candle')
                    bg-amber-200/70 dark:bg-amber-800/40 text-amber-700 dark:text-amber-300
                @else
                    bg-sky-200/70 dark:bg-sky-800/40 text-sky-700 dark:text-sky-300
                @endif">
                {{ $initials }}
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900 dark:text-white/90 truncate">{{ $authorName }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 time-ago" data-created-at="{{ $tribute->created_at->toIso8601String() }}">{{ $tribute->created_at->diffForHumans() }}</p>
        </div>
        <div data-tribute-header-icons class="flex items-center gap-1 shrink-0">
            @if($canEditTribute && !$tributeEmbedPreview)
                <button type="button" data-tribute-edit-trigger="{{ $tribute->id }}" class="memorial-edit-fab rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit tribute">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            @endif
            {{-- Type indicator: the same artwork the one-tap card uses, so the flower in the
                 feed and the flower somebody tapped are recognisably the same thing. This is
                 now the only place the card states its type. --}}
            <span data-tribute-art class="pointer-events-none block h-9 w-9 shrink-0" aria-hidden="true">
                @include('pages.memorials.partials.tribute-art', [
                    'type' => $tribute->type,
                    'image' => $tribute->type.'.png',
                ])
            </span>
        </div>
    </div>

    {{-- The message, and nothing else.
         The artwork in the header already says what kind of tribute this is; saying it
         again as an inline icon and a third time as a "FLOWER LEFT" label was three
         statements of one fact, in a card whose only real content is a sentence someone
         wrote. A tribute with no message is a reaction and has no body at all — see
         Tribute::scopeWithMessage(). --}}
    @if($tribute->message)
    <div data-tribute-body class="mt-3 rounded-lg p-3
        @if($tribute->type === 'flower')
            bg-violet-100/50 dark:bg-violet-900/20 border border-violet-200/40 dark:border-violet-800/30
        @elseif($tribute->type === 'candle')
            bg-amber-100/50 dark:bg-amber-900/20 border border-amber-200/40 dark:border-amber-800/30
        @else
            bg-sky-100/50 dark:bg-sky-900/20 border border-sky-200/40 dark:border-sky-800/30
        @endif">

        {{-- Text content --}}
        <div class="min-w-0 flex-1">
            @if ($tributeEmbedPreview)
                <div x-data="{ expanded: false }" class="min-w-0">
                    <div
                        data-tribute-display="{{ $tribute->id }}"
                        class="transition-[max-height] duration-200"
                        :class="expanded ? 'max-h-none overflow-visible' : 'max-h-[5.25rem] overflow-hidden'"
                    >
                        <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\HtmlHelper::sanitize($tribute->message) !!}</div>
                    </div>
                    <button type="button" @click="expanded = !expanded" class="btn btn-link btn-sm mt-1.5">
                        <span x-text="expanded ? 'Show less' : 'Read all'"></span>
                    </button>
                </div>
            @else
            <div data-tribute-display="{{ $tribute->id }}">
                <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\HtmlHelper::sanitize($tribute->message) !!}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Kept outside the message block: a tribute with no body still has a type to
         change and can still be deleted. --}}
    @if($canEditTribute && !$tributeEmbedPreview)
        <div data-tribute-edit="{{ $tribute->id }}" class="hidden mt-2 space-y-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Type</label>
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                        <input type="radio" name="tribute-type-{{ $tribute->id }}" value="flower" {{ $tribute->type === 'flower' ? 'checked' : '' }} class="text-violet-500 focus:ring-violet-500" />
                        <span class="text-violet-600 dark:text-violet-400">Flower</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                        <input type="radio" name="tribute-type-{{ $tribute->id }}" value="candle" {{ $tribute->type === 'candle' ? 'checked' : '' }} class="text-amber-500 focus:ring-amber-500" />
                        <span class="text-amber-600 dark:text-amber-400">Candle</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                        <input type="radio" name="tribute-type-{{ $tribute->id }}" value="prayer" {{ $tribute->type === 'prayer' ? 'checked' : '' }} class="text-sky-500 focus:ring-sky-500" />
                        <span class="text-sky-600 dark:text-sky-400">Prayer</span>
                    </label>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Message</label>
                <div id="tribute-editor-{{ $tribute->id }}" class="min-h-[100px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" data-tribute-save="{{ $tribute->id }}" class="btn btn-primary btn-md">Save</button>
                <button type="button" data-tribute-cancel="{{ $tribute->id }}" class="btn btn-secondary btn-md">Cancel</button>
                <button type="button" data-tribute-delete="{{ $tribute->id }}" class="btn btn-danger-soft btn-md ml-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete
                </button>
            </div>
        </div>
    @endif


    {{-- Footer: reactions, comments, share (relative = full card width for comment dropdown, like life chapter inline comments) --}}
    <div data-tribute-footer class="relative z-10 mt-3 border-t pt-3
        @if($tribute->type === 'flower') border-violet-200/40 dark:border-violet-800/30
        @elseif($tribute->type === 'candle') border-amber-200/40 dark:border-amber-800/30
        @else border-sky-200/40 dark:border-sky-800/30
        @endif">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-3 sm:gap-4">
            <button type="button" data-tribute-react="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span data-tribute-reaction-count="{{ $tribute->id }}">{{ $reactionCount }}</span>
            </button>
            @if ($tributeEmbedPreview)
                <div class="relative" data-tribute-comment-container="{{ $tribute->id }}">
                    <button type="button" data-open-tributes-tribute="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span data-tribute-id="{{ $tribute->id }}" data-tribute-comment-count>{{ $commentCount }}</span>
                    </button>
                </div>
            @else
                <div data-tribute-comment-container="{{ $tribute->id }}">
                    <button type="button" data-tribute-comment-toggle data-tribute-id="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <span class="whitespace-nowrap tabular-nums" data-tribute-id="{{ $tribute->id }}" data-tribute-comment-count>{{ $commentCount }}</span>
                    </button>
                </div>
            @endif
        </div>
        @if ($quotaInfo['share_memories'] ?? false)
            <div class="flex shrink-0 items-center gap-3">
                @if ($tributeEmbedPreview)
                    <div class="relative" data-share-container>
                        <button type="button" data-share-toggle data-share-url="{{ $shareUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            Share
                        </button>
                        <div data-share-dropdown class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                            @include('pages.memorials.partials.share-dropdown', ['shareUrl' => $shareUrl])
                        </div>
                    </div>
                @else
                    <div class="relative" data-share-container data-tribute-id="{{ $tribute->id }}">
                        <button type="button" data-share-toggle data-share-url="{{ $shareUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                            Share
                        </button>
                        <div data-share-dropdown-tribute="{{ $tribute->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                            @include('pages.memorials.partials.share-dropdown', ['shareUrl' => $shareUrl])
                        </div>
                    </div>
                @endif
            </div>
        @endif
        </div>
        @if (! $tributeEmbedPreview)
            <div data-tribute-comment-dropdown="{{ $tribute->id }}" class="absolute inset-x-0 top-full z-[9999] mt-1 hidden w-full min-w-0 rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-100 p-3 dark:border-gray-700">
                    <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Add your comment</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" data-tribute-comment-input="{{ $tribute->id }}" placeholder="Write a comment..." class="h-9 min-w-0 flex-1 basis-36 rounded-full border border-gray-300 bg-gray-50 px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                        <button type="button" data-tribute-comment-submit data-tribute-id="{{ $tribute->id }}" class="btn btn-primary btn-sm rounded-full shrink-0 active:scale-95">Post</button>
                    </div>
                </div>
                <div class="max-h-48 overflow-y-auto overflow-x-hidden p-3" data-tribute-comments-list="{{ $tribute->id }}">
                    @foreach ($tribute->comments as $comment)
                        @include('pages.memorials.partials.tribute-comment-item', ['comment' => $comment, 'tributeId' => $tribute->id, 'canDelete' => $canDeleteTributeComments])
                    @endforeach
                </div>
                <p data-tribute-comments-empty="{{ $tribute->id }}" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400 {{ $tribute->comments->isEmpty() ? '' : 'hidden' }}">No comments yet. Add a comment.</p>
            </div>
        @endif
    </div>
</div>
