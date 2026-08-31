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
        ['key' => 'twitter', 'url' => ThemeSetting::get('branding.social_twitter'), 'label' => 'X'],
        ['key' => 'instagram', 'url' => ThemeSetting::get('branding.social_instagram'), 'label' => 'Instagram'],
    ])->filter(fn ($s) => filled($s['url']))->values();

    // Every nav on this site is the reseller's, built in Reseller → Menus. The lists below are
    // only what shows before they have put anything there, so a brand-new site is not two
    // columns of nothing.
    $serviceFallback = ['Funeral Arrangements', 'Repatriation Services', 'Coffins & Caskets', 'Hearse Transport', 'Memorial Services', 'Pre-Planning'];
    $servicesUrl = \App\Support\StandardPages::urlForRouteName('pricing') ?: \App\Support\StandardPages::urlForRouteName('about');

    $colHeading = 'ap-foot-heading text-[13px] font-bold uppercase tracking-[0.16em] text-[var(--ap-gold)]';
    $colLink = 'group flex items-center gap-2 py-1 text-[13px] text-white/65 transition-colors duration-200 ease-out hover:text-white';
    $lines = fn ($value) => collect(preg_split('/\r\n|\r|\n/', (string) $value))->map(fn ($l) => trim($l))->filter()->values();
@endphp

<footer class="bg-[var(--ap-navy)]">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-14">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-12">
            {{-- Identity --}}
            <div>
                <a href="{{ $homeUrl }}" class="inline-block">
                    <img src="{{ BrandingHelper::logoDarkUrl() }}" alt="{{ $appName }}"
                        class="h-12 w-auto max-w-[200px] object-contain object-left" />
                </a>

                @if (filled($tagline))
                    <p class="t-body mt-5 max-w-[260px] text-[13px] text-white/60">{{ $tagline }}</p>
                @endif

                @if ($socials->isNotEmpty())
                    {{-- Gold discs with the navy showing through the mark. The only filled gold
                         shapes in the footer, which is what makes them read as buttons among
                         four columns of text links. --}}
                    <div class="mt-6 flex items-center gap-2.5">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener"
                                aria-label="{{ $social['label'] }}"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--ap-gold)] text-[var(--ap-navy)] transition-transform duration-200 ease-out hover:scale-105">
                                @switch($social['key'])
                                    @case('facebook')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5H16.7V3.6c-.3 0-1.3-.13-2.47-.13-2.44 0-4.11 1.49-4.11 4.23V9.9H7.4V13h2.72v8z" /></svg>
                                        @break
                                    @case('twitter')
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.2 3h3.3l-7.2 8.2L21.8 21h-6.6l-4.4-5.7L5.7 21H2.4l7.7-8.8L2.5 3h6.8l4 5.3zm-1.2 16h1.8L8.1 4.8H6.2z" /></svg>
                                        @break
                                    @default
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5" /><circle cx="12" cy="12" r="3.7" /><circle cx="16.9" cy="7.1" r="1.1" fill="currentColor" stroke="none" /></svg>
                                @endswitch
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick links --}}
            <div>
                <h3 class="{{ $colHeading }}">{{ $footerQuickMenu?->title ?: 'Quick Links' }}</h3>
                <nav class="mt-5">
                    @forelse ($footerQuickItems as $item)
                        <a href="{{ $item->resolvedUrl() }}" class="{{ $colLink }}">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[var(--ap-gold)]" />
                            <span>{{ $item->label }}</span>
                        </a>
                    @empty
                        <a href="{{ $homeUrl }}" class="{{ $colLink }}">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[var(--ap-gold)]" />
                            <span>Home</span>
                        </a>
                        @foreach (['about' => 'About Us', 'pricing' => 'Pricing', 'memorial.directory' => 'Find a Memorial', 'contact' => 'Contact Us'] as $route => $label)
                            @php $url = \App\Support\StandardPages::urlForRouteName($route); @endphp
                            @if ($url)
                                <a href="{{ $url }}" class="{{ $colLink }}">
                                    <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[var(--ap-gold)]" />
                                    <span>{{ $label }}</span>
                                </a>
                            @endif
                        @endforeach
                    @endforelse
                </nav>
            </div>

            {{-- Services --}}
            <div>
                <h3 class="{{ $colHeading }}">{{ $footerCompanyMenu?->title ?: 'Our Services' }}</h3>
                <nav class="mt-5">
                    @forelse ($footerCompanyItems as $item)
                        <a href="{{ $item->resolvedUrl() }}"
                            @if ($item->open_in_new_tab) target="_blank" rel="noopener noreferrer" @endif
                            class="{{ $colLink }}">
                            <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[var(--ap-gold)]" />
                            <span>{{ $item->label }}</span>
                        </a>
                    @empty
                        @foreach ($serviceFallback as $service)
                            <a href="{{ $servicesUrl ?: $homeUrl }}" class="{{ $colLink }}">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-[var(--ap-gold)]" />
                                <span>{{ $service }}</span>
                            </a>
                        @endforeach
                    @endforelse
                </nav>
            </div>

            {{-- Contact --}}
            <div>
                <h3 class="{{ $colHeading }}">Contact Us</h3>
                <div class="mt-5 space-y-3 text-[13px] text-white/65">
                    @if (filled($address))
                        <p class="flex items-start gap-2.5">
                            <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                            <span class="t-body">
                                @foreach ($lines($address) as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </span>
                        </p>
                    @endif

                    @foreach (collect([$phone, $phoneAlt])->filter() as $number)
                        <p class="flex items-center gap-2.5">
                            <x-icon name="phone" class="h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $number) }}" class="transition-colors duration-200 ease-out hover:text-white">{{ $number }}</a>
                        </p>
                    @endforeach

                    @if (filled($email))
                        <p class="flex items-center gap-2.5">
                            <x-icon name="mail" class="h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                            <a href="mailto:{{ $email }}" class="break-all transition-colors duration-200 ease-out hover:text-white">{{ $email }}</a>
                        </p>
                    @endif

                    {{-- Opening hours, when the reseller has set them. No key exists for this
                         yet, so it resolves to nothing and the row simply collapses — written as
                         a ThemeSetting read rather than hardcoded, so the day the key is added
                         this fills in with no change here. --}}
                    @if (filled($hours))
                        <p class="flex items-start gap-2.5">
                            <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--ap-gold)]" />
                            <span class="t-body">
                                @foreach ($lines($hours) as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </span>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- The closing bar. Slightly deeper than the footer above it so the copyright reads as a
         base rather than as a fifth column, with the platform credit under it. --}}
    <div class="border-t border-white/10 bg-black/20">
        <div class="mx-auto max-w-7xl px-4 py-5 text-center sm:px-6 lg:px-8">
            <p class="text-[12px] text-white/50">
                &copy; {{ date('Y') }} {{ $appName }}. All Rights Reserved.
            </p>

            @include('partials.powered-by', ['tone' => 'dark'])
        </div>
    </div>
</footer>
