{{--
    A row of things, in A-Plus.

    The mockup has no cards. A service is a circular pale-blue badge with a navy line mark in
    it, a bold navy title, and two lines of grey text — and the only thing separating it from
    the service beside it is a 1px hairline. No border around it, no fill, no shadow, no
    "Learn More" row, and no filled lead tile.

    That is why this file is still a fork. Everything about the *look* of these columns comes
    from `--t-*` and the hooks in ap-theme-style; what cannot come from tokens is that the
    platform's card is a bordered box and this is not a box at all. If a change can be made in
    ap-theme-style, it belongs there and not here.

    ------------------------------------------------------------------
    Two things the 2026-08-31 version did that this one deliberately does not:

    **The filled first card is gone.** It existed to give a grid of six identical tiles a
    starting point for the eye. With the tiles gone there is nothing to start — the row reads
    as one object now — and a single navy-filled column in a design this pale would be the
    loudest thing on the page.

    **The statistics band is gone.** `background: dark` + four columns used to render an inset
    navy plate over a drained photograph. The mockup has no such section, and the band was
    where "30+ Years Experience" and "1,000+ Families Served" — one client's claims — were
    shipping to every reseller who applied this template. Four dark columns now render as
    ordinary dark columns.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;

    $items = collect($props['items'] ?? [])
        ->filter(fn ($i) => is_array($i) && (filled($i['title'] ?? '') || filled($i['text'] ?? '')))
        ->values();

    $buttons = R::buttons($props);
    $centred = ($props['alignment'] ?? 'center') === 'center';

    // A disc behind the icon, or the bare mark.
    //
    // The mockup draws the services with a pale blue disc behind each icon and the reasons
    // without one. The reason is shape, not meaning: a tick already comes inside a circle, and
    // putting a disc behind it draws two concentric rings.
    //
    // So it is keyed on the icon itself. A first attempt keyed it on whether the row linked
    // anywhere — services do, reasons do not — which read well and was wrong within one page:
    // the About page's services grid has no urls on its items, and six services came out as
    // bare marks. An icon that draws its own ring is a fact about the icon; whether somebody
    // filled in a url is not.
    $ringIcons = ['circle-check-big', 'check', 'circle-check'];
    $onDark = in_array($props['background'] ?? 'muted', ['dark', 'image'], true);

    $cols = (int) ($props['columns'] ?? 3);
    $colClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

    $bg = R::background($props, [
        'page' => 'bg-[var(--ap-paper)]',
        'muted' => 'bg-[var(--ap-mist)]',
        'dark' => 'bg-[var(--ap-navy)]',
        'accent' => 'bg-[var(--ap-blue)]',
        'image' => 'bg-[var(--ap-navy)]',
    ]);

    $pad = R::padding($props, [
        'none' => '',
        'sm' => 'py-[var(--t-pad-sm)]',
        'md' => 'py-[var(--t-pad-md)]',
        'lg' => 'py-[var(--t-pad-lg)]',
    ]);

    $width = R::width($props, [
        'narrow' => 'max-w-3xl', 'normal' => 'max-w-6xl', 'wide' => 'max-w-7xl', 'full' => 'max-w-none',
    ]);
@endphp

<section class="{{ $bg }} {{ $pad }}">
    <div class="mx-auto {{ $width }} px-4 sm:px-6 lg:px-8">
        @if (filled($props['eyebrow'] ?? '') || filled($props['heading'] ?? '') || filled($props['body'] ?? ''))
            {{-- Centred heading, amber rule, one line of standfirst under it. The old template
                 set the heading left and the standfirst right, as two halves of one sentence
                 broken across the page; the mockup centres both and lets the whitespace do it
                 instead. --}}
            <div class="{{ $centred ? 'text-center' : '' }}">
                @if (filled($props['eyebrow'] ?? ''))
                    <p class="t-eyebrow {{ $onDark ? 'text-white/70' : 'text-[var(--ap-blue)]' }}">{{ $props['eyebrow'] }}</p>
                @endif

                @if (filled($props['heading'] ?? ''))
                    <h2 class="t-heading t-h2 {{ filled($props['eyebrow'] ?? '') ? 'mt-3' : '' }} {{ $onDark ? 'text-white' : 'text-[var(--ap-ink)]' }}">{{ $props['heading'] }}</h2>

                    <span class="ap-rule mt-4 {{ $centred ? 'ap-rule-center' : '' }}" aria-hidden="true"></span>
                @endif

                @if (filled($props['body'] ?? ''))
                    <p @class([
                        't-body mt-5 text-[15px]',
                        'mx-auto max-w-2xl' => $centred,
                        'max-w-2xl' => ! $centred,
                        'text-white/70' => $onDark,
                        'text-[var(--ap-ink-soft)]' => ! $onDark,
                    ])>{{ $props['body'] }}</p>
                @endif
            </div>
        @endif

        @if ($items->isNotEmpty())
            {{-- `gap-y` only. A column gap would put space either side of the hairline and turn
                 the divider into a gutter with a line floating in it; the padding inside each
                 column does that job instead, so the line sits hard against the content it
                 separates. --}}
            <div class="mt-12 grid grid-cols-1 gap-y-12 {{ $colClass }}">
                @foreach ($items as $i => $item)
                    @php
                        $url = filled($item['url'] ?? '') ? R::url($item['url']) : null;

                        // A column without a link is a <div>, not an <a> going nowhere: a
                        // pointer cursor that leads nowhere is a small lie the whole page pays
                        // for.
                        $tag = $url ? 'a' : 'div';
                    @endphp

                    <{{ $tag }} @if ($url) href="{{ $url }}" @endif
                        @class([
                            'ap-col group flex flex-col px-6',
                            // No divider at the start of a row. Which index starts a row depends
                            // on the width, so both breakpoints are marked and the CSS picks.
                            'ap-col-row-start-sm' => $i % 2 === 0,
                            'ap-col-row-start-lg' => $i % max($cols, 1) === 0,
                            'items-center text-center' => $centred,
                        ])>
                        @php $bare = in_array($item['icon'] ?? '', $ringIcons, true); @endphp

                        @if (filled($item['icon'] ?? '') && ! $bare)
                            <span @class([
                                'ap-badge transition-colors duration-200 ease-out',
                                'group-hover:bg-[var(--ap-blue)] group-hover:text-white' => $url,
                                // On a dark ground the disc inverts: a pale blue badge on navy
                                // is the one place the pale token stops reading as a surface.
                                '!bg-white/10 !text-white' => $onDark,
                            ])>
                                <x-icon :name="$item['icon']" class="h-11 w-11" stroke="1.4" />
                            </span>
                        @elseif (filled($item['icon'] ?? ''))
                            <span @class(['ap-mark', '!text-white' => $onDark])>
                                <x-icon :name="$item['icon']" class="h-12 w-12" stroke="1.6" />
                            </span>
                        @endif

                        @if (filled($item['title'] ?? ''))
                            {{-- The heading serif at label size — see `.ap-col-title`. It is the
                                 serif here that stops a row of four columns reading as a feature
                                 comparison table. --}}
                            <h3 @class([
                                'ap-col-title mt-6 text-[19px] leading-snug transition-colors duration-200 ease-out',
                                'text-white' => $onDark,
                                'text-[var(--ap-ink)]' => ! $onDark,
                                'group-hover:text-[var(--ap-blue)]' => $url && ! $onDark,
                            ])>{{ $item['title'] }}</h3>
                        @endif

                        @if (filled($item['text'] ?? ''))
                            <p @class([
                                't-body mt-3 max-w-[12.5rem] text-[15px] leading-[1.8]',
                                'text-white/65' => $onDark,
                                'text-[var(--ap-ink-soft)]' => ! $onDark,
                            ])>{{ $item['text'] }}</p>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif

        @if ($buttons)
            <div class="mt-12 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                @foreach ($buttons as $button)
                    <a href="{{ $button['url'] }}" @class([
                        't-btn',
                        'bg-[var(--ap-blue)] text-white hover:brightness-110' => $button['primary'] && ! $onDark,
                        'bg-white text-[var(--ap-blue)] hover:brightness-95' => $button['primary'] && $onDark,
                        'border border-[var(--ap-blue)]/30 text-[var(--ap-blue)] hover:border-[var(--ap-blue)]' => ! $button['primary'] && ! $onDark,
                        'border border-white/40 text-white hover:border-white' => ! $button['primary'] && $onDark,
                    ])>{{ $button['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
