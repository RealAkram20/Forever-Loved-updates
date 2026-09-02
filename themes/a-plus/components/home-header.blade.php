@php
    use App\Helpers\BrandingHelper;
    use App\Helpers\SiteShareMetaHelper;
    use App\Helpers\ThemeSetting;

    $appName = SiteShareMetaHelper::appDisplayName();
    $currentRoute = request()->route()?->getName();

    // Whose site this is decides whether the platform's marketing links belong in the nav. On a
    // reseller's own domain a link to our Pricing walks their visitor onto our site, which is
    // the bug ThemeConformanceTest has caught three times in three different disguises.
    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');

    $headerNavItems = $headerNavItems ?? collect();

    // A reseller's front page arrives under one of four route names depending on how the site
    // is reached — the platform host, their subdomain, their own domain, or the /r/{slug}
    // development fallback. Comparing against 'home' alone leaves the nav with no active item
    // on every reseller site, which is where it matters most.
    $homeRoutes = ['home', 'reseller.public.index', 'reseller.public.index-custom-domain', 'reseller.public.index-path'];
    $onHome = in_array($currentRoute, $homeRoutes, true);

    $phone = ThemeSetting::get('branding.contact_phone');
    $email = BrandingHelper::contactEmail();
    $address = ThemeSetting::get('branding.contact_address');

    // An address setting is a textarea, so it arrives with newlines in it. Joined for the one
    // place the header still shows it — the mobile menu, where it is one line under the phone.
    $addressLine = collect(preg_split('/\r\n|\r|\n/', (string) $address))
        ->map(fn ($l) => trim($l))->filter()->implode(', ');

    $telHref = fn ($number) => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $number);

    $fallbackNav = collect([
        ['label' => 'Home', 'url' => $homeUrl, 'route' => 'home'],
        ['label' => 'About Us', 'url' => \App\Support\StandardPages::urlForRouteName('about'), 'route' => 'about'],
        ['label' => 'Pricing', 'url' => \App\Support\StandardPages::urlForRouteName('pricing'), 'route' => 'pricing'],
        ['label' => 'Find a Memorial', 'url' => \App\Support\StandardPages::urlForRouteName('memorial.directory'), 'route' => 'memorial.directory'],
        ['label' => 'Contact Us', 'url' => \App\Support\StandardPages::urlForRouteName('contact'), 'route' => 'contact'],
    ])->filter(fn ($i) => filled($i['url']))->values();

    // The account link is for the people who run the site, not the families who use it. A
    // client's /dashboard is their own memorial, reached from the memorial page itself —
    // putting "Dashboard" in a funeral home's public navigation tells every grieving visitor
    // there is an admin area they are missing.
    $staffUser = auth()->user();
    $showAccountLink = $staffUser !== null && $staffUser->hasRole(['admin', 'super-admin', 'reseller']);

    $navLinkClass = 'ap-nav-link flex items-center whitespace-nowrap px-3.5 py-6 text-[13.5px] font-semibold text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]';
@endphp

{{--
    One white bar, and nothing above it.

    The template shipped on 2026-08-31 opened with a blue utility strip carrying the address and
    the phone number over the identity bar. The final mockup has no strip: the page now begins
    on a pale blue hero, and a saturated blue band directly above it put two strong horizontal
    edges in the first 100px of the site.

    Nothing was lost that is not somewhere else. The phone number is the header's own call to
    action and the first row of the mobile menu; the address, the second number, the email and
    the opening hours are all in the footer's Contact column, which is where a visitor looks
    for them anyway.
--}}
<header class="ap-header bg-white" x-data="{ mobileOpen: false }">
    <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ $homeUrl }}" class="flex min-w-0 shrink items-center py-3">
            <img src="{{ BrandingHelper::logoUrl() }}" alt="{{ $appName }}"
                class="h-11 w-auto max-w-[180px] object-contain object-left sm:h-12 sm:max-w-[220px] lg:h-14 lg:max-w-[260px]" />
        </a>

        <nav class="ml-auto hidden items-center lg:flex">
            @forelse ($headerNavItems as $navItem)
                <a href="{{ $navItem->resolvedUrl() }}"
                    @if ($navItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                    @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                    class="{{ $navLinkClass }}">{{ $navItem->label }}</a>
            @empty
                @foreach ($fallbackNav as $item)
                    @php $active = $item['route'] === 'home' ? $onHome : $currentRoute === $item['route']; @endphp
                    <a href="{{ $item['url'] }}"
                        @if ($active) aria-current="page" @endif
                        class="{{ $navLinkClass }}">{{ $item['label'] }}</a>
                @endforeach
            @endforelse

            @if ($showAccountLink)
                {{-- route(), not SiteUrl::to(): the dashboard is an application screen that
                     exists on every host, not a page on the tenant's public site. SiteUrl
                     would build /r/{slug}/dashboard under the path fallback, which is not a
                     route and 404s. --}}
                <a href="{{ route('dashboard') }}" class="{{ $navLinkClass }}">Dashboard</a>
            @endif
        </nav>

        @if ($phone)
            {{-- The visibility lives on a wrapper, not on the button.

                 `.t-btn` sets `display: inline-flex`, and it is plain CSS written after
                 `@import 'tailwindcss'` in app.css — so it sits outside the utility layer
                 and beats `hidden` on the same element. Putting `hidden lg:block` on the
                 anchor did nothing: the pill stayed visible at every width, the header row
                 came to about 430px against a 390px viewport, and the whole page scrolled
                 sideways with the hero heading and every card clipped at the right edge.
                 Found by rendering it at 390px; invisible at desktop widths and invisible
                 to the test suite, which asserts markup rather than layout. --}}
            <div class="ml-auto hidden shrink-0 lg:ml-5 lg:block">
                <a href="{{ $telHref($phone) }}"
                    class="t-btn bg-[var(--ap-blue)] text-white transition-[filter] duration-200 ease-out hover:brightness-110">
                    <span>Reach Us 24/7</span>
                </a>
            </div>
        @endif

        <button type="button" @click="mobileOpen = !mobileOpen"
            class="ml-auto inline-flex h-11 w-11 items-center justify-center text-[var(--ap-ink)] lg:hidden"
            :aria-expanded="mobileOpen ? 'true' : 'false'" aria-label="Menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <path x-show="!mobileOpen" d="M4 7h16M4 12h16M4 17h16" />
                <path x-show="mobileOpen" x-cloak d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>
    </div>

    {{-- The mobile menu. Same items, same order, stacked — a visitor who has used the desktop
         site should not have to relearn where anything is. White rather than the navy drawer
         the old template used: the header it drops out of is white, and a navy panel hanging
         off a white bar reads as a different site's menu. --}}
    <div x-show="mobileOpen" x-cloak x-collapse class="border-t border-[var(--ap-line)] bg-white lg:hidden">
        <div class="mx-auto max-w-7xl px-4 py-2 sm:px-6">
            @forelse ($headerNavItems as $navItem)
                <a href="{{ $navItem->resolvedUrl() }}"
                    @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                    class="block border-b border-[var(--ap-line)] py-3.5 text-[14px] font-semibold {{ $navItem->isActive($currentRoute) ? 'text-[var(--ap-blue)]' : 'text-[var(--ap-ink)]' }}">{{ $navItem->label }}</a>
            @empty
                @foreach ($fallbackNav as $item)
                    @php $active = $item['route'] === 'home' ? $onHome : $currentRoute === $item['route']; @endphp
                    <a href="{{ $item['url'] }}"
                        @if ($active) aria-current="page" @endif
                        class="block border-b border-[var(--ap-line)] py-3.5 text-[14px] font-semibold {{ $active ? 'text-[var(--ap-blue)]' : 'text-[var(--ap-ink)]' }}">{{ $item['label'] }}</a>
                @endforeach
            @endforelse

            @if ($showAccountLink)
                <a href="{{ route('dashboard') }}" class="block border-b border-[var(--ap-line)] py-3.5 text-[14px] font-semibold text-[var(--ap-ink-soft)]">Dashboard</a>
            @endif

            <div class="space-y-2.5 py-4 text-[13px] text-[var(--ap-ink-soft)]">
                @if ($phone)
                    <a href="{{ $telHref($phone) }}" class="flex items-center gap-2.5">
                        <x-icon name="phone" class="h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                        <span>{{ $phone }}</span>
                    </a>
                @endif

                @if ($email)
                    <a href="mailto:{{ $email }}" class="flex items-center gap-2.5">
                        <x-icon name="mail" class="h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                        <span>{{ $email }}</span>
                    </a>
                @endif

                @if (filled($addressLine))
                    <p class="flex items-start gap-2.5">
                        <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                        <span>{{ $addressLine }}</span>
                    </p>
                @endif
            </div>
        </div>
    </div>
</header>
