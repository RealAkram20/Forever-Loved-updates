{{--
    The one line on a white-labeled site that is ours.

    A reseller's footer is otherwise entirely theirs — their logo, their words, their colours,
    their address. This is the credit, and it only appears on their sites: on the platform's own
    footer it would read "Powered by ourselves".

    Everything about it is deliberately quiet. Small, dimmed, under the copyright line, and set
    on the platform's mark rather than its name, because a mark reads as attribution where a
    second wordmark would read as a second brand competing with the one above it.

    Opens in a new tab. A visitor here is usually reading a memorial, and a footer credit is not
    worth losing their place on the funeral home's site for.

    @param string|null $tone  'dark' when the footer's ground is dark, so the right mark is used.
--}}
@php
    $tone = ($tone ?? 'light') === 'dark' ? 'dark' : 'light';
    // config('app.url'), not route('home'): on a reseller host AppServiceProvider has re-rooted
    // URL generation to *their* domain, so route('home') would link the visitor back to the page
    // they are already standing on.
    $platformUrl = rtrim((string) config('app.url'), '/') ?: '/';
    $platformName = \App\Helpers\BrandingHelper::platformName();
@endphp

@if (\App\Helpers\ThemeSetting::isResellerSite())
    {{-- Dimmed in exactly one place. An opacity on the wrapper *and* a translucent text colour
         multiply, and the first attempt at this came out at roughly a quarter alpha — legible
         on the design, invisible on the page. --}}
    <a href="{{ $platformUrl }}"
       target="_blank"
       rel="noopener noreferrer"
       class="group mt-3 inline-flex items-center justify-center gap-2.5">
        <span class="text-[10px] font-medium uppercase tracking-[0.18em] {{ $tone === 'dark' ? 'text-white/45' : 'text-gray-400' }}">Powered by</span>
        <img src="{{ \App\Helpers\BrandingHelper::platformLogoUrl($tone) }}"
             alt="{{ $platformName }}"
             loading="lazy"
             class="h-8 w-auto opacity-80 transition-opacity duration-200 ease-out group-hover:opacity-100 sm:h-9" />
    </a>
@endif
