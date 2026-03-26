@props(['comment', 'postId', 'isReply' => false, 'canDelete' => false])
@php $initial = strtoupper(substr($comment->author_name, 0, 1)); @endphp
<div class="relative flex gap-2 sm:gap-3 {{ $isReply ? 'ml-6 sm:ml-10' : '' }}" data-comment-id="{{ $comment->id }}">
    <div class="flex flex-col items-center shrink-0">
        <div class="flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-full {{ $isReply ? 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' : 'bg-brand-100 dark:bg-brand-500/25 text-brand-600 dark:text-brand-400' }} text-[11px] sm:text-xs font-semibold">
            {{ $initial }}
        </div>
        @if (!$isReply && $comment->replies && $comment->replies->isNotEmpty())
            <div class="mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
        @endif
    </div>

    <div class="min-w-0 flex-1 pb-3">
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span class="truncate text-sm font-semibold text-gray-900 dark:text-white/90">{{ $comment->author_name }}</span>
            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">{{ $comment->created_at->diffForHumans(short: true) }}</span>
        </div>
        <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">{{ $comment->content }}</p>
        <div class="mt-1.5 flex items-center gap-3">
            @if (!$isReply && isset($postId))
                <button type="button" data-reply-to data-comment-id="{{ $comment->id }}" data-post-id="{{ $postId }}" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-brand-500 dark:hover:text-brand-400 transition">Reply</button>
            @endif
            @if ($canDelete)
                <button type="button" data-delete-comment data-comment-id="{{ $comment->id }}" data-post-id="{{ $postId }}" class="text-xs font-medium text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition">Delete</button>
            @endif
        </div>

        @if (!$isReply && isset($postId))
            <div data-reply-form="{{ $comment->id }}" class="hidden mt-2">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    </div>
                    <input type="text" data-reply-input="{{ $comment->id }}" placeholder="Write a reply..." class="h-9 min-w-0 flex-1 basis-40 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                    <button type="button" data-reply-submit data-comment-id="{{ $comment->id }}" data-post-id="{{ $postId }}" class="h-9 shrink-0 rounded-full bg-brand-500 px-3 text-xs font-semibold text-white hover:bg-brand-600 transition active:scale-95">Reply</button>
                </div>
            </div>
        @endif

        @if (!$isReply && $comment->replies && $comment->replies->isNotEmpty())
            <div class="mt-1 space-y-0" data-replies-list="{{ $comment->id }}">
                @foreach ($comment->replies as $reply)
                    @include('pages.memorials.partials.comment-item', ['comment' => $reply, 'postId' => $postId, 'isReply' => true, 'canDelete' => $canDelete])
                @endforeach
            </div>
        @endif
    </div>
</div>
