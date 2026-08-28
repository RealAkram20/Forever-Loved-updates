{{--
    Image beside text.

    Written once, for every template. What used to be forked per theme — the fonts, the
    letter-spacing, the corner radius, the button voice, the section rhythm — now comes from
    theme tokens, and the flourishes a template draws around them (a rule before the eyebrow, a
    frame around the photograph) hang off `.t-eyebrow` and `.t-figure` as pseudo-elements.

    A template overrides this file only if its *structure* differs. Dignified does not.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;

    $paragraphs = R::paragraphs($props['body'] ?? '');
    $buttons = R::buttons($props);
    $image = R::image($props['image'] ?? '');
    $imageRight = ($props['image_side'] ?? 'left') === 'right';
    $ratio = $props['image_ratio'] ?? '5/6';

    $onDark = in_array($props['background'] ?? 'page', ['dark', 'image'], true);

    $bg = match ($props['background'] ?? 'page') {
        'muted' => 'bg-[var(--t-surface-muted)]',
        'dark', 'image' => 'bg-[var(--t-surface-dark)]',
        'accent' => 'bg-[var(--t-accent)]',
        default => 'bg-[var(--t-surface-page)]',
    };

    $pad = match ($props['padding'] ?? 'md') {
        'none' => '', 'sm' => 'py-[var(--t-pad-sm)]', 'lg' => 'py-[var(--t-pad-lg)]',
        default => 'py-[var(--t-pad-md)]',
    };

    $width = R::width($props);
@endphp

<section class="{{ $bg }} {{ $pad }}">
    <div class="mx-auto grid {{ $width }} grid-cols-1 items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        @if ($image)
            <div class="{{ $imageRight ? 'lg:order-last' : '' }}">
                <figure class="t-figure mx-auto w-full max-w-[365px]">
                    <img src="{{ $image }}" alt="" loading="lazy" class="aspect-[{{ $ratio }}]" />
                </figure>
            </div>
        @endif

        <div>
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow {{ $onDark ? 'text-white/80' : 'text-[var(--t-accent)]' }}">{{ $props['eyebrow'] }}</p>
            @endif

            @if (filled($props['heading'] ?? ''))
                <h2 class="t-heading t-h2 mt-4 {{ $onDark ? 'text-white' : 'text-gray-900 dark:text-white' }}">{{ $props['heading'] }}</h2>
            @endif

            @if ($paragraphs)
                <div class="t-body mt-5 space-y-4 {{ $onDark ? 'text-white/70' : 'text-gray-600 dark:text-gray-300' }}">
                    @foreach ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endif

            @if ($buttons)
                <div class="mt-7 flex flex-wrap gap-3">
                    @foreach ($buttons as $button)
                        <a href="{{ $button['url'] }}" @class([
                            't-btn',
                            'bg-[var(--t-accent-2)] text-gray-900 hover:brightness-95' => $button['primary'],
                            'border border-current/30 hover:border-current' => ! $button['primary'],
                            'text-white' => ! $button['primary'] && $onDark,
                            'text-gray-900 dark:text-white' => ! $button['primary'] && ! $onDark,
                        ])>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
