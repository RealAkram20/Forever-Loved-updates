@php
    $authorName = $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous';
    $initials = collect(explode(' ', $authorName))->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->join('');
    $authorPhoto = $tribute->user?->profile_photo_url;
    $reactionCount = $tribute->reactions->count();
    $commentCount = $tribute->comments->count() + $tribute->comments->sum(fn($c) => $c->replies->count());
    $deceasedFirst = \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'them');
    $canEditTribute = ($canEdit ?? false) || (auth()->id() && $tribute->user_id === auth()->id());
@endphp
<div
    id="tribute-{{ $tribute->id }}"
    data-tribute-id="{{ $tribute->id }}"
    data-tribute-type="{{ $tribute->type }}"
    x-show="tributeFilter === 'all' || tributeFilter === '{{ $tribute->type }}'"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    class="group rounded-xl border p-4 transition
        @if($tribute->type === 'flower')
            border-pink-200/60 dark:border-pink-800/40 bg-pink-50/40 dark:bg-pink-950/20
        @elseif($tribute->type === 'candle')
            border-amber-200/60 dark:border-amber-800/40 bg-amber-50/40 dark:bg-amber-950/20
        @else
            border-gray-200/80 dark:border-gray-700/60 bg-gray-50/40 dark:bg-white/[0.02]
        @endif"
>
    {{-- Header: avatar, name, time, type icon --}}
    <div class="flex items-start gap-3">
        @if($authorPhoto)
            <img src="{{ $authorPhoto }}" alt="{{ $authorName }}" class="h-10 w-10 shrink-0 rounded-full object-cover" />
        @else
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold
                @if($tribute->type === 'flower')
                    bg-pink-200/70 dark:bg-pink-800/40 text-pink-700 dark:text-pink-300
                @elseif($tribute->type === 'candle')
                    bg-amber-200/70 dark:bg-amber-800/40 text-amber-700 dark:text-amber-300
                @else
                    bg-gray-200/70 dark:bg-gray-700/60 text-gray-600 dark:text-gray-300
                @endif">
                {{ $initials }}
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <p class="font-semibold text-gray-900 dark:text-white/90 truncate">{{ $authorName }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 time-ago" data-created-at="{{ $tribute->created_at->toIso8601String() }}">{{ $tribute->created_at->diffForHumans() }}</p>
        </div>
        <div class="flex items-center gap-1 shrink-0">
            @if($canEditTribute)
                <button type="button" data-tribute-edit-trigger="{{ $tribute->id }}" class="memorial-edit-fab rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit tribute">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
            @endif
            {{-- Type indicator --}}
            @if($tribute->type === 'flower')
                <svg class="h-6 w-6 text-pink-400 dark:text-pink-400 tribute-icon-sway" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.5 2 7.5 4.5 7.5 7c0 1.8 1 3.4 2.5 4.2V22h4V11.2c1.5-.8 2.5-2.4 2.5-4.2 0-2.5-2-5-4.5-5zm-2 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4 0c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
            @elseif($tribute->type === 'candle')
                <svg class="h-6 w-6 text-amber-400 dark:text-amber-400 tribute-icon-flicker" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 0-1 .19-1.41.59l-1.3 1.3C8.78 4.4 8.5 5.13 8.5 5.91c0 1.97 1.6 3.59 3.5 3.59s3.5-1.62 3.5-3.59c0-.78-.28-1.51-.79-2.02l-1.3-1.3C13 2.19 12.5 2 12 2zm-1 8.5V22h2V10.5h-2z"/></svg>
            @else
                <svg class="h-6 w-6 text-gray-400 dark:text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            @endif
        </div>
    </div>

    {{-- Content block with inline icon --}}
    <div class="mt-3 flex items-start gap-3 rounded-lg p-3
        @if($tribute->type === 'flower')
            bg-pink-100/50 dark:bg-pink-900/20 border border-pink-200/40 dark:border-pink-800/30
        @elseif($tribute->type === 'candle')
            bg-amber-100/50 dark:bg-amber-900/20 border border-amber-200/40 dark:border-amber-800/30
        @else
            bg-white/60 dark:bg-white/[0.03] border border-gray-200/50 dark:border-gray-700/40
        @endif">

        {{-- Animated inline icon --}}
        <div class="shrink-0 mt-0.5">
            @if($tribute->type === 'flower')
                {{-- Multi-petal flower with sway --}}
                <svg class="h-10 w-10 tribute-icon-sway" viewBox="0 0 48 48" fill="none">
                    <g transform="translate(24,20)">
                        <ellipse cx="0" cy="-8" rx="5" ry="8" fill="#f9a8d4" opacity="0.9" transform="rotate(0)"/>
                        <ellipse cx="0" cy="-8" rx="5" ry="8" fill="#f472b6" opacity="0.8" transform="rotate(72)"/>
                        <ellipse cx="0" cy="-8" rx="5" ry="8" fill="#f9a8d4" opacity="0.9" transform="rotate(144)"/>
                        <ellipse cx="0" cy="-8" rx="5" ry="8" fill="#f472b6" opacity="0.8" transform="rotate(216)"/>
                        <ellipse cx="0" cy="-8" rx="5" ry="8" fill="#f9a8d4" opacity="0.9" transform="rotate(288)"/>
                        <circle cx="0" cy="0" r="4" fill="#fbbf24"/>
                    </g>
                    <line x1="24" y1="24" x2="24" y2="44" stroke="#86efac" stroke-width="2.5" stroke-linecap="round"/>
                    <ellipse cx="18" cy="36" rx="5" ry="3" fill="#86efac" opacity="0.7" transform="rotate(-30, 18, 36)"/>
                </svg>
            @elseif($tribute->type === 'candle')
                {{-- Candle with animated flame --}}
                <svg class="h-10 w-10" viewBox="0 0 48 48" fill="none">
                    <rect x="19" y="22" width="10" height="20" rx="2" fill="#fbbf24" opacity="0.85"/>
                    <rect x="20" y="22" width="3" height="20" rx="1" fill="#fde68a" opacity="0.4"/>
                    <rect x="23" y="20" width="2" height="3" rx="1" fill="#92400e"/>
                    <g class="tribute-flame-flicker" transform-origin="24 16">
                        <ellipse cx="24" cy="14" rx="4.5" ry="7" fill="#f97316" opacity="0.9"/>
                        <ellipse cx="24" cy="13" rx="2.5" ry="5" fill="#fbbf24"/>
                        <ellipse cx="24" cy="12" rx="1.2" ry="3" fill="#fef3c7"/>
                    </g>
                    <ellipse cx="24" cy="9" rx="6" ry="3" fill="#fbbf24" opacity="0.15" class="tribute-glow-pulse"/>
                </svg>
            @else
                {{-- Quill pen writing --}}
                <svg class="h-10 w-10 tribute-icon-write" viewBox="0 0 48 48" fill="none">
                    <path d="M34 6c-6 4-12 14-16 24l-2 8 6-4c4-8 8-16 14-22" fill="#94a3b8" opacity="0.15"/>
                    <path d="M34 6c-6 4-12 14-16 24l-2 8 6-4c4-8 8-16 14-22z" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="dark:stroke-gray-400"/>
                    <path d="M16 30l-2 8" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" class="dark:stroke-gray-400"/>
                    <circle cx="15" cy="39" r="1.5" fill="#64748b" opacity="0.5" class="tribute-ink-dot"/>
                </svg>
            @endif
        </div>

        {{-- Text content --}}
        <div class="min-w-0 flex-1">
            <div data-tribute-display="{{ $tribute->id }}">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wider
                    @if($tribute->type === 'flower') text-pink-600 dark:text-pink-400
                    @elseif($tribute->type === 'candle') text-amber-600 dark:text-amber-400
                    @else text-gray-500 dark:text-gray-400
                    @endif">
                    @if($tribute->type === 'flower') Flower Left
                    @elseif($tribute->type === 'candle') Candle Lit
                    @else Note Left
                    @endif
                </p>
                @if($tribute->message)
                    <div class="text-sm text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\HtmlHelper::sanitize($tribute->message) !!}</div>
                @else
                    <p class="text-sm italic
                        @if($tribute->type === 'flower') text-pink-600/80 dark:text-pink-400/80
                        @elseif($tribute->type === 'candle') text-amber-600/80 dark:text-amber-400/80
                        @else text-gray-500 dark:text-gray-400
                        @endif">
                        @if($tribute->type === 'flower') A flower placed in memory of {{ $deceasedFirst }}.
                        @elseif($tribute->type === 'candle') A flame lit in honour of {{ $deceasedFirst }}.
                        @else A note left for {{ $deceasedFirst }}.
                        @endif
                    </p>
                @endif
            </div>
            @if($canEditTribute)
                <div data-tribute-edit="{{ $tribute->id }}" class="hidden mt-2 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Type</label>
                        <div class="flex items-center gap-3">
                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" name="tribute-type-{{ $tribute->id }}" value="flower" {{ $tribute->type === 'flower' ? 'checked' : '' }} class="text-pink-500 focus:ring-pink-500" />
                                <span class="text-pink-600 dark:text-pink-400">Flower</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" name="tribute-type-{{ $tribute->id }}" value="candle" {{ $tribute->type === 'candle' ? 'checked' : '' }} class="text-amber-500 focus:ring-amber-500" />
                                <span class="text-amber-600 dark:text-amber-400">Candle</span>
                            </label>
                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-sm">
                                <input type="radio" name="tribute-type-{{ $tribute->id }}" value="note" {{ $tribute->type === 'note' ? 'checked' : '' }} class="text-gray-500 focus:ring-gray-500" />
                                <span class="text-gray-600 dark:text-gray-400">Note</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Message</label>
                        <div id="tribute-editor-{{ $tribute->id }}" class="min-h-[100px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" data-tribute-save="{{ $tribute->id }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">Save</button>
                        <button type="button" data-tribute-cancel="{{ $tribute->id }}" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 transition">Cancel</button>
                        <button type="button" data-tribute-delete="{{ $tribute->id }}" class="ml-auto inline-flex items-center gap-1.5 text-sm text-red-500 hover:text-red-600 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Footer: reactions, comments, share --}}
    <div class="mt-3 flex items-center justify-between border-t pt-3
        @if($tribute->type === 'flower') border-pink-200/40 dark:border-pink-800/30
        @elseif($tribute->type === 'candle') border-amber-200/40 dark:border-amber-800/30
        @else border-gray-200/50 dark:border-gray-700/40
        @endif">
        <div class="flex items-center gap-4">
            <button type="button" data-tribute-react="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition">
                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                <span data-tribute-reaction-count="{{ $tribute->id }}">{{ $reactionCount }}</span>
            </button>
            <div class="relative" data-tribute-comment-container="{{ $tribute->id }}">
                <button type="button" data-tribute-comment-toggle data-tribute-id="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                    <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <span data-tribute-id="{{ $tribute->id }}" data-tribute-comment-count>{{ $commentCount }}</span>
                </button>
                <div data-tribute-comment-dropdown="{{ $tribute->id }}" class="absolute left-0 top-full z-[9999] mt-1 hidden w-[calc(100vw-2rem)] max-w-sm rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl sm:w-80">
                    <div class="border-b border-gray-100 dark:border-gray-700 p-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Add your comment</p>
                        <div class="flex gap-2">
                            <input type="text" data-tribute-comment-input="{{ $tribute->id }}" placeholder="Write a comment..." class="min-w-0 flex-1 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm dark:text-white" />
                            <button type="button" data-tribute-comment-submit data-tribute-id="{{ $tribute->id }}" class="shrink-0 rounded-lg bg-brand-500 px-3 py-2 text-sm font-medium text-white hover:bg-brand-600">Post</button>
                        </div>
                    </div>
                    <div class="max-h-48 overflow-y-auto p-3" data-tribute-comments-list="{{ $tribute->id }}">
                        @foreach ($tribute->comments as $comment)
                            @include('pages.memorials.partials.tribute-comment-item', ['comment' => $comment, 'tributeId' => $tribute->id])
                        @endforeach
                    </div>
                    <p data-tribute-comments-empty="{{ $tribute->id }}" class="px-3 py-4 text-center text-sm text-gray-500 {{ $tribute->comments->isEmpty() ? '' : 'hidden' }}">No comments yet. Add a comment.</p>
                </div>
            </div>
        </div>
        @if ($quotaInfo['share_memories'] ?? false)
            <div class="flex items-center gap-3">
                <div class="relative" data-share-container data-tribute-id="{{ $tribute->id }}">
                    <button type="button" data-share-toggle data-share-url="{{ $shareUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">
                        <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                    <div data-share-dropdown-tribute="{{ $tribute->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-52 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-1.5">
                        @include('pages.memorials.partials.share-dropdown', ['shareUrl' => $shareUrl])
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
