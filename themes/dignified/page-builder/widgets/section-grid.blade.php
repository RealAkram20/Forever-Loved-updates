{{--
    Card grid, in Dignified.

    This is the services grid from the reference: butted tiles with a hairline gap so the set
    reads as one object, the first tile carrying the gold fill, and the gold-into-crimson rules
    bracketing the whole thing. Hovering any other tile moves it toward the same state, which
    is what makes the gold read as "this one" rather than as decoration.

    On a light background the tiles invert — grey cards on paper — so the same widget works for
    a "why choose us" list without a second widget or a card-style setting nobody would
    understand the consequences of.
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
        2 => 'grid-cols-1 sm:grid-cols-2',
        4 => 'grid-cols-2 sm:grid-cols-4',
        default => 'grid-cols-2 sm:grid-cols-3',
    };

    $bg = R::background($props, [
        'page' => 'bg-[var(--dg-paper)]',
        'muted' => 'bg-[var(--dg-ink)]',
        'dark' => 'bg-[var(--dg-ink)]',
        'accent' => 'bg-[var(--dg-gold)]',
        'image' => 'bg-[var(--dg-ink)]',
    ]);

    $pad = R::padding($props, [
        'none' => '', 'sm' => 'py-6', 'md' => 'py-8 sm:py-9', 'lg' => 'py-12 sm:py-14',
    ]);

    $width = R::width($props, [
        'narrow' => 'max-w-3xl', 'normal' => 'max-w-6xl', 'wide' => 'max-w-7xl', 'full' => 'max-w-none',
    ]);

    // The florals plate, but only when this section is dark and sitting on the theme's own
    // background — a grid dropped onto a light page should not acquire a photograph.
    $showPlate = $onDark || ($props['background'] ?? 'muted') === 'muted';
@endphp

<section class="relative isolate overflow-hidden {{ $bg }}">
    @if ($showPlate)
        <img src="{{ asset('images/themes/dignified/services-bg.webp') }}" alt="" aria-hidden="true" loading="lazy"
            class="absolute inset-0 h-full w-full object-cover grayscale" />
        <div class="absolute inset-0 bg-black/55" aria-hidden="true"></div>
    @endif

    <div class="relative mx-auto {{ $width }} px-4 {{ $pad }} sm:px-6 lg:px-8">
        @if (filled($props['eyebrow'] ?? ''))
            @include('sections.eyebrow', ['text' => $props['eyebrow'], 'centered' => $centred, 'light' => $showPlate || $onDark])
        @endif

        @if (filled($props['heading'] ?? ''))
            <h2 @class([
                'dg-caps mt-4 text-[26px] leading-[1.2] sm:text-[32px]',
                'text-center' => $centred,
                'text-white' => $showPlate || $onDark,
                'text-[var(--dg-ink)]' => ! ($showPlate || $onDark),
            ])>{{ $props['heading'] }}</h2>
        @endif

        @if (filled($props['body'] ?? ''))
            <p @class([
                'dg-body mt-4 max-w-2xl text-[15px]',
                'mx-auto text-center' => $centred,
                'text-white/70' => $showPlate || $onDark,
                'text-[#5c5c5c]' => ! ($showPlate || $onDark),
            ])>{{ $props['body'] }}</p>
        @endif

        @if ($items->isNotEmpty())
            <div class="dg-rule mx-auto mt-5 h-px w-full max-w-4xl" aria-hidden="true"></div>

            <div class="mx-auto mt-5 grid max-w-4xl gap-1.5 {{ $colClass }}">
                @foreach ($items as $i => $item)
                    @php
                        $url = filled($item['url'] ?? '') ? R::url($item['url']) : null;
                        $featured = $i === 0;
                        $tag = $url ? 'a' : 'div';
                    @endphp

                    <{{ $tag }} @if ($url) href="{{ $url }}" @endif
                        @class([
                            'group flex flex-col items-center justify-center px-3 py-5 text-center transition-colors duration-200 ease-out sm:px-5 sm:py-6',
                            'bg-[var(--dg-gold)]' => $featured,
                            'bg-[#e9e9e9]' => ! $featured,
                            'hover:bg-[var(--dg-gold)]' => ! $featured && $url,
                        ])>
                        @if (filled($item['icon'] ?? ''))
                            <span class="text-[var(--dg-ink)]">
                                <x-icon :name="$item['icon']" class="h-12 w-12" stroke="1.5" />
                            </span>
                        @endif

                        @if (filled($item['title'] ?? ''))
                            <span class="dg-caps mt-3 block text-[14px] font-medium leading-[1.35] tracking-[0.06em] text-[var(--dg-ink)] sm:text-[15px]">{{ $item['title'] }}</span>
                        @endif

                        @if (filled($item['text'] ?? ''))
                            <span class="dg-body mt-1.5 block text-[12px] leading-snug text-[#5c5c5c]">{{ $item['text'] }}</span>
                        @endif

                        <span aria-hidden="true"
                            @class([
                                'mt-2.5 block h-px w-10 bg-[var(--dg-red)] transition-opacity duration-200 ease-out',
                                'opacity-100' => $featured,
                                'opacity-0 group-hover:opacity-100' => ! $featured,
                            ])></span>
                    </{{ $tag }}>
                @endforeach
            </div>

            <div class="dg-rule mx-auto mt-5 h-px w-full max-w-4xl" aria-hidden="true"></div>
        @endif

        @if ($buttons)
            <div class="mt-7 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                @foreach ($buttons as $button)
                    <a href="{{ $button['url'] }}" @class([
                        'inline-flex items-center px-7 py-3 text-[11px] font-bold uppercase tracking-[0.16em] transition-[background-color,border-color,filter,transform] duration-200 ease-out active:scale-[0.98]',
                        'border border-white/45 text-white hover:border-white hover:bg-white/10' => $showPlate || $onDark,
                        'bg-[var(--dg-gold)] text-[var(--dg-ink)] hover:brightness-95' => ! ($showPlate || $onDark) && $button['primary'],
                        'border border-[#c9c9c9] bg-white text-[var(--dg-ink)] hover:border-[var(--dg-ink)]' => ! ($showPlate || $onDark) && ! $button['primary'],
                    ])>{{ $button['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
</section>
