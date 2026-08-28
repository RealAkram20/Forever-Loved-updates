{{--
    Words over a background, with somewhere to go next.

    One view for every template. The hero, an inner-page title band, a call to action and the
    feedback bar are the same object at four heights, and the differences between templates —
    type, corner radius, button voice, whether the photograph is desaturated, whether a rule
    runs down the copy — are tokens and hooks rather than a forked file.
--}}
@php
    use App\PageBuilder\Support\SectionRender as R;

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

    // One span per sentence. CSS decides whether they sit on separate lines, so a template
    // that wants a two-line hero gets one without anybody hand-writing a line break.
    $heading = trim((string) ($props['heading'] ?? ''));
    $headingLines = $heading === '' ? [] : (preg_split('/(?<=\.)\s+/', $heading, 2) ?: [$heading]);
@endphp

{{-- data-banner-height so a template can style the hero differently from the four other
     things this same view renders. Without it the only handle is the padding class, and a
     rule meant for the front page's hero landed on every inner-page title band too. --}}
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
            // The rule only earns its place beside left-aligned copy; centred text with a rule
            // down one side reads as a mistake. The view decides *whether* a rule belongs
            // here; a template decides what it looks like.
            't-banner-ruled ps-6 sm:ps-8' => ! $centred && $height !== 'band',
        ])>
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow {{ $dark ? 'text-white/80' : 'text-[var(--t-accent)]' }}">{{ $props['eyebrow'] }}</p>
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

            @if ($wantsForm)
                @include('page-builder.widgets.partials.banner-form', ['props' => $props, 'onImage' => $dark])
            @endif

            @if ($buttons)
                <div class="mt-7 flex flex-wrap gap-3 {{ $centred ? 'justify-center' : '' }}">
                    @foreach ($buttons as $button)
                        <a href="{{ $button['url'] }}" @class([
                            't-btn',
                            'bg-[var(--t-accent-2)] text-gray-900 hover:brightness-95' => $button['primary'],
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
