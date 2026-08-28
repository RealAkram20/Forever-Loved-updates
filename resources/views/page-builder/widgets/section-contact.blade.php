{{--
    Where to find the business, and how to reach it.

    Content comes from Settings → Contact & Location rather than props, so this section stays
    correct wherever it is placed and a business that moves premises changes one field instead
    of every page an address was typed onto.

    Every row is conditional. A funeral home that has not given us its opening hours gets a
    shorter list, never a placeholder — a plausible-looking wrong address on this page sends
    someone to the wrong building on the worst day of their year.
--}}
@php
    use App\Helpers\BrandingHelper;
    use App\PageBuilder\Support\SectionRender as R;
    use App\Support\SiteContactDetails as CD;

    $appName = \App\Helpers\SiteShareMetaHelper::appDisplayName();
    $address = CD::get(CD::ADDRESS);
    $phone = CD::get(CD::PHONE);
    $phoneAlt = CD::get(CD::PHONE_ALT);
    $email = BrandingHelper::contactEmail();
    $hours = CD::get(CD::HOURS);
    $mapEmbed = CD::mapEmbedUrl();
    $mapUrl = CD::mapLinkUrl();

    $showMap = (bool) ($props['show_map'] ?? true);
    $mapLeft = ($props['map_side'] ?? 'right') === 'left';

    $rows = collect([
        ['map-pin', CD::lines($address), null],
        ['phone', array_values(array_filter([$phone, $phoneAlt])), 'tel'],
        ['mail', array_values(array_filter([$email])), 'mailto'],
        ['clock', CD::lines($hours), null],
    ])->filter(fn ($r) => count($r[1]) > 0);

    $pad = match ($props['padding'] ?? 'md') {
        'none' => '',
        'sm' => 'py-[var(--t-pad-sm)]',
        'lg' => 'py-[var(--t-pad-lg)]',
        default => 'py-[var(--t-pad-md)]',
    };

    $bg = match ($props['background'] ?? 'page') {
        'muted' => 'bg-[var(--t-surface-muted)]',
        'dark' => 'bg-[var(--t-surface-dark)]',
        default => 'bg-[var(--t-surface-page)]',
    };
@endphp

<section class="{{ $bg }} {{ $pad }}">
    <div @class([
        'mx-auto grid grid-cols-1 gap-10 px-4 sm:px-6 lg:gap-16 lg:px-8',
        R::width($props),
        'lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]' => $showMap,
    ])>
        <div class="{{ $showMap && $mapLeft ? 'lg:order-last' : '' }}">
            @if (filled($props['eyebrow'] ?? ''))
                <p class="t-eyebrow text-[var(--t-accent)]">{{ $props['eyebrow'] }}</p>
            @endif

            @if (filled($props['heading'] ?? ''))
                <h2 class="t-heading t-h4 mt-3 text-gray-900 dark:text-white">{{ $props['heading'] }}</h2>
                <span aria-hidden="true" class="t-contact-rule mt-3 block h-[3px] w-10 bg-[var(--t-accent)]"></span>
            @endif

            @if (filled($props['body'] ?? ''))
                <p class="t-body mt-4 max-w-md text-gray-600 dark:text-gray-300">{{ $props['body'] }}</p>
            @endif

            @if ($rows->isNotEmpty())
                <ul class="mt-7 space-y-5">
                    @foreach ($rows as [$icon, $values, $scheme])
                        <li class="flex gap-4">
                            <span class="mt-0.5 shrink-0 text-[var(--t-accent)]">
                                <x-icon :name="$icon" class="h-[18px] w-[18px]" stroke="1.6" />
                            </span>
                            <span class="t-body text-gray-600 dark:text-gray-300">
                                @foreach ($values as $value)
                                    @if ($scheme === 'tel')
                                        <a href="tel:{{ preg_replace('/[^+0-9]/', '', $value) }}" class="block hover:text-[var(--t-accent)]">{{ $value }}</a>
                                    @elseif ($scheme === 'mailto')
                                        <a href="mailto:{{ $value }}" class="block hover:text-[var(--t-accent)]">{{ $value }}</a>
                                    @else
                                        <span class="block">{{ $value }}</span>
                                    @endif
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($showMap)
            <div class="t-map relative flex min-h-[300px] flex-col">
                @if ($mapEmbed)
                    {{-- Their own map. Lazy because it is below the fold everywhere it appears,
                         and a third-party frame should not delay the content someone came for. --}}
                    <div class="t-map-frame flex-1 overflow-hidden">
                        <iframe src="{{ $mapEmbed }}" title="Map showing the location of {{ $appName }}"
                            class="block h-full min-h-[300px] w-full border-0"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>
                @else
                    {{-- A drawn stand-in until they paste a real map into Settings. It says
                         "roughly here" and links out to a real one, without costing every
                         visitor a third-party request for a locator they may never look at.

                         Deliberately unlabelled. An earlier version carried real street names,
                         which were correct for exactly one reseller and confidently, legibly
                         wrong on every other site that rendered it. --}}
                    <a @if ($mapUrl) href="{{ $mapUrl }}" target="_blank" rel="noopener" @endif
                        class="t-map-frame flex flex-1 overflow-hidden"
                        @unless ($mapUrl) aria-hidden="true" @endunless>
                        <svg viewBox="0 0 560 268" preserveAspectRatio="xMidYMid slice" class="block h-full w-full"
                            role="img" aria-label="Map showing the location of {{ $appName }}">
                            <rect width="560" height="268" fill="#f4f4f4" />
                            <g fill="#ededed">
                                <path d="M0 0h300L250 120 0 96z" />
                                <path d="M330 0h230v78L360 96z" />
                                <path d="M0 130l246 22-40 148H0z" />
                                <path d="M300 150l260 26v124H250z" />
                            </g>
                            <g stroke="#ffffff" fill="none" stroke-linecap="round">
                                <path d="M-10 108 L580 140" stroke-width="16" />
                                <path d="M312 -10 L268 310" stroke-width="13" />
                                <path d="M-10 250 L580 196" stroke-width="9" />
                                <path d="M120 -10 L96 130" stroke-width="6" />
                                <path d="M430 60 L470 310" stroke-width="6" />
                            </g>
                        </svg>
                    </a>

                    {{-- The pin sits over the drawing rather than inside it, so the label can
                         wrap as real text at whatever length the business name happens to be. --}}
                    <div class="pointer-events-none absolute left-1/2 top-1/2 flex -translate-y-1/2 items-center gap-2">
                        <svg class="h-9 w-9 drop-shadow" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 22s7-6.1 7-11.6A7 7 0 1 0 5 10.4C5 15.9 12 22 12 22z" />
                            <circle cx="12" cy="10" r="2.6" fill="#ffffff" />
                        </svg>
                        <span class="t-map-label max-w-[190px] bg-white px-2 py-1 text-[11px] font-medium leading-tight text-gray-900 shadow-sm">{{ $appName }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
