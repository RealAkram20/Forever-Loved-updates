{{--
    Words over a background, in A-Plus.

    Two departures from the platform's view, both structural, which is the only justification
    this repo accepts for forking one:

    1. **A photograph beside the copy, not underneath it.** The platform's banner puts the
       image behind the words and darkens it until they are legible. The final mockup does the
       opposite: a pale blue band with the copy on the left and the picture bleeding out of the
       right-hand edge, in full colour, with no scrim at all. That is not a colour change — the
       image stops being a background and becomes half the composition — so it cannot be done
       from tokens.
    2. The breadcrumb the reference puts under every inner-page hero.

    Everything else — the type, the buttons, the section rhythm, the amber rule — still comes
    from `--t-*` and the hooks in ap-theme-style, not from this file.

    The trail is derived, not authored. `section_banner` has no breadcrumb prop and adding one
    would be a platform change; more to the point, a trail somebody types by hand is a trail that
    goes stale the moment a page is renamed. So it is read from the page actually being served.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;
    use App\Helpers\ThemeSetting;

    $buttons = R::buttons($props);
    $image = R::image($props['image'] ?? '');
    $centred = ($props['alignment'] ?? 'left') === 'center';
    $wantsForm = ($props['form'] ?? 'none') === 'enquiry';
    $height = $props['height'] ?? 'tall';
    $background = $props['background'] ?? 'image';

    // Dark is now something a reseller has to ask for by name.
    //
    // It used to be inferred — any banner with an image was dark, because every banner with an
    // image had a scrim over it. In this design a photograph means the pale band, so the two
    // have to be separated or the hero comes out navy the moment somebody picks a picture.
    $dark = in_array($background, ['dark', 'accent'], true);

    // The bleed: a photograph out of the right-hand edge of a pale band.
    //
    // Only when the copy is on the left. A centred heading over a picture bleeding from the
    // right has the words drifting into the subject with nothing to stop them, and both
    // supplied photographs put theirs slightly left of centre. Centred banners get the band on
    // its own, which is exactly what the mockup's inner pages are.
    $bleed = $image !== '' && ! $dark && ! $centred;

    // The pale band carries the hero, the closing feature section and every inner-page title
    // band. `page` and `muted` still mean what they say, so a reseller who wants a plain white
    // section still gets one.
    $onSky = ! $dark && in_array($background, ['image', 'sky'], true);

    $heightCls = match ($height) {
        'band' => 'py-[var(--t-pad-md)]',
        'short' => 'py-[var(--t-pad-md)]',
        'screen' => 'min-h-[78vh] flex items-center py-[var(--t-pad-lg)]',
        default => 'py-[var(--t-pad-lg)]',
    };

    $headingSize = match ($height) {
        'band', 'short' => 't-h2',
        default => 't-h1',
    };

    $bg = $onSky ? 'bg-[var(--ap-sky)]' : match ($background) {
        'muted' => 'bg-[var(--t-surface-muted)]',
        'dark' => 'bg-[var(--t-surface-dark)]',
        'accent' => 'bg-[var(--t-accent)]',
        default => 'bg-[var(--t-surface-page)]',
    };

    // One span per line, and `.t-banner-line` decides whether those sit on separate rows.
    //
    // An explicit newline in the heading wins: the mockup opens on "In Need" above "We Care",
    // and no width cap produces that break — at any column narrow enough to wrap it, the words
    // fall as "In Need We" / "Care". So the break is data, typed into the heading, rather than
    // a layout accident. A heading with no newline in it still splits after its first sentence,
    // which is what the inner pages rely on.
    $heading = trim((string) ($props['heading'] ?? ''));

    $headingLines = match (true) {
        $heading === '' => [],
        str_contains($heading, "\n") => array_values(array_filter(
            array_map('trim', preg_split('/\R/', $heading) ?: []),
            fn ($l) => $l !== ''
        )),
        default => preg_split('/(?<=\.)\s+/', $heading, 2) ?: [$heading],
    };

    // The breadcrumb.
    //
    // Only on a banner that opens an inner page: a trail on the front page would read "Home /
    // Home", and a trail on the feedback band halfway down a page is pointing at the page it is
    // already on. `band` is excluded for that reason, and the home routes for the first.
    //
    // Four route names mean "the front page" depending on how the site was reached — the
    // platform host, a subdomain, a custom domain, or the /r/{slug} development fallback.
    // Comparing against 'home' alone would put a breadcrumb on every reseller's front page.
    $currentRoute = request()->route()?->getName();
    $onHome = in_array($currentRoute, [
        'home', 'reseller.public.index', 'reseller.public.index-custom-domain', 'reseller.public.index-path',
    ], true);

    $tenantSite = ThemeSetting::isResellerSite() ? ThemeSetting::tenant() : null;
    $homeUrl = $tenantSite ? $tenantSite->publicBaseUrl() : route('home');

    $crumb = null;

    if (! $onHome && $height !== 'band') {
        // The page's own title, so renaming a page renames its trail. The slug is the last
        // segment of the path the visitor actually requested, which is what the page was found
        // by. Falls back to the segment itself, tidied — a trail that says something slightly
        // generic is better than a hole where the trail should be.
        $segment = trim((string) request()->segment(count(request()->segments())), '/');

        if ($segment !== '') {
            $crumb = \App\Models\Page::query()
                ->where('slug', $segment)
                ->when($tenantSite, fn ($q) => $q->where('reseller_id', $tenantSite->id))
                ->value('title');

            $crumb = $crumb ?: \Illuminate\Support\Str::headline($segment);
        }
    }
@endphp

{{-- data-banner-height so a template can style the hero differently from the four other things
     this same view renders. Without it the only handle is the padding class, and a rule meant
     for the front page's hero lands on every inner-page title band too. --}}
<section data-banner-height="{{ $height }}" class="relative isolate overflow-hidden {{ $bg }} {{ $heightCls }}">
    @if ($bleed)
        {{-- Anchored right and clipped, rather than stretched across the section. `object-right`
             keeps the subject where the photographer put it at every width; `w-[62%]` leaves the
             copy the left two fifths, which is what the mockup gives it.

             Not `loading="lazy"`: this is the first thing on the page, and a lazy hero is a
             blank pale band for as long as the request takes. --}}
        <img src="{{ $image }}" alt="" aria-hidden="true"
            class="ap-bleed-image absolute inset-y-0 end-0 h-full w-[68%] object-cover object-right" />

        {{-- The fade that joins the band to the photograph. Full width rather than sitting over
             the picture alone, so the gradient's opaque end *is* the band and there is no seam
             where the two meet. --}}
        <div class="ap-bleed-fade absolute inset-0" aria-hidden="true"></div>
    @elseif ($dark && $image !== '')
        {{-- A reseller who explicitly asks for a dark banner still gets the old treatment: the
             picture behind the words with a scrim over it. Kept because "dark" has to keep
             meaning something, and because the memorial furniture uses it. --}}
        <img src="{{ $image }}" alt="" aria-hidden="true" class="t-banner-image absolute inset-0 h-full w-full object-cover" />
        <div class="t-banner-overlay absolute inset-0 {{ R::overlay($props) ?: 'bg-black/50' }}" aria-hidden="true"></div>
    @endif

    <div class="relative mx-auto w-full {{ R::width($props) }} px-4 sm:px-6 lg:px-8">
        <div @class([
            't-banner-copy',
            'text-center mx-auto max-w-2xl' => $centred,
            'max-w-xl' => ! $centred,
        ])>
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow {{ $dark ? 'text-[var(--ap-gold)]' : 'text-[var(--t-accent)]' }}">{{ $props['eyebrow'] }}</p>
            @endif

            @if ($headingLines)
                <h2 class="t-heading {{ $headingSize }} {{ filled($props['eyebrow'] ?? '') ? 'mt-4' : '' }} {{ $dark ? 'text-white' : 'text-[var(--ap-ink)]' }}">
                    @foreach ($headingLines as $line)
                        <span class="t-banner-line">{{ trim($line) }}</span>
                    @endforeach
                </h2>
            @endif

            {{-- The amber rule. Markup rather than the pseudo-element the old template hung off
                 `[data-banner-height='tall'] .t-heading`, because it is now on every banner and
                 has to follow the alignment — a centred inner-page title band wants it centred,
                 and a pseudo-element cannot read the alignment prop. --}}
            @if ($headingLines)
                <span class="ap-rule mt-6 {{ $centred ? 'ap-rule-center' : '' }}" aria-hidden="true"></span>
            @endif

            @if (filled($props['body'] ?? ''))
                <p class="t-body mt-6 {{ $height === 'tall' ? 'max-w-[19rem] text-[16px]' : 'max-w-md text-[15px]' }} {{ $centred ? 'mx-auto' : '' }} {{ $dark ? 'text-white/75' : ($height === 'tall' ? 'text-[var(--ap-ink-strong)]' : 'text-[var(--ap-ink-soft)]') }}">{{ $props['body'] }}</p>
            @endif

            @if ($crumb)
                {{-- Sits under the heading's rule, which is where the reference puts it. A nav
                     landmark rather than a row of links, so it is skippable and announces
                     itself; aria-current marks the page you are on, which is the one crumb that
                     is deliberately not a link. --}}
                <nav aria-label="Breadcrumb" class="mt-6">
                    <ol class="flex flex-wrap items-center gap-2 text-[13px] {{ $centred ? 'justify-center' : '' }}">
                        <li>
                            <a href="{{ $homeUrl }}"
                                class="{{ $dark ? 'text-white/75 hover:text-[var(--ap-gold)]' : 'text-[var(--ap-ink-soft)] hover:text-[var(--ap-blue)]' }} transition-colors duration-200 ease-out">Home</a>
                        </li>
                        <li aria-hidden="true" class="{{ $dark ? 'text-white/40' : 'text-[var(--ap-ink-soft)]/50' }}">/</li>
                        <li aria-current="page" class="font-semibold {{ $dark ? 'text-white' : 'text-[var(--ap-blue)]' }}">{{ $crumb }}</li>
                    </ol>
                </nav>
            @endif

            @if ($wantsForm)
                @include('page-builder.widgets.partials.banner-form', ['props' => $props, 'onImage' => $dark])
            @endif

            @if ($buttons)
                <div class="mt-8 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                    @foreach ($buttons as $button)
                        <a href="{{ $button['url'] }}" @class([
                            't-btn',
                            'bg-[var(--ap-blue)] text-white hover:brightness-110' => $button['primary'] && ! $dark,
                            'bg-white text-[var(--ap-blue)] hover:brightness-95' => $button['primary'] && $dark,
                            'border border-[var(--ap-blue)]/30 text-[var(--ap-blue)] hover:border-[var(--ap-blue)]' => ! $button['primary'] && ! $dark,
                            'border border-white/40 text-white hover:border-white' => ! $button['primary'] && $dark,
                        ])>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
