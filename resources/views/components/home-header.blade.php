@php
    $isAdmin = auth()->user()?->hasRole(['admin', 'super-admin']);
    $appName = \App\Models\SystemSetting::get('branding.app_name', 'Forever-Loved');
@endphp

<header class="sticky top-0 z-40 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900" x-data="{ mobileOpen: false }">
    <div class="mx-auto flex h-14 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-gray-800 dark:text-white/90 hover:text-brand-500">
            <img class="dark:hidden h-8 w-auto" src="{{ \App\Helpers\BrandingHelper::logoUrl() }}" alt="{{ $appName }}" />
            <img class="hidden dark:block h-8 w-auto" src="{{ \App\Helpers\BrandingHelper::logoDarkUrl() }}" alt="{{ $appName }}" />
        </a>

        {{-- Desktop search --}}
        <div class="hidden flex-1 max-w-md lg:block"
            x-data="{
                query: '',
                results: [],
                open: false,
                loading: false,
                debounceTimer: null,
                selected: -1,
                search() {
                    clearTimeout(this.debounceTimer);
                    if (this.query.length < 2) { this.results = []; this.open = false; return; }
                    this.loading = true;
                    this.debounceTimer = setTimeout(() => {
                        fetch(`{{ route('memorials.search') }}?q=${encodeURIComponent(this.query)}`)
                            .then(r => r.json())
                            .then(data => {
                                this.results = data.results;
                                this.open = this.results.length > 0;
                                this.selected = -1;
                                this.loading = false;
                            })
                            .catch(() => { this.loading = false; });
                    }, 300);
                },
                navigate(url) { window.location.href = url; },
                onKeydown(e) {
                    if (!this.open) return;
                    if (e.key === 'ArrowDown') { e.preventDefault(); this.selected = Math.min(this.selected + 1, this.results.length - 1); }
                    else if (e.key === 'ArrowUp') { e.preventDefault(); this.selected = Math.max(this.selected - 1, 0); }
                    else if (e.key === 'Enter' && this.selected >= 0) { e.preventDefault(); this.navigate(this.results[this.selected].url); }
                    else if (e.key === 'Escape') { this.open = false; }
                }
            }"
            @click.away="open = false">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input
                    type="search"
                    x-model="query"
                    @input="search()"
                    @keydown="onKeydown($event)"
                    @focus="if (results.length) open = true"
                    placeholder="Search memorials..."
                    autocomplete="off"
                    class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 pl-9 pr-4 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-brand-300 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20" />
                <svg x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>
            <div x-show="open" x-cloak x-transition.opacity
                class="absolute z-50 mt-1 w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl overflow-hidden">
                <template x-for="(item, idx) in results" :key="item.slug">
                    <a :href="item.url"
                       class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60"
                       :class="{ 'bg-gray-50 dark:bg-gray-700/60': selected === idx }"
                       @mouseenter="selected = idx">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                            <template x-if="item.photo"><img :src="item.photo" :alt="item.name" class="h-full w-full object-cover" /></template>
                            <template x-if="!item.photo"><div class="flex h-full w-full items-center justify-center text-sm font-semibold text-gray-400 dark:text-gray-500" x-text="item.name?.charAt(0)?.toUpperCase()"></div></template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white/90" x-text="item.name"></p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="item.profession || ''"></span>
                                <span x-show="item.profession && item.years"> &middot; </span>
                                <span x-text="item.years || ''"></span>
                            </p>
                        </div>
                        <svg class="h-4 w-4 flex-shrink-0 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </template>
                <template x-if="query.length >= 2 && !loading && results.length === 0">
                    <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        No memorials found for "<span x-text="query" class="font-medium"></span>"
                    </div>
                </template>
            </div>
        </div>

        {{-- Desktop nav (hidden on mobile) --}}
        <nav class="hidden md:flex items-center gap-1">
            <button type="button" @click="$store.theme.toggle()" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-200" aria-label="Toggle theme">
                <svg class="hidden dark:block fill-current" width="18" height="18" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z" fill="currentColor"/></svg>
                <svg class="fill-current dark:hidden" width="18" height="18" viewBox="0 0 20 20"><path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z" fill="currentColor"/></svg>
            </button>
            <a href="{{ route('home') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200">Home</a>
            <a href="{{ route('memorial.directory') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-800 dark:hover:text-gray-200">Find Memorial</a>
            @if ($isAdmin)
                <a href="{{ route('memorials.index') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Dashboard</a>
            @elseif (auth()->check())
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/25 text-xs font-semibold text-brand-600 dark:text-brand-400">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                        <span class="hidden lg:inline max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                        <svg class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                        class="absolute right-0 mt-1 w-56 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-xl">
                        <div class="border-b border-gray-100 dark:border-gray-700 px-3 pb-2 mb-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white/90 truncate">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ auth()->user()->email }}</p>
                        </div>
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            Dashboard
                        </a>
                        <a href="{{ route('memorials.index') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            My Memorials
                        </a>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Edit Profile
                        </a>
                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('register') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 transition">Sign up</a>
            @endif
        </nav>

        {{-- Mobile: theme toggle + hamburger (visible below md) --}}
        <div class="flex items-center gap-1 md:hidden">
            <button type="button" @click="$store.theme.toggle()" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Toggle theme">
                <svg class="hidden dark:block fill-current" width="18" height="18" viewBox="0 0 20 20"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001Z" fill="currentColor"/></svg>
                <svg class="fill-current dark:hidden" width="18" height="18" viewBox="0 0 20 20"><path d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z" fill="currentColor"/></svg>
            </button>
            <button type="button" @click="mobileOpen = !mobileOpen" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Menu">
                <svg x-show="!mobileOpen" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileOpen" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu panel --}}
    <div x-show="mobileOpen" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 md:hidden">
        {{-- Mobile search --}}
        <div class="px-4 pt-3 pb-2"
            x-data="{
                query: '',
                results: [],
                open: false,
                loading: false,
                debounceTimer: null,
                search() {
                    clearTimeout(this.debounceTimer);
                    if (this.query.length < 2) { this.results = []; this.open = false; return; }
                    this.loading = true;
                    this.debounceTimer = setTimeout(() => {
                        fetch(`{{ route('memorials.search') }}?q=${encodeURIComponent(this.query)}`)
                            .then(r => r.json())
                            .then(data => {
                                this.results = data.results;
                                this.open = this.results.length > 0;
                                this.loading = false;
                            })
                            .catch(() => { this.loading = false; });
                    }, 300);
                },
                navigate(url) { window.location.href = url; }
            }">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" x-model="query" @input="search()" placeholder="Search memorials..." autocomplete="off"
                    class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 pl-9 pr-4 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
            </div>
            <div x-show="open" class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg overflow-hidden max-h-60 overflow-y-auto">
                <template x-for="item in results" :key="item.slug">
                    <a :href="item.url" class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700/60">
                        <div class="h-8 w-8 shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                            <template x-if="item.photo"><img :src="item.photo" :alt="item.name" class="h-full w-full object-cover" /></template>
                            <template x-if="!item.photo"><div class="flex h-full w-full items-center justify-center text-xs font-semibold text-gray-400" x-text="item.name?.charAt(0)?.toUpperCase()"></div></template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white/90" x-text="item.name"></p>
                            <p class="truncate text-xs text-gray-500" x-text="item.years || ''"></p>
                        </div>
                    </a>
                </template>
            </div>
        </div>

        {{-- Mobile nav links --}}
        <nav class="space-y-0.5 px-4 pb-3">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Home
            </a>
            <a href="{{ route('memorial.directory') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Find Memorial
            </a>
            @if ($isAdmin)
                <a href="{{ route('memorials.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard
                </a>
            @elseif (auth()->check())
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('memorials.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    My Memorials
                </a>
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Edit Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Sign out
                    </button>
                </form>
            @else
                <div class="pt-2">
                    <a href="{{ route('register') }}" class="flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition">Sign up</a>
                </div>
            @endif
        </nav>
    </div>
</header>
