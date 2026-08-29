{{--
    The first-visit invitation to turn on notifications.

    A browser's permission cannot be forced and should not be ambushed. Calling
    requestPermission() on load is rejected outright by Safari and Firefox, and Chrome answers a
    site whose prompts get dismissed by collapsing them into a bell icon in the address bar —
    permanently, for the whole domain. Worse, a dismissal is a *denial* that lasts until someone
    goes into browser settings to undo it. One badly timed prompt costs that visitor forever.

    So this is the soft prompt: our own card, which costs nothing if ignored, and which opens the
    real dialog only when somebody taps it. Shown once — a visitor who says "not now" is not
    asked again on this device, because the second ask is what makes people block.

    It waits for the page to be read rather than appearing on arrival. Someone who followed a
    link to a memorial came to look at a face, and a request for anything at all before they have
    seen it is the wrong first thing to say to them.

    @param \App\Models\Memorial $memorial
--}}
@php
    $pushEnabled = (bool) \App\Models\SystemSetting::get('notifications.push_enabled', false)
        && filled(\App\Models\SystemSetting::get('notifications.vapid_public_key'));
    $firstName = \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'this memorial');
@endphp

@if ($pushEnabled)
    <div id="push-invite"
         role="dialog"
         aria-labelledby="push-invite-title"
         aria-describedby="push-invite-body"
         class="pointer-events-none fixed inset-x-0 bottom-0 z-40 hidden justify-center p-4 sm:p-6">
        <div class="pointer-events-auto w-full max-w-md rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl dark:border-gray-700 dark:bg-gray-800 sm:p-5">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="push-invite-title" class="text-sm font-semibold text-gray-900 dark:text-white/90">
                        Stay with {{ $firstName }}'s page
                    </h2>
                    <p id="push-invite-body" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        We'll let you know when family add stories, photos or tributes. No account needed.
                    </p>
                </div>
            </div>

            {{-- "Not now" is deliberately not a button-shaped button. On a template whose
                 secondary style is a filled crimson block it came out louder than the thing it
                 declines, and a card that shouts its own refusal is a card designed to be
                 refused. Quiet text, easy to hit, impossible to mistake for the primary. --}}
            <div class="mt-4 flex items-center gap-2">
                <button type="button" data-push-invite-accept class="btn btn-primary btn-sm flex-1 active:scale-[0.98]">
                    Turn on
                </button>
                <button type="button" data-push-invite-dismiss
                    class="shrink-0 px-4 py-2 text-xs font-medium text-gray-500 transition-colors duration-150 hover:text-gray-800 active:scale-[0.97] motion-reduce:active:scale-100 dark:text-gray-400 dark:hover:text-gray-200">
                    Not now
                </button>
            </div>
        </div>
    </div>
@endif
