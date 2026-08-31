{{--
    Image beside text, in A-Plus.

    Two departures from the platform's view, both structural, which is the only justification
    this repo accepts for forking one:

    1. The photograph is a landscape plate with room to carry something, rather than a portrait
       capped at 365px.
    2. When the section's primary button is a phone number, it is drawn as a card sitting over
       the foot of that photograph instead of as a pill under the text. That is the reference's
       most distinctive element, and it is keyed on something a reseller actually controls —
       "make this button a `tel:` link" — rather than on a hidden flag nobody would discover.
       Any other link renders as an ordinary button, so nothing about the widget changes for a
       section that has not asked for this.

    Everything else — type, radius, button voice, section rhythm — still comes from `--t-*`.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;
    use App\Helpers\ThemeSetting;

    $paragraphs = R::paragraphs($props['body'] ?? '');
    $buttons = R::buttons($props);
    $image = R::image($props['image'] ?? '');
    $imageRight = ($props['image_side'] ?? 'left') === 'right';
    $ratio = $props['image_ratio'] ?? '5/6';

    $onDark = in_array($props['background'] ?? 'page', ['dark', 'image'], true);

    $bg = match ($props['background'] ?? 'page') {
        'muted' => 'bg-[var(--ap-mist)]',
        'dark', 'image' => 'bg-[var(--ap-navy)]',
        'accent' => 'bg-[var(--ap-blue)]',
        default => 'bg-[var(--ap-paper)]',
    };

    $pad = match ($props['padding'] ?? 'md') {
        'none' => '', 'sm' => 'py-[var(--t-pad-sm)]', 'lg' => 'py-[var(--t-pad-lg)]',
        default => 'py-[var(--t-pad-md)]',
    };

    $width = R::width($props, [
        'narrow' => 'max-w-3xl', 'normal' => 'max-w-6xl', 'wide' => 'max-w-7xl', 'full' => 'max-w-none',
    ]);

    // A `tel:` primary button becomes the card over the photograph. Pulled out of the button
    // list so it is not also drawn as a pill underneath — one call to action, in one place.
    $callButton = null;

    foreach ($buttons as $i => $button) {
        if ($button['primary'] && str_starts_with(strtolower($button['url']), 'tel:')) {
            $callButton = $button;
            unset($buttons[$i]);

            break;
        }
    }

    $buttons = array_values($buttons);

    // The number to show on the card. The href is whatever the reseller typed; the visible
    // digits come from their contact setting when they have one, because a `tel:` href is
    // stripped of spaces and reads as a string of digits rather than as a phone number.
    $callDisplay = $callButton
        ? (ThemeSetting::get('branding.contact_phone') ?: rawurldecode(substr($callButton['url'], 4)))
        : null;
@endphp

<section class="{{ $bg }} {{ $pad }}">
    <div class="mx-auto grid {{ $width }} grid-cols-1 items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
        @if ($image)
            <div class="{{ $imageRight ? 'lg:order-last' : '' }}">
                {{-- `relative`, so the call card can be positioned against the photograph. The
                     figure keeps its `t-figure` hook so the shared radius and shadow still
                     apply and a future flourish still lands here. --}}
                <figure class="t-figure relative mx-auto w-full">
                    <img src="{{ $image }}" alt="" loading="lazy" class="aspect-[{{ $ratio }}]" />

                    @if ($callButton)
                        {{-- Overlapping the foot of the picture, not floating in the middle of
                             it: the faces in these photographs are in the upper two thirds, and
                             a panel across the centre covers the one thing the picture is for.

                             Below `sm` it drops out of the overlay and sits under the picture as
                             an ordinary block — at that width a card inset into a 340px-wide
                             photograph leaves the number on two lines over somebody's face. --}}
                        <figcaption class="mt-4 sm:absolute sm:inset-x-6 sm:bottom-6 sm:mt-0">
                            <a href="{{ $callButton['url'] }}"
                                class="flex items-center gap-4 rounded-[var(--t-radius)] bg-[var(--ap-blue)] p-5 shadow-[0_18px_40px_-16px_rgb(6_33_79_/_0.55)] transition-[filter,transform] duration-200 ease-out hover:brightness-110 active:scale-[0.99]">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/15 text-[var(--ap-gold)]">
                                    <x-icon name="phone" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="t-heading block text-[15px] leading-snug text-white">{{ $callButton['label'] }}</span>
                                    @if ($callDisplay)
                                        <span class="mt-1 block text-[15px] font-bold text-[var(--ap-gold)]">{{ $callDisplay }}</span>
                                    @endif
                                </span>
                            </a>
                        </figcaption>
                    @endif
                </figure>
            </div>
        @endif

        <div>
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow {{ $onDark ? 'text-[var(--ap-gold)]' : 'text-[var(--t-accent)]' }}">{{ $props['eyebrow'] }}</p>
            @endif

            @if (filled($props['heading'] ?? ''))
                <h2 class="t-heading t-h2 mt-3 {{ $onDark ? 'text-white' : 'text-[var(--ap-ink)]' }}">{{ $props['heading'] }}</h2>
            @endif

            {{-- The gold rule under the heading, the same mark the hero and every card title
                 carry. Drawn here rather than as a pseudo-element on `.t-heading`, because that
                 selector is also every card title in the grid, where the rule is already
                 explicit markup. --}}
            @if (filled($props['heading'] ?? ''))
                <span class="mt-4 block h-[3px] w-14 bg-[var(--ap-gold)]" aria-hidden="true"></span>
            @endif

            @if ($paragraphs)
                <div class="t-body mt-6 space-y-4 {{ $onDark ? 'text-white/70' : 'text-[var(--ap-ink-soft)]' }}">
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
                            'bg-[var(--ap-gold)] text-[var(--ap-navy)] hover:brightness-95' => $button['primary'],
                            'border border-current/30 hover:border-current' => ! $button['primary'],
                            'text-white' => ! $button['primary'] && $onDark,
                            'text-[var(--ap-blue)]' => ! $button['primary'] && ! $onDark,
                        ])>{{ $button['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
