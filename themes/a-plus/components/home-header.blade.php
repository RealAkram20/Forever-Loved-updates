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

    // The utility strip runs the address on one line. An address setting is a textarea, so it
    // arrives with newlines in it; joined rather than printed, because a two-line block in a
    // 40px bar pushes the whole header down.
    $addressLine = collect(preg_split('/\r\n|\r|\n/', (string) $address))
        ->map(fn ($l) => trim($l))->filter()->implode(', ');

    $telHref = fn ($number) => 'tel:'.preg_replace('/[^0-9+]/', '', (string) $number);

    $socials = collect([
        ['key' => 'facebook', 'url' => ThemeSetting::get('branding.social_facebook'), 'label' => 'Facebook'],
        ['key' => 'twitter', 'url' => ThemeSetting::get('branding.social_twitter'), 'label' => 'X'],
        ['key' => 'instagram', 'url' => ThemeSetting::get('branding.social_instagram'), 'label' => 'Instagram'],
    ])->filter(fn ($s) => filled($s['url']))->values();

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

    $navLinkClass = 'ap-nav-link flex items-center whitespace-nowrap px-3 py-6 text-[13px] font-semibold transition-colors duration-200 ease-out';
@endphp

<header class="ap-header" x-data="{ mobileOpen: false }">
    {{-- The utility strip: where they are and how to reach them, above everything else. On a
         phone this collapses to nothing rather than wrapping to three lines — the same two
         facts are in the mobile menu, and a 40px bar that becomes 120px pushes the logo off
         the first screen. --}}
    <div class="hidden bg-[var(--ap-blue)] text-white md:block">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 py-2.5 text-[12px] sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-6">
                @if (filled($addressLine))
                    <span class="flex min-w-0 items-center gap-2">
                        <x-icon name="map-pin" class="h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                        <span class="truncate">{{ $addressLine }}</span>
                    </span>
                @endif

                @if ($phone)
                    <a href="{{ $telHref($phone) }}" class="flex items-center gap-2 transition-colors duration-200 ease-out hover:text-[var(--ap-gold)]">
                        <x-icon name="phone" class="h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                        <span>{{ $phone }}</span>
                    </a>
                @endif
            </div>

            @if ($socials->isNotEmpty() || $email)
                <div class="ml-auto flex shrink-0 items-center gap-4">
                    @foreach ($socials as $social)
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                            aria-label="{{ $social['label'] }}"
                            class="text-white/75 transition-colors duration-200 ease-out hover:text-[var(--ap-gold)]">
                            @switch($social['key'])
                                @case('facebook')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5H16.7V3.6c-.3 0-1.3-.13-2.47-.13-2.44 0-4.11 1.49-4.11 4.23V9.9H7.4V13h2.72v8z" /></svg>
                                    @break
                                @case('twitter')
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.2 3h3.3l-7.2 8.2L21.8 21h-6.6l-4.4-5.7L5.7 21H2.4l7.7-8.8L2.5 3h6.8l4 5.3zm-1.2 16h1.8L8.1 4.8H6.2z" /></svg>
                                    @break
                                @default
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" /><circle cx="12" cy="12" r="3.7" /><circle cx="16.9" cy="7.1" r="1.1" fill="currentColor" stroke="none" /></svg>
                            @endswitch
                        </a>
                    @endforeach

                    @if ($email)
                        <a href="mailto:{{ $email }}" aria-label="Email"
                            class="text-white/75 transition-colors duration-200 ease-out hover:text-[var(--ap-gold)]">
                            <x-icon name="mail" class="h-4 w-4" />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- The identity bar: logo, navigation, and the one action a family in the first hour of a
         bereavement actually wants. --}}
    <div class="bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-6 px-4 sm:px-6 lg:px-8">
            <a href="{{ $homeUrl }}" class="flex min-w-0 shrink items-center py-3">
                <img src="{{ BrandingHelper::logoUrl() }}" alt="{{ $appName }}"
                    class="h-12 w-auto max-w-[190px] object-contain object-left sm:h-14 sm:max-w-[240px] lg:h-16 lg:max-w-[280px]" />
            </a>

            <nav class="ml-auto hidden items-center lg:flex">
                @forelse ($headerNavItems as $navItem)
                    <a href="{{ $navItem->resolvedUrl() }}"
                        @if ($navItem->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                        @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                        class="{{ $navLinkClass }} {{ $navItem->isActive($currentRoute) ? 'text-[var(--ap-blue)]' : 'text-[var(--ap-ink)] hover:text-[var(--ap-blue)]' }}">{{ $navItem->label }}</a>
                @empty
                    @foreach ($fallbackNav as $item)
                        @php $active = $item['route'] === 'home' ? $onHome : $currentRoute === $item['route']; @endphp
                        <a href="{{ $item['url'] }}"
                            @if ($active) aria-current="page" @endif
                            class="{{ $navLinkClass }} {{ $active ? 'text-[var(--ap-blue)]' : 'text-[var(--ap-ink)] hover:text-[var(--ap-blue)]' }}">{{ $item['label'] }}</a>
                    @endforeach
                @endforelse

                @if ($showAccountLink)
                    {{-- route(), not SiteUrl::to(): the dashboard is an application screen that
                         exists on every host, not a page on the tenant's public site. SiteUrl
                         would build /r/{slug}/dashboard under the path fallback, which is not a
                         route and 404s. --}}
                    <a href="{{ route('dashboard') }}"
                        class="{{ $navLinkClass }} text-[var(--ap-ink-soft)] hover:text-[var(--ap-blue)]">Dashboard</a>
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
                <div class="ml-auto hidden shrink-0 lg:ml-6 lg:block">
                    <a href="{{ $telHref($phone) }}"
                        class="t-btn gap-2 bg-[var(--ap-gold)] text-[var(--ap-navy)] hover:brightness-95">
                        <x-icon name="phone" class="h-4 w-4" />
                        <span>Call Us Now</span>
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
    </div>

    {{-- The mobile menu. Same items, same order, stacked — a visitor who has used the desktop
         site should not have to relearn where anything is. It also carries the phone number and
         address that the utility strip drops at this width, so nothing is only on one screen. --}}
    <div x-show="mobileOpen" x-cloak x-collapse class="bg-[var(--ap-navy)] lg:hidden">
        <div class="mx-auto max-w-7xl px-4 py-2 sm:px-6">
            @forelse ($headerNavItems as $navItem)
                <a href="{{ $navItem->resolvedUrl() }}"
                    @if ($navItem->isActive($currentRoute)) aria-current="page" @endif
                    class="block border-b border-white/10 py-3.5 text-[14px] font-semibold {{ $navItem->isActive($currentRoute) ? 'text-[var(--ap-gold)]' : 'text-white/85' }}">{{ $navItem->label }}</a>
            @empty
                @foreach ($fallbackNav as $item)
                    @php $active = $item['route'] === 'home' ? $onHome : $currentRoute === $item['route']; @endphp
                    <a href="{{ $item['url'] }}"
                        @if ($active) aria-current="page" @endif
                        class="block border-b border-white/10 py-3.5 text-[14px] font-semibold {{ $active ? 'text-[var(--ap-gold)]' : 'text-white/85' }}">{{ $item['label'] }}</a>
                @endforeach
            @endforelse

            @if ($showAccountLink)
                <a href="{{ route('dashboard') }}" class="block border-b border-white/10 py-3.5 text-[14px] font-semibold text-white/70">Dashboard</a>
            @endif

            <div class="space-y-2 py-4 text-[13px] text-white/70">
                @if ($phone)
                    <a href="{{ $telHref($phone) }}" class="flex items-center gap-2.5">
                        <x-icon name="phone" class="h-4 w-4 text-[var(--ap-gold)]" />
                        <span>{{ $phone }}</span>
                    </a>
                @endif

                @if ($email)
                    <a href="mailto:{{ $email }}" class="flex items-center gap-2.5">
                        <x-icon name="mail" class="h-4 w-4 text-[var(--ap-gold)]" />
                        <span>{{ $email }}</span>
                    </a>
                @endif

                @if (filled($addressLine))
                    <p class="flex items-start gap-2.5">
                        <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                        <span>{{ $addressLine }}</span>
                    </p>
                @endif
            </div>
        </div>
    </div>
</header>
