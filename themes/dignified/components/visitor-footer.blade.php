@php
    use App\Helpers\BrandingHelper;
    use App\Helpers\SiteShareMetaHelper;
    use App\Helpers\ThemeSetting;

    $appName = SiteShareMetaHelper::appDisplayName();
    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');

    $footerQuickItems = $footerQuickItems ?? collect();
    $footerCompanyItems = $footerCompanyItems ?? collect();

    // Their own words or none. ThemeSetting::get() would fall through to the platform's
    // line, which is our marketing sitting under their logo.
    $tagline = ThemeSetting::tenantOwn('branding.tagline');
    $address = ThemeSetting::get('branding.contact_address');
    $phone = ThemeSetting::get('branding.contact_phone');
    $phoneAlt = ThemeSetting::get('branding.contact_phone_alt');
    $email = BrandingHelper::contactEmail();

    $socials = collect([
        ['key' => 'facebook', 'url' => ThemeSetting::get('branding.social_facebook'), 'label' => 'Facebook'],
        ['key' => 'twitter', 'url' => ThemeSetting::get('branding.social_twitter'), 'label' => 'X'],
        ['key' => 'instagram', 'url' => ThemeSetting::get('branding.social_instagram'), 'label' => 'Instagram'],
    ])->filter(fn ($s) => filled($s['url']))->values();

    // Every nav on this site is the reseller's, built in Reseller -> Menus. This column is the
    // footer's second menu location; the list below is only what shows before they have put
    // anything there, so a brand-new site is not a column of nothing.
    $serviceFallback = ['Funeral Arrangements', 'Documentation & Advisory', 'Repatriation Services', 'Memorial Services', 'Burial & Cremation', 'Condolence Support'];
    $servicesUrl = \App\Support\StandardPages::urlForRouteName('pricing') ?: \App\Support\StandardPages::urlForRouteName('about');

    $colHeading = 'dg-caps text-[13px] font-bold tracking-[0.12em] text-white';
    $colLink = 'block py-0.5 text-[13px] text-white/60 transition-colors duration-200 ease-out hover:text-[var(--dg-gold)]';
    $lines = fn ($value) => collect(preg_split('/\r\n|\r|\n/', (string) $value))->map(fn ($l) => trim($l))->filter()->values();
@endphp

<footer class="bg-[#1f1f21]">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-7 lg:px-8">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">
            {{-- Identity --}}
            <div class="lg:pr-8">
                <a href="{{ $homeUrl }}" class="inline-block">
                    <img src="{{ BrandingHelper::logoDarkUrl() }}" alt="{{ $appName }}"
                        class="h-11 w-auto max-w-[185px] object-contain object-left" />
                </a>

                @if (filled($tagline))
                    <p class="dg-body mt-4 max-w-[220px] text-[13px] text-white/55">{{ $tagline }}</p>
                @endif

                @if ($socials->isNotEmpty())
                    <div class="mt-5 flex items-center gap-3">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                                aria-label="{{ $social['label'] }}"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-white/30 text-white/70 transition-colors duration-200 ease-out hover:border-[var(--dg-gold)] hover:text-[var(--dg-gold)]">
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
                    </div>
                @endif
            </div>

            {{-- Quick links --}}
            <div class="lg:border-l lg:border-white/10 lg:pl-8">
                <h3 class="{{ $colHeading }}">{{ $footerQuickMenu?->title ?: 'Quick Links' }}</h3>
                <nav class="mt-4">
                    @forelse ($footerQuickItems as $item)
                        <a href="{{ $item->resolvedUrl() }}" class="{{ $colLink }}">{{ $item->label }}</a>
                    @empty
                        <a href="{{ $homeUrl }}" class="{{ $colLink }}">Home</a>
                        @foreach (['about' => 'About Us', 'pricing' => 'Pricing', 'memorial.directory' => 'Find a Memorial', 'contact' => 'Contact Us'] as $route => $label)
                            @php $url = \App\Support\StandardPages::urlForRouteName($route); @endphp
                            @if ($url)<a href="{{ $url }}" class="{{ $colLink }}">{{ $label }}</a>@endif
                        @endforeach
                    @endforelse
                </nav>
            </div>

            {{-- Services --}}
            <div class="lg:border-l lg:border-white/10 lg:pl-8">
                <h3 class="{{ $colHeading }}">{{ $footerCompanyMenu?->title ?: 'Our Services' }}</h3>
                <nav class="mt-4">
                    @forelse ($footerCompanyItems as $item)
                        <a href="{{ $item->resolvedUrl() }}"
                            @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                            class="{{ $colLink }}">{{ $item->label }}</a>
                    @empty
                        @foreach ($serviceFallback as $service)
                            <a href="{{ $servicesUrl ?: $homeUrl }}" class="{{ $colLink }}">{{ $service }}</a>
                        @endforeach
                    @endforelse
                </nav>
            </div>

            {{-- Contact --}}
            <div class="lg:border-l lg:border-white/10 lg:pl-8">
                <h3 class="{{ $colHeading }}">Contact Info</h3>
                <div class="mt-4 space-y-1 text-[13px] text-white/60">
                    @foreach (collect([$phone, $phoneAlt])->filter() as $number)
                        <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $number) }}" class="transition-colors duration-200 ease-out hover:text-[var(--dg-gold)]">{{ $number }}</a></p>
                    @endforeach

                    @if (filled($email))
                        <p><a href="mailto:{{ $email }}" class="transition-colors duration-200 ease-out hover:text-[var(--dg-gold)]">{{ $email }}</a></p>
                    @endif

                    @if (filled($address))
                        <p class="dg-body pt-3">
                            @foreach ($lines($address) as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer legal links stay reachable even on a tenant site with no menus built. --}}
        <div class="mt-5 border-t border-white/10 pt-3 text-center">
            <p class="text-[12px] text-white/45">
                &copy; {{ date('Y') }} {{ $appName }}. All Rights Reserved.
            </p>
        </div>
    </div>

    {{-- The closing bar: a short gold run, then crimson to the edge. The same two colours the
         page opened with, in the same order. --}}
    <div class="flex h-1.5" aria-hidden="true">
        <span class="w-[26%] bg-[var(--dg-gold)]"></span>
        <span class="flex-1 bg-[var(--dg-red)]"></span>
    </div>
</footer>
