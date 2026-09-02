{{--
    The band at the top of every page that is not the home page.

    Not a new idea — it is the same thing `section_banner` already draws for an inner page in
    this template, and it has to stay that way: a visitor moving from /services (a theme page,
    built out of widgets) to /contact (a platform page, built out of Blade) must not cross a
    seam. Pale sky ground, centred serif title, the amber rule, and the same Home / Page trail.

    It exists as a partial because the platform's own pages cannot render a page-builder widget
    — they have no document to render — so the one piece of furniture they share with the
    widget pages has to be available to them directly.

    @param string      $title
    @param string|null $eyebrow
    @param string|null $sub
    @param bool        $crumb    Draw the breadcrumb. Off for pages that are not really "under"
                                 the home page in any useful sense.
--}}
@php
    use App\Helpers\ThemeSetting;

    $eyebrow = $eyebrow ?? null;
    $sub = $sub ?? null;
    $crumb = $crumb ?? true;

    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');
@endphp

{{-- data-banner-height so the tokens and rules written against the widget's inner-page band
     reach this one too, rather than the two drifting apart the first time either is adjusted. --}}
<section data-banner-height="short" class="bg-[var(--ap-sky)] py-[var(--t-pad-md)]">
    <div class="mx-auto max-w-6xl px-4 text-center sm:px-6 lg:px-8">
        @if ($eyebrow)
            <p class="t-eyebrow text-[var(--ap-blue)]">{{ $eyebrow }}</p>
        @endif

        <h1 class="t-heading t-h2 {{ $eyebrow ? 'mt-4' : '' }} text-[var(--ap-ink)]">{{ $title }}</h1>

        <span class="ap-rule ap-rule-center mt-6" aria-hidden="true"></span>

        @if ($sub)
            <p class="t-body mx-auto mt-6 max-w-xl text-[15px] text-[var(--ap-ink-soft)]">{{ $sub }}</p>
        @endif

        @if ($crumb)
            <nav aria-label="Breadcrumb" class="mt-6">
                <ol class="flex flex-wrap items-center justify-center gap-2 text-[13px]">
                    <li>
                        <a href="{{ $homeUrl }}"
                            class="text-[var(--ap-ink-soft)] transition-colors duration-200 ease-out hover:text-[var(--ap-blue)]">Home</a>
                    </li>
                    <li aria-hidden="true" class="text-[var(--ap-ink-soft)]/50">/</li>
                    <li aria-current="page" class="font-semibold text-[var(--ap-blue)]">{{ $title }}</li>
                </ol>
            </nav>
        @endif
    </div>
</section>
