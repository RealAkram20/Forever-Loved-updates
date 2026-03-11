<div id="tribute-{{ $tribute->id }}" class="border-b border-gray-100 dark:border-gray-800 pb-4 last:border-0 last:pb-0" data-tribute-id="{{ $tribute->id }}">
    <div class="flex items-center gap-2">
        <span class="font-medium text-gray-900 dark:text-white/90">{{ $tribute->user?->name ?? $tribute->guest_name ?? 'Anonymous' }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400 time-ago" data-created-at="{{ $tribute->created_at->toIso8601String() }}">{{ $tribute->created_at->diffForHumans() }}</span>
        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
            @if($tribute->type === 'flower') bg-pink-100 dark:bg-pink-500/20 text-pink-800 dark:text-pink-400
            @elseif($tribute->type === 'candle') bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400
            @else bg-gray-100 dark:bg-gray-500/20 text-gray-800 dark:text-gray-300 @endif">
            {{ ucfirst($tribute->type) }}
        </span>
        <div class="relative flex items-center gap-1" data-tribute-comment-container="{{ $tribute->id }}">
            <button type="button" data-tribute-comment-toggle data-tribute-id="{{ $tribute->id }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <span data-tribute-id="{{ $tribute->id }}" data-tribute-comment-count class="text-sm text-gray-600 dark:text-gray-400">{{ $tribute->comments->count() + $tribute->comments->sum(fn($c) => $c->replies->count()) }}</span>
            </button>
            <div data-tribute-comment-dropdown="{{ $tribute->id }}" class="absolute left-0 top-full z-[9999] mt-1 hidden w-full min-w-[320px] max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
                <div class="border-b border-gray-100 dark:border-gray-700 p-3">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Add your comment</p>
                    <div class="flex gap-2">
                        <input type="text" data-tribute-comment-input="{{ $tribute->id }}" placeholder="Write a comment..." class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm" />
                        <button type="button" data-tribute-comment-submit data-tribute-id="{{ $tribute->id }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Post</button>
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
        <div class="relative ml-auto" data-share-container data-tribute-id="{{ $tribute->id }}">
            <button type="button" data-share-toggle data-share-url="{{ $shareUrl }}" class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                Share
            </button>
            <div data-share-dropdown-tribute="{{ $tribute->id }}" class="absolute right-0 top-full z-[9999] mt-1 hidden w-48 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl py-1">
                <a href="#" data-share="whatsapp" data-share-url="{{ $shareUrl }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">WhatsApp</a>
                <a href="#" data-share="facebook" data-share-url="{{ $shareUrl }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Facebook</a>
                <a href="#" data-share="linkedin" data-share-url="{{ $shareUrl }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">LinkedIn</a>
                <button type="button" data-share="copy" data-share-url="{{ $shareUrl }}" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Link</button>
            </div>
        </div>
    </div>
    @if ($tribute->message)
        <div class="mt-1 text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\HtmlHelper::sanitize($tribute->message) !!}</div>
    @endif
</div>
