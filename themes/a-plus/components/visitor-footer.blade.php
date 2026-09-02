@php
    use App\Helpers\BrandingHelper;
    use App\Helpers\SiteShareMetaHelper;
    use App\Helpers\ThemeSetting;

    $appName = SiteShareMetaHelper::appDisplayName();
    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');

    $footerQuickItems = $footerQuickItems ?? collect();
    $footerCompanyItems = $footerCompanyItems ?? collect();

    // Their own words or none. ThemeSetting::get() would fall through to the platform's line,
    // which is our marketing sitting under their logo.
    $tagline = ThemeSetting::tenantOwn('branding.tagline');
    $address = ThemeSetting::get('branding.contact_address');
    $phone = ThemeSetting::get('branding.contact_phone');
    $phoneAlt = ThemeSetting::get('branding.contact_phone_alt');
    $email = BrandingHelper::contactEmail();
    $hours = ThemeSetting::get('branding.contact_hours');

    $socials = collect([
        ['key' => 'facebook', 'url' => ThemeSetting::get('branding.social_facebook'), 'label' => 'Facebook'],
        ['key' => 'instagram', 'url' => ThemeSetting::get('branding.social_instagram'), 'label' => 'Instagram'],
        ['key' => 'twitter', 'url' => ThemeSetting::get('branding.social_twitter'), 'label' => 'X'],
    ])->filter(fn ($s) => filled($s['url']))->values();

    // The services column is the mockup's one omission, and it is conditional rather than
    // deleted. The mockup shows logo / Quick Links / Contact Us / Follow Us — no services — and
    // with nothing configured that is exactly what renders. But this column draws
    // `footerCompanyItems`, a menu the reseller builds in Reseller → Menus, and silently
    // dropping links somebody set up is not a design decision. So it appears only when they
    // have actually built one, and the default matches the mockup.
    $showServices = $footerCompanyItems->isNotEmpty();

    $colCount = 3 + ($showServices ? 1 : 0) + ($socials->isNotEmpty() ? 1 : 0);
    $gridCols = match ($colCount) {
        5 => 'sm:grid-cols-2 lg:grid-cols-5',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

    // The heading serif, at label size. `t-heading` is what carries --t-heading-family, so the
    // column headings move with the template's type rather than naming Playfair here.
    $colHeading = 't-heading text-[16px] text-[var(--ap-ink)]';
    // 6px of padding either side of 13.5px type is a 32px row: fine under a mouse, and two
    // thirds of a fingertip. The footer is where a phone visitor goes for the number and the
    // opening hours, so on a phone the rows open up to 44px and close again at `sm`, where the
    // columns go side by side and the mockup's tighter rhythm is the right one.
    $colLink = 'block py-3 text-[13.5px] text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)] sm:py-1.5';

    // One hairline, left of the first link column — the only divider the mockup's footer has.
    // Not between every column: four rules across a footer turns it into a table.
    $firstCol = 'lg:border-s lg:border-[var(--ap-line)] lg:ps-10';

    $lines = fn ($value) => collect(preg_split('/\r\n|\r|\n/', (string) $value))->map(fn ($l) => trim($l))->filter()->values();
@endphp

{{--
    A light footer, and one navy bar under it.

    The 2026-08-31 template ended on a deep navy block: gold column headings, gold discs, a
    chevron before every link. The final mockup ends on the same near-white the page is written
    on — measured at #FDFDFC, which is why this is `--ap-mist` and not a tinted band — with
    navy serif headings and grey links, and puts the only dark surface in the whole design under
    it to close the page.
--}}
<footer class="border-t border-[var(--ap-line)] bg-[var(--ap-mist)]">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
        <div class="grid grid-cols-1 gap-10 {{ $gridCols }} lg:gap-12">
            {{-- Identity --}}
            <div>
                <a href="{{ $homeUrl }}" class="inline-block">
                    {{-- The light logo, not the dark one. The footer used to be navy and asked
                         for logoDarkUrl(); it is now the same ground as the header, so it takes
                         the same mark. A reseller who uploaded a white-on-transparent logo for
                         the old footer would otherwise get an invisible one here. --}}
                    <img src="{{ BrandingHelper::logoUrl() }}" alt="{{ $appName }}"
                        class="h-14 w-auto max-w-[210px] object-contain object-left" />
                </a>

                @if (filled($tagline))
                    <p class="t-body mt-5 max-w-[230px] text-[13px] leading-[1.9] text-[var(--ap-ink-soft)]">{{ $tagline }}</p>
                @endif
            </div>

            {{-- Quick links --}}
            <div class="{{ $firstCol }}">
                <h3 class="{{ $colHeading }}">{{ $footerQuickMenu?->title ?: 'Quick Links' }}</h3>
                <nav class="mt-5">
                    @forelse ($footerQuickItems as $item)
                        <a href="{{ $item->resolvedUrl() }}" class="{{ $colLink }}">{{ $item->label }}</a>
                    @empty
                        <a href="{{ $homeUrl }}" class="{{ $colLink }}">Home</a>
                        @foreach (['about' => 'About Us', 'pricing' => 'Pricing', 'memorial.directory' => 'Find a Memorial', 'contact' => 'Contact Us'] as $route => $label)
                            @php $url = \App\Support\StandardPages::urlForRouteName($route); @endphp
                            @if ($url)
                                <a href="{{ $url }}" class="{{ $colLink }}">{{ $label }}</a>
                            @endif
                        @endforeach
                    @endforelse
                </nav>
            </div>

            {{-- Services, only when the reseller has built the menu --}}
            @if ($showServices)
                <div>
                    <h3 class="{{ $colHeading }}">{{ $footerCompanyMenu?->title ?: 'Our Services' }}</h3>
                    <nav class="mt-5">
                        @foreach ($footerCompanyItems as $item)
                            <a href="{{ $item->resolvedUrl() }}"
                                @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                                class="{{ $colLink }}">{{ $item->label }}</a>
                        @endforeach
                    </nav>
                </div>
            @endif

            {{-- Contact --}}
            <div>
                <h3 class="{{ $colHeading }}">Contact Us</h3>
                <div class="mt-5 space-y-3.5 text-[13.5px] text-[var(--ap-ink-soft)]">
                    @if (filled($address))
                        <p class="flex items-start gap-3">
                            <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                            <span class="t-body">
                                @foreach ($lines($address) as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </span>
                        </p>
                    @endif

                    @foreach (collect([$phone, $phoneAlt])->filter() as $number)
                        <p class="flex items-center gap-3">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                            {{-- Padded on a phone for the same reason as the link columns: a
                                 20px line of text is the thing a visitor on a phone is most
                                 likely to be reaching for in this whole footer. --}}
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $number) }}" class="py-2.5 transition-colors duration-200 ease-out hover:text-[var(--ap-blue)] sm:py-0">{{ $number }}</a>
                        </p>
                    @endforeach

                    @if (filled($email))
                        <p class="flex items-center gap-3">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                            <a href="mailto:{{ $email }}" class="break-all py-2.5 transition-colors duration-200 ease-out hover:text-[var(--ap-blue)] sm:py-0">{{ $email }}</a>
                        </p>
                    @endif

                    {{-- Opening hours, when the reseller has set them. No key exists for this
                         yet, so it resolves to nothing and the row simply collapses — written as
                         a ThemeSetting read rather than hardcoded, so the day the key is added
                         this fills in with no change here. --}}
                    @if (filled($hours))
                        <p class="flex items-start gap-3">
                            <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-blue)]" />
                            <span class="t-body">
                                @foreach ($lines($hours) as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </span>
                        </p>
                    @endif
                </div>
            </div>

            {{-- Follow us. Its own column in the final mockup, rather than three discs tucked
                 under the logo where the old navy footer put them. --}}
            @if ($socials->isNotEmpty())
                <div>
                    <h3 class="{{ $colHeading }}">Follow Us</h3>
                    <div class="mt-5 flex items-center gap-3">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                                aria-label="{{ $social['label'] }}"
                                {{-- 32px is the disc the mockup draws. On a phone that is a
                                     target you miss, so it grows to 40px there and returns to
                                     the drawn size from `sm` up. --}}
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--ap-blue)] text-white transition-[filter] duration-200 ease-out hover:brightness-125 sm:h-8 sm:w-8">
                                @switch($social['key'])
                                    @case('facebook')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5H16.7V3.6c-.3 0-1.3-.13-2.47-.13-2.44 0-4.11 1.49-4.11 4.23V9.9H7.4V13h2.72v8z" /></svg>
                                        @break
                                    @case('twitter')
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.2 3h3.3l-7.2 8.2L21.8 21h-6.6l-4.4-5.7L5.7 21H2.4l7.7-8.8L2.5 3h6.8l4 5.3zm-1.2 16h1.8L8.1 4.8H6.2z" /></svg>
                                        @break
                                    @default
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" /><circle cx="12" cy="12" r="3.7" /><circle cx="16.9" cy="7.1" r="1.1" fill="currentColor" stroke="none" /></svg>
                                @endswitch
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- The closing bar. The one dark surface in the design, and the reason the light footer
         above it does not simply run off the bottom of the screen. --}}
    <div class="bg-[var(--ap-navy)]">
        <div class="mx-auto max-w-7xl px-4 py-4 text-center sm:px-6 lg:px-8">
            <p class="text-[12px] text-white/70">
                &copy; {{ date('Y') }} {{ $appName }}. All Rights Reserved.
            </p>

            @include('partials.powered-by', ['tone' => 'dark'])
        </div>
    </div>
</footer>
