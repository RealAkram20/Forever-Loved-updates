@props(['reseller'])

{{--
    Row actions for the reseller roster. Only the everyday three live here — view, support
    login, and suspend/reactivate. Partnership-lifecycle actions (rollover, restore, manual
    domain verification) stay on the detail page, where there's room to say what they do
    before someone commits to one.

    Positioned `fixed` off the trigger's bounding rect rather than absolutely, because the
    table scrolls horizontally and an overflow container would clip an absolute panel.
--}}
<div x-data="{
        open: false,
        x: 0,
        y: 0,
        toggle() {
            if (this.open) { this.open = false; return; }
            const r = this.$refs.trigger.getBoundingClientRect();
            this.x = r.right;
            this.y = r.bottom + 6;
            this.open = true;
        }
    }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    @scroll.window="open = false"
    @resize.window="open = false"
    class="inline-block">

    <button type="button" x-ref="trigger" @click="toggle()" :aria-expanded="open" aria-haspopup="menu"
        class="rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
        <span class="sr-only">Actions for {{ $reseller->name }}</span>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
    </button>

    <div x-show="open" x-cloak role="menu"
        :style="{ top: y + 'px', left: (x - 208) + 'px' }"
        x-transition:enter="transition duration-150 ease-[cubic-bezier(0.23,1,0.32,1)]"
        x-transition:enter-start="opacity-0 scale-[0.96]" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition duration-100 ease-out"
        x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
        class="fixed z-[999999] w-52 origin-top-right rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-1.5 text-left shadow-lg">

        @php
            $itemClass = 'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800';
        @endphp

        <a href="{{ route('settings.resellers.show', $reseller) }}" role="menuitem" class="{{ $itemClass }}">
            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/></svg>
            View details
        </a>

        <form action="{{ route('settings.resellers.login-as', $reseller) }}" method="POST" role="none">
            @csrf
            <button type="submit" role="menuitem" class="{{ $itemClass }}">
                <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3.75h3.25a1.5 1.5 0 0 1 1.5 1.5v13.5a1.5 1.5 0 0 1-1.5 1.5H15M10.5 15.75 14.25 12 10.5 8.25M14.25 12H4"/></svg>
                Log in as owner
            </button>
        </form>

        <div class="my-1.5 h-px bg-gray-100 dark:bg-gray-800"></div>

        @if ($reseller->isActive())
            <form action="{{ route('settings.resellers.suspend', $reseller) }}" method="POST" role="none"
                onsubmit="return confirm('Suspend {{ addslashes($reseller->name) }}? Their public memorials and subdomain keep working — only their own dashboard access is blocked.')">
                @csrf
                <button type="submit" role="menuitem" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-red-600 transition-colors duration-150 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="12" cy="12" r="8.25"/><path d="m6.5 6.5 11 11"/></svg>
                    Suspend
                </button>
            </form>
        @else
            <form action="{{ route('settings.resellers.activate', $reseller) }}" method="POST" role="none">
                @csrf
                <button type="submit" role="menuitem" class="{{ $itemClass }}">
                    <svg class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.5 5 5 10-11"/></svg>
                    Reactivate
                </button>
            </form>
        @endif
    </div>
</div>
