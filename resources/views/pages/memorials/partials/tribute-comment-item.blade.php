@props(['comment', 'tributeId', 'isReply' => false])
@php $tcPhoto = $comment->user?->profile_photo_url; @endphp
<div class="mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 {{ $isReply ? 'ml-3 sm:ml-4 border-l-2 border-gray-200 dark:border-gray-700' : '' }}" data-tribute-comment-id="{{ $comment->id }}">
    <div class="flex items-center gap-2 mb-1">
        @if($tcPhoto)
            <img src="{{ $tcPhoto }}" alt="{{ $comment->author_name }}" class="h-6 w-6 rounded-full object-cover shrink-0" />
        @else
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                {{ strtoupper(substr($comment->author_name, 0, 1)) }}
            </div>
        @endif
        <p class="text-sm font-medium text-gray-900 dark:text-white/90">{{ $comment->author_name }}</p>
    </div>
    <p class="text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">{{ $comment->content }}</p>
    <div class="flex items-center gap-2 mt-1">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
        @if (!$isReply && isset($tributeId))
            <button type="button" data-tribute-reply-to data-comment-id="{{ $comment->id }}" data-tribute-id="{{ $tributeId }}" class="text-xs text-brand-500 hover:text-brand-600 dark:hover:text-brand-400">Reply</button>
        @endif
    </div>
    @if (!$isReply && isset($tributeId))
        <div data-tribute-reply-form="{{ $comment->id }}" class="hidden mt-2">
            <div class="flex flex-wrap gap-2">
                <input type="text" data-tribute-reply-input="{{ $comment->id }}" placeholder="Write a reply..." class="h-9 min-w-0 flex-1 basis-36 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 text-sm dark:text-white" />
                <button type="button" data-tribute-reply-submit data-comment-id="{{ $comment->id }}" data-tribute-id="{{ $tributeId }}" class="h-9 shrink-0 rounded-lg bg-brand-500 px-3 text-sm font-medium text-white hover:bg-brand-600">Post</button>
            </div>
        </div>
    @endif
    @if (!$isReply && $comment->replies && $comment->replies->isNotEmpty())
        <div class="mt-2 space-y-2" data-tribute-replies-list="{{ $comment->id }}">
            @foreach ($comment->replies as $reply)
                @include('pages.memorials.partials.tribute-comment-item', ['comment' => $reply, 'tributeId' => $tributeId, 'isReply' => true])
            @endforeach
        </div>
    @endif
</div>
