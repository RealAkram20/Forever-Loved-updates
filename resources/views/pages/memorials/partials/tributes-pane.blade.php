{{--
    The Tributes half of the Tributes & Stories tab: the type filters, the list, and
    the compose form. `tributeFilter` lives on the tab panel's own x-data, one level up.
--}}
<div class="flex items-center justify-between gap-4 mb-4">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Tributes (<span data-tribute-count>{{ $tributes->total() + (isset($highlightTribute) ? 1 : 0) }}</span>)</h2>
    <button type="button" id="add-tribute-btn" class="rounded-lg border border-dashed border-brand-400 dark:border-brand-500 px-4 py-2 text-sm font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/20">Add a tribute</button>
</div>

{{-- Type filter tabs --}}
<div class="flex flex-wrap gap-2 mb-5">
    <button type="button" @click="tributeFilter = 'all'" :class="tributeFilter === 'all' ? 'bg-gray-900 dark:bg-white/90 text-white dark:text-gray-900' : 'bg-gray-100 dark:bg-white/[0.06] text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/10'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
        All
        <span class="rounded-full bg-white/20 dark:bg-gray-900/20 px-2 py-0.5 text-xs" :class="tributeFilter === 'all' ? 'bg-white/20 dark:bg-gray-900/20' : 'bg-gray-200 dark:bg-white/10'" data-count-all>{{ $tw['total'] }}</span>
    </button>
    <button type="button" @click="tributeFilter = 'flower'" :class="tributeFilter === 'flower' ? 'bg-violet-600 dark:bg-violet-500 text-white' : 'bg-violet-50 dark:bg-violet-950/30 text-violet-700 dark:text-violet-400 hover:bg-violet-100 dark:hover:bg-violet-900/40'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.5 2 7.5 4.5 7.5 7c0 1.8 1 3.4 2.5 4.2V22h4V11.2c1.5-.8 2.5-2.4 2.5-4.2 0-2.5-2-5-4.5-5zm-2 7c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm4 0c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
        Flowers
        <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'flower' ? 'bg-white/20' : 'bg-violet-100 dark:bg-violet-900/40'" data-count-flower>{{ $tw['flower'] }}</span>
    </button>
    <button type="button" @click="tributeFilter = 'candle'" :class="tributeFilter === 'candle' ? 'bg-amber-600 dark:bg-amber-500 text-white' : 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 hover:bg-amber-100 dark:hover:bg-amber-900/40'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-.5 0-1 .19-1.41.59l-1.3 1.3C8.78 4.4 8.5 5.13 8.5 5.91c0 1.97 1.6 3.59 3.5 3.59s3.5-1.62 3.5-3.59c0-.78-.28-1.51-.79-2.02l-1.3-1.3C13 2.19 12.5 2 12 2zm-1 8.5V22h2V10.5h-2z"/></svg>
        Candles
        <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'candle' ? 'bg-white/20' : 'bg-amber-100 dark:bg-amber-900/40'" data-count-candle>{{ $tw['candle'] }}</span>
    </button>
    <button type="button" @click="tributeFilter = 'prayer'" :class="tributeFilter === 'prayer' ? 'bg-sky-600 dark:bg-sky-500 text-white' : 'bg-sky-50 dark:bg-sky-950/30 text-sky-700 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/40'" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.4 1.9c-1.1 1-1.95 2.3-2.55 3.85C8 7.9 7.55 10.15 7.55 12.5v2.8c0 1.45.9 2.75 2.25 3.25l1.6.6z"/><path d="M11.4 16.5v3.25l-2.2 1.35a2.4 2.4 0 0 1-3.3-.8 2.4 2.4 0 0 1 .8-3.3l2.7-1.65z"/><g transform="translate(24,0) scale(-1,1)"><path d="M11.4 1.9c-1.1 1-1.95 2.3-2.55 3.85C8 7.9 7.55 10.15 7.55 12.5v2.8c0 1.45.9 2.75 2.25 3.25l1.6.6z"/><path d="M11.4 16.5v3.25l-2.2 1.35a2.4 2.4 0 0 1-3.3-.8 2.4 2.4 0 0 1 .8-3.3l2.7-1.65z"/></g></svg>
        Prayers
        <span class="rounded-full px-2 py-0.5 text-xs" :class="tributeFilter === 'prayer' ? 'bg-white/20' : 'bg-sky-100 dark:bg-sky-900/40'" data-count-prayer>{{ $tw['prayer'] }}</span>
    </button>
</div>

<div class="space-y-4" data-tributes-list>
    @if($highlightTribute ?? null)
        @foreach([$highlightTribute] as $tribute)
            @include('pages.memorials.partials.tribute-item', ['tribute' => $tribute, 'shareUrl' => route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])])
        @endforeach
    @endif
    @foreach ($tributes as $tribute)
        @include('pages.memorials.partials.tribute-item', ['tribute' => $tribute, 'shareUrl' => route('memorial.tribute.public', ['memorial_slug' => $memorial->slug, 'share_id' => $tribute->share_id])])
    @endforeach
    <p data-tributes-empty class="py-8 text-center text-gray-500 dark:text-gray-400 {{ ($tributes->isEmpty() && !isset($highlightTribute)) ? '' : 'hidden' }}">No tributes yet. Be the first to leave one.</p>
</div>
@if ($tributes->hasPages())
    <div class="mt-4">{{ $tributes->links() }}</div>
@endif
<div id="tribute-form-anchor" class="mt-8 scroll-mt-4"></div>
<div id="tribute-note-ajax" class="mt-4 space-y-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
    <h3 class="font-medium text-gray-900 dark:text-white/90">Leave a Tribute</h3>
    @if (!$isAuthenticated)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your name</label>
                <input type="text" id="tribute-note-name" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="Your name" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your email</label>
                <input type="email" id="tribute-note-email" class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="your@email.com" />
            </div>
        </div>
    @endif
    <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">What kind of tribute is this?</label>
        <div class="flex flex-wrap gap-2 mb-4">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition text-gray-700 dark:text-white has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                <input type="radio" name="tribute-type" value="flower" class="sr-only" />Flower
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition text-gray-700 dark:text-white has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                <input type="radio" name="tribute-type" value="candle" class="sr-only" />Candle
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-medium transition text-gray-700 dark:text-white has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-500/20 border-gray-200 dark:border-gray-700">
                <input type="radio" name="tribute-type" value="prayer" class="sr-only" checked />Prayer
            </label>
        </div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your message</label>
        <div id="tribute-editor" class="min-h-[120px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
        <input type="hidden" id="tribute-note-message" />
    </div>
    <button type="button" id="tribute-note-submit" class="btn btn-primary btn-md">Leave Tribute</button>
</div>
