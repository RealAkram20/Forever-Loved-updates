{{--
    The one composer.

    There used to be two — "Add a tribute" at the foot of one sub-tab and "+ Your Chapter"
    at the foot of another — and both were buttons that scrolled you somewhere else before
    you could type a word. A visitor arriving to say something first had to decide which of
    two words their sentence was.

    Now there is one, it sits at the top of the feed where the thing it makes will appear,
    and it opens in place. The marker (flower, candle, prayer) is chosen after the words,
    not before them, and skipping it is the default rather than a fallback.

    @param \App\Models\Memorial $memorial
    @param bool $isAuthenticated
--}}
@php
    $composerFirstName = \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'them');

    // Three, and none of them selected. A story is what this makes by default, so a "Story"
    // chip sitting pre-selected among them was a control that did nothing — it named the
    // state you were already in, and made picking nothing look like a fourth choice rather
    // than the normal one. Tapping a chosen chip again clears it.
    $composerMarkers = [
        ['value' => 'flower', 'label' => 'Flower', 'art' => 'flower'],
        ['value' => 'candle', 'label' => 'Candle', 'art' => 'candle'],
        ['value' => 'prayer', 'label' => 'Prayer', 'art' => 'prayer'],
    ];
@endphp

<div id="story-composer" data-open="0" class="story-composer overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.04]">
    {{-- Collapsed: an invitation shaped like the thing it opens. It is a button, not an
         input, so a tap anywhere along it opens the real editor rather than focusing a
         field that cannot accept what the composer actually takes. Open, it stays as the
         header — tapping it again puts the caret back in the editor. --}}
    <button type="button" id="story-composer-open" aria-expanded="false" aria-controls="story-composer-form"
        class="story-composer__prompt flex w-full items-center gap-3 px-4 py-3.5 text-left">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-600 dark:bg-brand-500/25 dark:text-brand-400">
            {{ strtoupper(substr(auth()->user()?->name ?? 'G', 0, 1)) }}
        </span>
        <span class="min-w-0 flex-1 truncate text-sm text-gray-500 dark:text-gray-400">Share a memory of {{ $composerFirstName }}&hellip;</span>
        <span class="story-composer__cta shrink-0 rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-semibold text-white">Write</span>
    </button>

    <div id="story-composer-form" class="story-composer__form hidden px-4 pb-4">
        @if (!$isAuthenticated)
            {{-- data-guest-fields: hidden by adoptSession() the moment a first write signs
                 this visitor in — from then on the page must stop asking who they are. --}}
            <div data-guest-fields class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="chapter-guest-name" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Your name</label>
                    <input type="text" id="chapter-guest-name" autocomplete="name"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="Your name" />
                </div>
                <div>
                    <label for="chapter-guest-email" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Your email</label>
                    <input type="email" id="chapter-guest-email" autocomplete="email"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" placeholder="your@email.com" />
                </div>
            </div>
        @endif

        <form id="tribute-post-form" class="space-y-4">
            <div>
                <label for="story-title" class="sr-only">Title</label>
                <input type="text" name="title" id="story-title" placeholder="Give it a title (optional)"
                    class="h-11 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
            </div>

            <div>
                <div id="chapter-editor" class="min-h-[140px] rounded-lg border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-900"></div>
                <input type="hidden" name="content" id="chapter-content" />
            </div>

            {{-- The marker. A radio group, so it is one choice and a keyboard can move
                 through it with arrows the way a keyboard expects to. --}}
            <fieldset>
                <legend class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                    Mark it as <span class="font-normal text-gray-400 dark:text-gray-500">— optional</span>
                </legend>
                <div class="story-marker-row flex flex-wrap gap-2" data-marker-group>
                    @foreach ($composerMarkers as $marker)
                        <label class="story-marker-chip">
                            <input type="radio" name="story-marker" value="{{ $marker['value'] }}" class="sr-only" />
                            <span class="story-marker-chip__art" aria-hidden="true">
                                @include('pages.memorials.partials.tribute-art', ['type' => $marker['art'], 'image' => $marker['art'].'.png'])
                            </span>
                            <span class="story-marker-chip__label">{{ $marker['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="story-files" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Add photos, video, a song or a document</label>
                <input type="file" name="files[]" id="story-files" multiple accept="image/*,video/*,audio/*,.pdf"
                    class="w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 dark:text-gray-400 dark:file:bg-white/10 dark:file:text-gray-200" />
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary btn-md">Post</button>
                <button type="button" id="cancel-story-btn" class="btn btn-secondary btn-md">Cancel</button>
            </div>
        </form>
    </div>
</div>