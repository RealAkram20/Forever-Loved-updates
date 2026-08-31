{{--
    Card grid, in A-Plus.

    The services grid from the reference: separated cards on a faint blue ground, the first one
    filled with the brand blue so the set has a starting point, a gold rule under every title,
    and a "Learn More" with a gold disc after it on the cards that go somewhere.

    Overridden rather than tokenised because the *structure* differs from the plain view, which
    is the only reason this repo allows a fork. The plain card is an icon, a title and a line of
    text; this one adds a rule and a footer row, and the footer row exists only on cards that
    have a link — which the plain view has no concept of, because its whole card is the link.

    Everything that is merely a matter of taste — the type, the radius, the button voice, the
    section rhythm — still comes from `--t-*` in ap-theme-style.blade.php. If a change can be
    made there, it belongs there and not here.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;

    $items = collect($props['items'] ?? [])
        ->filter(fn ($i) => is_array($i) && (filled($i['title'] ?? '') || filled($i['text'] ?? '')))
        ->values();

    $buttons = R::buttons($props);
    $centred = ($props['alignment'] ?? 'center') === 'center';
    $onDark = in_array($props['background'] ?? 'muted', ['dark', 'image'], true);

    $cols = (int) ($props['columns'] ?? 3);
    $colClass = match ($cols) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };

    // Four columns on a dark ground is this template's statistics strip.
    //
    // A theme deciding what a widget *looks like* in a given context is the arrangement the
    // token system already assumes — Dignified renders the same widget as butted tiles on a
    // florals plate when it is dark, and as grey cards on paper when it is not. Here the same
    // signal produces the reference's inset navy band: a figure, a label, and a hairline
    // between each pair.
    //
    // Keyed on the two props a reseller actually sets rather than on a hidden style flag, so
    // it is reachable from the page builder without anyone documenting a secret. A stat is
    // exactly what this widget's item already is — a short `title` over a short `text` — so
    // nothing about the data shape is bent to fit.
    $statStrip = $onDark && $cols === 4;

    $bg = $statStrip ? 'bg-[var(--ap-paper)]' : R::background($props, [
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
            {{-- Heading left, standfirst right — the reference's arrangement, and the reason it
                 works is that the two are read as one sentence broken across the page rather
                 than as a title with a caption under it. Only when there is copy to put on the
                 right; a heading on its own centres or runs full width as it always did. --}}
            <div @class([
                'lg:grid lg:items-end lg:gap-12' => filled($props['body'] ?? '') && ! $centred,
                'lg:grid-cols-2' => filled($props['body'] ?? '') && ! $centred,
                'text-center' => $centred,
            ])>
                <div>
                    @if (filled($props['eyebrow'] ?? ''))
                        <p class="t-eyebrow {{ $onDark ? 'text-[var(--ap-gold)]' : 'text-[var(--ap-blue)]' }}">{{ $props['eyebrow'] }}</p>
                    @endif

                    @if (filled($props['heading'] ?? ''))
                        <h2 class="t-heading t-h2 mt-3 {{ $onDark ? 'text-white' : 'text-[var(--ap-ink)]' }}">{{ $props['heading'] }}</h2>
                    @endif
                </div>

                @if (filled($props['body'] ?? ''))
                    <p @class([
                        't-body mt-4 text-[15px]',
                        'mx-auto max-w-2xl' => $centred,
                        'lg:mt-0' => ! $centred,
                        'text-white/70' => $onDark,
                        'text-[var(--ap-ink-soft)]' => ! $onDark,
                    ])>{{ $props['body'] }}</p>
                @endif
            </div>
        @endif

        @if ($items->isNotEmpty() && $statStrip)
            {{-- The statistics band: an inset navy plate over a drained photograph, four
                 figures across it, hairlines between. Inset rather than full-bleed because in
                 the reference it sits *on* the page as an object, with the white showing past
                 its corners — full-bleed would read as another section rather than as a card.

                 The hairline is a left border on every item after the first in its row, so it
                 appears between pairs at two columns and between all four at full width,
                 without a divider ever dangling at the start of a row. --}}
            <div class="relative isolate mt-10 overflow-hidden rounded-[var(--t-radius)] bg-[var(--ap-navy)]">
                <img src="{{ asset('images/themes/a-plus/hero.webp') }}" alt="" aria-hidden="true" loading="lazy"
                    class="absolute inset-0 h-full w-full object-cover opacity-25 grayscale" />
                <div class="absolute inset-0 bg-[var(--ap-navy)]/80" aria-hidden="true"></div>

                <div class="relative grid grid-cols-2 gap-y-8 px-6 py-9 sm:px-8 lg:grid-cols-4 lg:gap-y-0">
                    @foreach ($items as $i => $item)
                        <div @class([
                            'flex flex-col items-center px-4 text-center',
                            'border-s border-white/15' => $i % 2 === 1,
                            'lg:border-s' => true,
                            'lg:border-s-0' => $i % 4 === 0,
                            'border-s-0' => $i % 2 === 0,
                        ])>
                            @if (filled($item['icon'] ?? ''))
                                <x-icon :name="$item['icon']" class="mb-3 h-7 w-7 text-[var(--ap-gold)]" stroke="1.6" />
                            @endif

                            @if (filled($item['title'] ?? ''))
                                {{-- The figure. Deliberately the loudest type on the page after
                                     the hero: a statistic that has to be read as a number rather
                                     than as a heading. --}}
                                <p class="t-heading text-[28px] leading-none text-white sm:text-[32px]">{{ $item['title'] }}</p>
                            @endif

                            @if (filled($item['text'] ?? ''))
                                <p class="t-body mt-2 text-[13px] text-white/70">{{ $item['text'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif ($items->isNotEmpty())
            <div class="mt-10 grid grid-cols-1 gap-5 {{ $colClass }}">
                @foreach ($items as $i => $item)
                    @php
                        $url = filled($item['url'] ?? '') ? R::url($item['url']) : null;

                        // The first card carries the fill. Not "the important one" — the items
                        // are in the order the reseller put them in and this template has no
                        // way to know which matters most. It is a starting point for the eye,
                        // and it makes a grid of six identical tiles into a composition.
                        $featured = $i === 0 && ! $onDark;

                        // A card without a link is a <div>, not an <a> going nowhere: a pointer
                        // cursor that leads nowhere is a small lie the whole page pays for.
                        $tag = $url ? 'a' : 'div';
                    @endphp

                    <{{ $tag }} @if ($url) href="{{ $url }}" @endif
                        @class([
                            'group flex flex-col rounded-[var(--t-radius)] p-7 transition-[background-color,border-color,box-shadow,transform] duration-200 ease-out',
                            'bg-[var(--ap-blue)]' => $featured,
                            'border border-[var(--t-border)] bg-white' => ! $featured && ! $onDark,
                            'border border-white/12 bg-white/[0.04]' => $onDark,
                            'hover:-translate-y-1 hover:shadow-[0_18px_40px_-20px_rgb(6_33_79_/_0.45)]' => $url,
                            'items-center text-center' => $centred,
                        ])>
                        @if (filled($item['icon'] ?? ''))
                            <span @class([
                                'text-[var(--ap-gold)]' => $featured || $onDark,
                                'text-[var(--ap-blue)]' => ! $featured && ! $onDark,
                            ])>
                                <x-icon :name="$item['icon']" class="h-9 w-9" stroke="1.6" />
                            </span>
                        @endif

                        @if (filled($item['title'] ?? ''))
                            <h3 class="t-heading mt-5 text-[17px] leading-snug {{ $featured || $onDark ? 'text-white' : 'text-[var(--ap-ink)]' }}">{{ $item['title'] }}</h3>
                        @endif

                        {{-- The gold rule under every title. Gold on the blue tile as well as on
                             the white ones: it is the one mark that is the same on both, which is
                             what keeps the filled card reading as a member of the set rather than
                             as a different component. --}}
                        <span class="mt-3 block h-[3px] w-9 bg-[var(--ap-gold)] {{ $centred ? 'mx-auto' : '' }}" aria-hidden="true"></span>

                        @if (filled($item['text'] ?? ''))
                            <p class="t-body mt-4 text-[14px] {{ $featured || $onDark ? 'text-white/75' : 'text-[var(--ap-ink-soft)]' }}">{{ $item['text'] }}</p>
                        @endif

                        @if ($url)
                            {{-- Pushed to the bottom by mt-auto so the "Learn More" of every card
                                 in a row lines up regardless of how much text each one carries.
                                 Without it a three-word card and a two-line card put their links
                                 at different heights and the row reads as ragged. --}}
                            <span @class([
                                'mt-auto flex items-center gap-2.5 pt-6 text-[13px] font-semibold',
                                'text-[var(--ap-gold)]' => $featured || $onDark,
                                'text-[var(--ap-blue)]' => ! $featured && ! $onDark,
                            ])>
                                <span>Learn More</span>
                                <span @class([
                                    'flex h-5 w-5 items-center justify-center rounded-full transition-transform duration-200 ease-out group-hover:translate-x-1',
                                    'bg-[var(--ap-gold)] text-[var(--ap-navy)]' => ! $featured && ! $onDark,
                                    'bg-white/15 text-[var(--ap-gold)]' => $featured || $onDark,
                                ])>
                                    <x-icon name="arrow-right" class="h-3 w-3" />
                                </span>
                            </span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif

        @if ($buttons)
            <div class="mt-10 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                @foreach ($buttons as $button)
                    <a href="{{ $button['url'] }}" @class([
                        't-btn',
                        'bg-[var(--ap-gold)] text-[var(--ap-navy)] hover:brightness-95' => $button['primary'],
                        'border border-current/35 hover:border-current' => ! $button['primary'],
                        'text-white' => ! $button['primary'] && $onDark,
                        'text-[var(--ap-blue)]' => ! $button['primary'] && ! $onDark,
                    ])>{{ $button['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
