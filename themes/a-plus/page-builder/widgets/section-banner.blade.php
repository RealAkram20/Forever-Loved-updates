{{--
    Words over a background, in A-Plus.

    Structurally the platform's view plus one addition: the breadcrumb the reference puts under
    every inner-page hero. Everything else — the type, the pill buttons, the scrim, the gold rule
    under the heading — still comes from `--t-*` tokens and hooks in ap-theme-style, not from
    this file. The fork exists for the breadcrumb and nothing else, and if a breadcrumb ever
    becomes a platform concern this file should be deleted rather than extended.

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
    $onImage = $image !== '';
    $wantsForm = ($props['form'] ?? 'none') === 'enquiry';
    $height = $props['height'] ?? 'tall';

    $heightCls = match ($height) {
        'band' => 'py-[var(--t-pad-sm)]',
        'short' => 'py-[var(--t-pad-md)]',
        'screen' => 'min-h-[78vh] flex items-center py-[var(--t-pad-lg)]',
        default => 'py-[var(--t-pad-lg)]',
    };

    $headingSize = match ($height) {
        'band', 'short' => 't-h2',
        default => 't-h1',
    };

    $dark = $onImage || in_array($props['background'] ?? 'image', ['dark', 'image'], true);

    $bg = $onImage ? 'bg-[var(--t-surface-dark)]' : match ($props['background'] ?? 'image') {
        'muted' => 'bg-[var(--t-surface-muted)]',
        'dark' => 'bg-[var(--t-surface-dark)]',
        'accent' => 'bg-[var(--t-accent)]',
        default => 'bg-[var(--t-surface-page)]',
    };

    // One span per sentence. CSS decides whether they sit on separate lines, so a template that
    // wants a two-line hero gets one without anybody hand-writing a line break.
    $heading = trim((string) ($props['heading'] ?? ''));
    $headingLines = $heading === '' ? [] : (preg_split('/(?<=\.)\s+/', $heading, 2) ?: [$heading]);

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
    @if ($onImage)
        <img src="{{ $image }}" alt="" aria-hidden="true" class="t-banner-image absolute inset-0 h-full w-full object-cover" />
        {{-- t-banner-overlay is a hook, not a style: the utility class still supplies the
             default. A template that wants a different scrim replaces it without a fork. --}}
        <div class="t-banner-overlay absolute inset-0 {{ R::overlay($props) ?: 'bg-black/50' }}" aria-hidden="true"></div>
    @endif

    <div class="relative mx-auto w-full {{ R::width($props) }} px-4 sm:px-6 lg:px-8">
        <div @class([
            't-banner-copy',
            'text-center mx-auto max-w-2xl' => $centred,
            'max-w-2xl' => ! $centred,
            't-banner-ruled ps-6 sm:ps-8' => ! $centred && $height !== 'band',
        ])>
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow {{ $dark ? 'text-[var(--ap-gold)]' : 'text-[var(--t-accent)]' }}">{{ $props['eyebrow'] }}</p>
            @endif

            @if ($headingLines)
                <h2 class="t-heading {{ $headingSize }} mt-4 {{ $dark ? 'text-white' : 'text-gray-900 dark:text-white' }}">
                    @foreach ($headingLines as $line)
                        <span class="t-banner-line">{{ trim($line) }}</span>
                    @endforeach
                </h2>
            @endif

            @if (filled($props['body'] ?? ''))
                <p class="t-body mt-4 max-w-xl {{ $centred ? 'mx-auto' : '' }} {{ $dark ? 'text-white/75' : 'text-gray-600 dark:text-gray-300' }}">{{ $props['body'] }}</p>
            @endif

            @if ($crumb)
                {{-- Sits under the heading's gold rule, which is where the reference puts it.
                     A nav landmark rather than a row of links, so it is skippable and announces
                     itself; aria-current marks the page you are on, which is the one crumb that
                     is deliberately not a link. --}}
                <nav aria-label="Breadcrumb" class="mt-6">
                    <ol class="flex flex-wrap items-center gap-2 text-[13px] {{ $centred ? 'justify-center' : '' }}">
                        <li>
                            <a href="{{ $homeUrl }}"
                                class="{{ $dark ? 'text-white/75 hover:text-[var(--ap-gold)]' : 'text-[var(--ap-ink-soft)] hover:text-[var(--ap-blue)]' }} transition-colors duration-200 ease-out">Home</a>
                        </li>
                        <li aria-hidden="true" class="{{ $dark ? 'text-white/40' : 'text-[var(--ap-ink-soft)]/50' }}">/</li>
                        <li aria-current="page" class="font-semibold {{ $dark ? 'text-[var(--ap-gold)]' : 'text-[var(--ap-blue)]' }}">{{ $crumb }}</li>
                    </ol>
                </nav>
            @endif

            @if ($wantsForm)
                @include('page-builder.widgets.partials.banner-form', ['props' => $props, 'onImage' => $dark])
            @endif

            @if ($buttons)
                <div class="mt-7 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                    @foreach ($buttons as $button)
                        <a href="{{ $button['url'] }}" @class([
                            't-btn',
                            'bg-[var(--ap-gold)] text-[var(--ap-navy)] hover:brightness-95' => $button['primary'],
                            'border border-current/40 hover:border-current' => ! $button['primary'],
                            'text-white' => ! $button['primary'] && $dark,
                            'text-gray-900 dark:text-white' => ! $button['primary'] && ! $dark,
                        ])>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
