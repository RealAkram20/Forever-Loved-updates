{{--
    Change / remove the cover photo.

    Rendered twice: floating on the banner from `md` up, and inside the card body below it,
    where the banner is not rendered at all. Two copies rather than one moved by CSS,
    because the banner is `display:none` on small screens and its children go with it — and
    the public memorial page is the only place a cover can be managed, so losing these on a
    phone would lose the capability outright.

    Everything the JS binds to is an attribute, never an id, precisely so both copies work.

    @param \App\Models\Memorial $memorial
    @param string $variant  overlay (on the banner) | inline (in the card body)
--}}
@php $overlay = ($variant ?? 'overlay') === 'overlay'; @endphp

<div class="flex items-center gap-1.5 {{ $overlay ? 'absolute right-2 top-2' : 'justify-end' }}">
    <label class="memorial-cover-btn inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold
        {{ $overlay
            ? 'bg-white/90 text-gray-900 shadow-sm backdrop-blur-sm dark:bg-gray-900/90 dark:text-white'
            : 'border border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">
        <input type="file" data-cover-input accept="image/*" class="hidden" />
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span data-cover-label>{{ $memorial->cover_photo_path ? 'Change cover' : 'Add cover' }}</span>
    </label>
    <button type="button" data-cover-remove
        class="memorial-cover-btn rounded-lg p-1.5 hover:text-red-600 dark:hover:text-red-400
            {{ $overlay
                ? 'bg-white/90 text-gray-700 shadow-sm backdrop-blur-sm dark:bg-gray-900/90 dark:text-gray-300'
                : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' }}
            {{ $memorial->cover_photo_path ? '' : 'hidden' }}"
        title="Remove cover photo" aria-label="Remove cover photo">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    </button>
</div>