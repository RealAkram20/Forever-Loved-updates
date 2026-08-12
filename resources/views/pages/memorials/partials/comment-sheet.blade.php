{{--
    The comment sheet.

    One sheet for the whole page, opened with a story id and filled from the API — not one
    hidden comment tree per story. A memorial with forty stories was shipping forty hidden
    lists in the markup, every one of them fully rendered, for a reader who would open at
    most one.

    Comments used to live in a strip that expanded inside the card: the composer scrolled
    away as soon as you read past it, the total was never shown, and every reply was always
    open. Here the count is in the header, the list scrolls under it, the composer is pinned
    to the bottom where your thumb is, and replies stay folded until asked for.

    Bottom sheet on a phone, centred dialog from `sm` up. Rows are built by JS from the same
    payload the API returns for a comment it has just stored, so there is one renderer.

    @param \App\Models\Memorial $memorial
    @param bool $isAuthenticated
--}}
<div id="comment-sheet" class="comment-sheet fixed inset-0 z-[99997] hidden" role="dialog" aria-modal="true" aria-labelledby="comment-sheet-title">
    <div class="comment-sheet__scrim absolute inset-0 bg-black/50" data-close-comment-sheet aria-hidden="true"></div>

    <div class="comment-sheet__panel">
        {{-- Reads as a thing you can pull down, and is the drag handle on touch. --}}
        <div class="comment-sheet__grab" data-comment-sheet-grab aria-hidden="true"><span></span></div>

        <header class="comment-sheet__head">
            <h2 id="comment-sheet-title" class="text-base font-semibold text-gray-900 dark:text-white/90">
                <span data-sheet-total class="tabular-nums">0</span> <span data-sheet-total-label>comments</span>
            </h2>
            <button type="button" data-close-comment-sheet class="comment-sheet__close" aria-label="Close comments">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <div class="comment-sheet__body" data-sheet-body>
            <ol class="comment-list" data-sheet-list></ol>

            {{-- Held in the markup rather than built on demand: all three are states of the
                 same list and swapping classes cannot fail halfway. --}}
            <div data-sheet-spinner class="hidden py-6 text-center">
                <svg class="mx-auto h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            </div>
            <div data-sheet-empty class="hidden px-6 py-12 text-center">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No comments yet</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Be the first to say something.</p>
            </div>
            <div data-sheet-error class="hidden px-6 py-10 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">Those didn’t load.</p>
                <button type="button" data-sheet-retry class="btn btn-secondary btn-sm mt-3">Try again</button>
            </div>
        </div>

        {{-- Someone else posted while you were reading further down. Yanking you to the top
             would lose your place, so the arrival is offered instead of applied. --}}
        <button type="button" data-sheet-new-pill class="comment-sheet__new-pill hidden">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            <span data-sheet-new-label>1 new comment</span>
        </button>

        <div class="comment-sheet__composer">
            {{-- Replying is the same composer wearing a label, so there is one input on
                 screen and the keyboard never moves. --}}
            <div data-sheet-replying class="comment-sheet__replying hidden">
                <span>Replying to <strong data-sheet-replying-to></strong></span>
                <button type="button" data-sheet-cancel-reply aria-label="Cancel reply">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if (!$isAuthenticated)
                <div data-sheet-guest data-guest-fields class="mb-2 grid grid-cols-2 gap-2">
                    <input type="text" data-sheet-guest-name autocomplete="name" placeholder="Your name"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                    <input type="email" data-sheet-guest-email autocomplete="email" placeholder="your@email.com"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                </div>
            @endif

            <div class="flex items-end gap-2">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-600 dark:bg-brand-500/25 dark:text-brand-400">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'G', 0, 1)) }}
                </span>
                <label for="comment-sheet-input" class="sr-only">Add a comment</label>
                {{-- A textarea, not an input: comments run past one line and a single-line
                     field hides everything but the tail of what you just wrote. It grows to
                     five lines and then scrolls. --}}
                <textarea id="comment-sheet-input" data-sheet-input rows="1" maxlength="2000"
                    placeholder="Add comment…" class="comment-sheet__input"></textarea>
                <button type="button" data-sheet-send class="comment-sheet__send" disabled aria-label="Post comment">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a.993.993 0 00-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>