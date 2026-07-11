@props(['comment', 'tributeId', 'isReply' => false, 'canDelete' => false])
@php $tcPhoto = $comment->user?->profile_photo_url; @endphp
<div class="mb-3 last:mb-0 rounded-lg bg-gray-50 dark:bg-white/[0.02] px-3 py-2 {{ $isReply ? 'ml-3 sm:ml-4 border-l-2 border-gray-200 dark:border-gray-700' : '' }}" data-tribute-comment-id="{{ $comment->id }}">
    <div class="mb-1 flex min-w-0 items-center gap-2">
        @if($tcPhoto)
            <img src="{{ $tcPhoto }}" alt="{{ $comment->author_name }}" class="h-6 w-6 shrink-0 rounded-full object-cover" />
        @else
            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                {{ strtoupper(substr($comment->author_name, 0, 1)) }}
            </div>
        @endif
        <p class="min-w-0 truncate text-sm font-medium text-gray-900 dark:text-white/90">{{ $comment->author_name }}</p>
    </div>
    <p class="text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">{{ $comment->content }}</p>
    <div class="flex flex-wrap items-center gap-2 mt-1">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
        @if (!$isReply && isset($tributeId))
            <button type="button" data-tribute-reply-to data-comment-id="{{ $comment->id }}" data-tribute-id="{{ $tributeId }}" class="btn btn-link btn-sm">Reply</button>
        @endif
        @if ($canDelete && isset($tributeId))
            <button type="button" data-delete-tribute-comment data-comment-id="{{ $comment->id }}" data-tribute-id="{{ $tributeId }}" class="btn btn-danger-soft btn-sm">Delete</button>
        @endif
    </div>
    @if (!$isReply && isset($tributeId))
        <div data-tribute-reply-form="{{ $comment->id }}" class="mt-2 hidden">
            <div class="flex flex-wrap items-center gap-2">
                <input type="text" data-tribute-reply-input="{{ $comment->id }}" placeholder="Write a reply..." class="h-9 min-w-0 flex-1 basis-36 rounded-full border border-gray-300 bg-gray-50 px-3 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                <button type="button" data-tribute-reply-submit data-comment-id="{{ $comment->id }}" data-tribute-id="{{ $tributeId }}" class="btn btn-primary btn-sm rounded-full shrink-0 active:scale-95">Post</button>
            </div>
        </div>
    @endif
    @if (!$isReply && $comment->replies && $comment->replies->isNotEmpty())
        <div class="mt-2 space-y-2" data-tribute-replies-list="{{ $comment->id }}">
            @foreach ($comment->replies as $reply)
                @include('pages.memorials.partials.tribute-comment-item', ['comment' => $reply, 'tributeId' => $tributeId, 'isReply' => true, 'canDelete' => $canDelete])
            @endforeach
        </div>
    @endif
</div>
