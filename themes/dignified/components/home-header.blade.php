@php
    use App\Helpers\BrandingHelper;
    use App\Helpers\SiteShareMetaHelper;
    use App\Helpers\ThemeSetting;

    $appName = SiteShareMetaHelper::appDisplayName();
    $currentRoute = request()->route()?->getName();

    // Whose site this is decides whether the platform's marketing links belong in the nav.
    // Same rule the base template follows — see its comment; on a reseller's own domain a
    // link to our Pricing walks their visitor onto our site.
    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');

    $headerNavItems = $headerNavItems ?? collect();

    // A reseller's front page arrives under one of four route names depending on how the
    // site is reached — the platform host, their subdomain, their own domain, or the
    // /r/{slug} development fallback. Comparing against 'home' alone left the nav with no
    // active item on every reseller site, which is where it matters most.
    $homeRoutes = ['home', 'reseller.public.index', 'reseller.public.index-custom-domain', 'reseller.public.index-path'];
    $onHome = in_array($currentRoute, $homeRoutes, true);

    // No settings key exists for these yet, so they resolve to nothing and the row simply
    // collapses. Written as ThemeSetting reads rather than hardcoded, so the day the key is
    // added — platform-wide or per reseller — this fills in with no change here.
    $phone = ThemeSetting::get('branding.contact_phone');
    $email = BrandingHelper::contactEmail();

    // The nav the design shows, resolved to whatever this site actually has. A reseller who
    // has built their own header menu gets theirs; everyone else gets the standard set.
    $fallbackNav = collect([
        ['label' => 'Home', 'url' => $homeUrl, 'route' => 'home'],
        ['label' => 'About Us', 'url' => \App\Support\StandardPages::urlForRouteName('about'), 'route' => 'about'],
        ['label' => 'Pricing', 'url' => \App\Support\StandardPages::urlForRouteName('pricing'), 'route' => 'pricing'],
        ['label' => 'Find a Memorial', 'url' => \App\Support\StandardPages::urlForRouteName('memorial.directory'), 'route' => 'memorial.directory'],
        ['label' => 'Contact Us', 'url' => \App\Support\StandardPages::urlForRouteName('contact'), 'route' => 'contact'],
    ])->filter(fn ($i) => filled($i['url']))->values();

    // The account link is for the people who run the site, not the families who use it.
    // A client's /dashboard is their own memorial, which they reach from the memorial page
    // itself — putting a "Dashboard" link in a funeral home's public navigation tells every
    // grieving visitor there is an admin area they are missing.
    // route(), not SiteUrl::to(): the dashboard is an application screen that exists on
    // every host, not a page on the tenant's public site. SiteUrl would build
    // /r/{slug}/dashboard under the path fallback, which is not a route and 404s.
    // ResolveResellerByHost re-roots generated URLs at the reseller's own host, so on a real
    // reseller domain this already resolves to theirs.
    $staffUser = auth()->user();
    $showAccountLink = $staffUser !== null && $staffUser->hasRole(['admin', 'super-admin', 'reseller']);

    $navLinkClass = 'flex flex-1 items-center justify-center whitespace-nowrap px-4 py-4 text-[11px] font-bold uppercase tracking-[0.14em] transition-colors duration-200 ease-out';
@endphp

<header x-data="{ mobileOpen: false }">
    {{-- Top bar: the identity and the two things a grieving family looks for first. --}}
    <div class="bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-4 sm:px-6 lg:py-5 lg:px-8">
            <a href="{{ $homeUrl }}" class="flex min-w-0 shrink items-center">
                <img src="{{ BrandingHelper::logoUrl() }}" alt="{{ $appName }}"
                    class="h-14 w-auto max-w-[210px] object-contain object-left sm:h-[68px] sm:max-w-[280px] lg:h-[78px] lg:max-w-[340px]" />
            </a>

            <div class="ml-auto hidden items-center gap-8 md:flex xl:gap-14">
                @if ($phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}"
                        class="flex items-center gap-2.5 text-[13px] text-[var(--dg-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--dg-red)]">
                        <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 6.5 6.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4.5 5.2 2 2 0 0 1 6.5 3z" />
                        </svg>
                        <span>{{ $phone }}</span>
                    </a>
                @endif

                @if ($email)
                    <a href="mailto:{{ $email }}"
                        class="flex items-center gap-2.5 text-[13px] text-[var(--dg-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--dg-red)]">
                        <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2.5" y="5" width="19" height="14" rx="1.5" />
                            <path d="m3 6.5 9 6 9-6" />
                        </svg>
                        <span>{{ $email }}</span>
                    </a>
                @endif
            </div>

            {{-- The hamburger lives up here on small screens, where the dark nav bar below
                 collapses away entirely. --}}
            <button type="button" @click="mobileOpen = !mobileOpen"
                class="ml-auto inline-flex h-11 w-11 items-center justify-center text-[var(--dg-ink)] lg:hidden"
                :aria-expanded="mobileOpen ? 'true' : 'false'" aria-label="Menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path x-show="!mobileOpen" d="M4 7h16M4 12h16M4 17h16" />
                    <path x-show="mobileOpen" x-cloak d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Nav bar. The active item is a solid crimson block running the full height of the bar
         rather than an underline — at 11px uppercase, an underline is not visible enough to
         orient anyone. --}}
    <nav class="hidden bg-[var(--dg-ink)] lg:block">
        <div class="mx-auto flex max-w-7xl px-4 sm:px-6 lg:px-8">
            @forelse ($headerNavItems as $navItem)
                <a href="{{ $navItem->resolvedUrl() }}"
                    @if ($navItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                    @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                    class="{{ $navLinkClass }} {{ $navItem->isActive($currentRoute) ? 'bg-[var(--dg-red)] text-white' : 'text-white/85 hover:bg-white/[0.07] hover:text-white' }}">{{ $navItem->label }}</a>
            @empty
                @foreach ($fallbackNav as $item)
                    <a href="{{ $item['url'] }}"
                        @if (($item['route'] === 'home' ? $onHome : $currentRoute === $item['route'])) aria-current="page" @endif
                        class="{{ $navLinkClass }} {{ ($item['route'] === 'home' ? $onHome : $currentRoute === $item['route']) ? 'bg-[var(--dg-red)] text-white' : 'text-white/85 hover:bg-white/[0.07] hover:text-white' }}">{{ $item['label'] }}</a>
                @endforeach
            @endforelse

            @if ($showAccountLink)
                <div class="flex shrink-0 items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center whitespace-nowrap px-5 py-4 text-[11px] font-bold uppercase tracking-[0.14em] text-white/70 transition-colors duration-200 ease-out hover:text-white">Dashboard</a>
                </div>
            @endif
        </div>
    </nav>

    {{-- Mobile menu. Same items, same order, stacked — a visitor who has used the desktop
         site should not have to relearn where anything is. --}}
    <div x-show="mobileOpen" x-cloak x-collapse class="bg-[var(--dg-ink)] lg:hidden">
        <div class="mx-auto max-w-7xl px-4 py-2 sm:px-6">
            @forelse ($headerNavItems as $navItem)
                <a href="{{ $navItem->resolvedUrl() }}"
                    @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                    class="block border-b border-white/10 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] {{ $navItem->isActive($currentRoute) ? 'text-[var(--dg-gold)]' : 'text-white/85' }}">{{ $navItem->label }}</a>
            @empty
                @foreach ($fallbackNav as $item)
                    <a href="{{ $item['url'] }}"
                        @if (($item['route'] === 'home' ? $onHome : $currentRoute === $item['route'])) aria-current="page" @endif
                        class="block border-b border-white/10 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] {{ ($item['route'] === 'home' ? $onHome : $currentRoute === $item['route']) ? 'text-[var(--dg-gold)]' : 'text-white/85' }}">{{ $item['label'] }}</a>
                @endforeach
            @endforelse

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 py-4 text-[13px] text-white/70">
                @if ($phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif
                @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                @if ($showAccountLink)
                    <a href="{{ route('dashboard') }}" class="text-[var(--dg-gold)]">Dashboard</a>
                @endif
            </div>
        </div>
    </div>
</header>
