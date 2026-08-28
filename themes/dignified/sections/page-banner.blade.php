{{--
    The band at the top of every page that is not the home page.

    The reference design only draws the home page, so this is an extension of its language
    rather than a copy of something: the same dark floral plate the services grid sits on, the
    same greyscale treatment as the hero, the same small-caps serif, and the gold-into-crimson
    rule that brackets the services grid — used here to underline the title.

    An inner page needs a header at all because the site's own header is white and the page
    body is near-white; without this band the title floats with nothing holding it, and the
    crimson nav bar becomes the darkest thing on the page by accident.

    @param string      $title
    @param string|null $eyebrow
    @param string|null $sub
--}}
@php
    $eyebrow = $eyebrow ?? null;
    $sub = $sub ?? null;
@endphp

<section class="relative isolate overflow-hidden bg-[var(--dg-ink)]">
    <img src="{{ asset('images/themes/dignified/services-bg.webp') }}" alt="" aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover grayscale" />
    <div class="absolute inset-0 bg-black/60" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-6xl px-4 py-12 text-center sm:px-6 sm:py-14 lg:px-8">
        @if ($eyebrow)
            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-white/70">{{ $eyebrow }}</p>
        @endif

        <h1 class="dg-caps mt-3 text-[30px] leading-[1.15] text-white sm:text-[38px]">{{ $title }}</h1>

        <div class="dg-rule mx-auto mt-5 h-px w-24" aria-hidden="true"></div>

        @if ($sub)
            <p class="dg-body mx-auto mt-5 max-w-xl text-[15px] text-white/70">{{ $sub }}</p>
        @endif
    </div>
</section>
