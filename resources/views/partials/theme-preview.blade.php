{{--
    A template's preview tile.

    Prefers a real screenshot when the template ships one, and otherwise draws a wireframe of
    the sections its manifest actually declares — so the tile says something true about the
    theme rather than filling space. A grey placeholder would make every card look identical,
    which is the one thing a gallery must never do.

    Drawn in the viewer's own brand colour, because what they are choosing between is layout;
    the palette is theirs either way and showing someone else's would misrepresent both.

    @param \App\Themes\ThemeManifest|null $manifest
--}}
@php
    $screenshot = $manifest?->screenshotUrl();
    // homeShape() reads the front page the template actually ships, falling back to its
    // declared blocks — see there for why the two can disagree.
    // A template with no declared arrangement still gets a shape rather than a void — the
    // most common one, which is also what SiteLayoutService falls back to.
    $types = $manifest?->homeShape() ?: ['hero', 'features_grid'];
@endphp

<div class="relative aspect-[16/10] overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
    @if ($screenshot)
        <img src="{{ $screenshot }}" alt="" loading="lazy" class="h-full w-full object-cover object-top" />
    @else
        <div class="flex h-full w-full flex-col gap-1.5 p-3" aria-hidden="true">
            {{-- Header bar: every template has one, and it anchors the wireframe as a page. --}}
            <div class="flex shrink-0 items-center gap-1.5 rounded-md bg-white px-2 py-1.5 shadow-theme-sm dark:bg-white/[0.06]">
                <span class="h-1.5 w-6 rounded-full bg-brand-500"></span>
                <span class="ml-auto h-1 w-4 rounded-full bg-gray-300 dark:bg-white/20"></span>
                <span class="h-1 w-4 rounded-full bg-gray-300 dark:bg-white/20"></span>
                <span class="h-1 w-4 rounded-full bg-gray-300 dark:bg-white/20"></span>
            </div>

            <div class="flex min-h-0 flex-1 flex-col gap-1.5">
                @foreach (array_slice($types, 0, 4) as $type)
                    @switch($type)
                        @case('hero')
                            <div class="flex flex-[2] flex-col items-center justify-center gap-1 rounded-md bg-brand-500/12 dark:bg-brand-500/20">
                                <span class="h-1.5 w-1/2 rounded-full bg-brand-500/70"></span>
                                <span class="h-1 w-1/3 rounded-full bg-gray-400/60 dark:bg-white/25"></span>
                                <span class="mt-0.5 h-2 w-10 rounded-full bg-brand-500"></span>
                            </div>
                            @break

                        @case('memorial_showcase')
                            <div class="flex flex-1 items-stretch gap-1.5">
                                @for ($i = 0; $i < 4; $i++)
                                    <div class="flex-1 rounded-md bg-white shadow-theme-sm dark:bg-white/[0.06]"></div>
                                @endfor
                            </div>
                            @break

                        @case('features_grid')
                            <div class="grid flex-1 grid-cols-3 gap-1.5">
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="flex flex-col justify-center gap-1 rounded-md bg-white p-1.5 shadow-theme-sm dark:bg-white/[0.06]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                                        <span class="h-1 w-full rounded-full bg-gray-300 dark:bg-white/20"></span>
                                    </div>
                                @endfor
                            </div>
                            @break

                        @case('cta_banner')
                            <div class="flex flex-1 items-center justify-center gap-1.5 rounded-md bg-brand-600">
                                <span class="h-1 w-1/4 rounded-full bg-white/70"></span>
                                <span class="h-2 w-8 rounded-full bg-white"></span>
                            </div>
                            @break

                        {{-- The reseller widget set. Every template built since section_* landed
                             is made of these, so without them the tile drew a stack of identical
                             grey bars for the newest themes and a real shape only for the oldest
                             — the gallery's cards looked least informative for the themes people
                             were most likely to be choosing between. --}}
                        @case('section_banner')
                            <div class="flex flex-[2] flex-col justify-center gap-1 rounded-md bg-brand-500/12 px-2 dark:bg-brand-500/20">
                                <span class="h-1.5 w-2/3 rounded-full bg-brand-500/70"></span>
                                <span class="h-1 w-1/2 rounded-full bg-gray-400/60 dark:bg-white/25"></span>
                                <span class="mt-0.5 h-2 w-8 rounded-full bg-brand-500"></span>
                            </div>
                            @break

                        @case('section_split')
                            <div class="flex flex-1 items-stretch gap-1.5">
                                <div class="w-2/5 rounded-md bg-white shadow-theme-sm dark:bg-white/[0.06]"></div>
                                <div class="flex flex-1 flex-col justify-center gap-1">
                                    <span class="h-1 w-1/3 rounded-full bg-brand-500"></span>
                                    <span class="h-1 w-full rounded-full bg-gray-300 dark:bg-white/20"></span>
                                    <span class="h-1 w-4/5 rounded-full bg-gray-300 dark:bg-white/20"></span>
                                </div>
                            </div>
                            @break

                        @case('section_grid')
                            <div class="grid flex-1 grid-cols-3 gap-1.5">
                                @for ($i = 0; $i < 3; $i++)
                                    <div class="flex flex-col justify-center gap-1 rounded-md bg-white p-1.5 shadow-theme-sm dark:bg-white/[0.06]">
                                        <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                                        <span class="h-1 w-full rounded-full bg-gray-300 dark:bg-white/20"></span>
                                    </div>
                                @endfor
                            </div>
                            @break

                        @case('section_contact')
                            <div class="flex flex-1 items-stretch gap-1.5">
                                <div class="flex flex-1 flex-col justify-center gap-1">
                                    <span class="h-1 w-full rounded-full bg-gray-300 dark:bg-white/20"></span>
                                    <span class="h-1 w-full rounded-full bg-gray-300 dark:bg-white/20"></span>
                                    <span class="h-2 w-8 rounded-full bg-brand-500"></span>
                                </div>
                                <div class="w-1/3 rounded-md bg-white shadow-theme-sm dark:bg-white/[0.06]"></div>
                            </div>
                            @break

                        @default
                            <div class="flex-1 rounded-md bg-white shadow-theme-sm dark:bg-white/[0.06]"></div>
                    @endswitch
                @endforeach
            </div>
        </div>
    @endif
</div>
