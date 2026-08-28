{{--
    A written page: About, the two legal pages, and anything a reseller adds by hand.

    All four render the same thing — a title band and a column of the reseller's own HTML — so
    they share this rather than carrying four copies of a prose stylesheet that would drift the
    first time one of them was adjusted.

    The prose rules are written out rather than left to Tailwind's `prose` plugin: the body font
    and heading font here come from the theme's tokens, and `prose` would impose its own
    measure, colours and heading scale on top of them. What a reseller pastes in from a Word
    document has to come out looking like the rest of their site.

    @param \App\Models\Page|null $page
    @param string  $title
    @param string|null $eyebrow
    @param bool $showUpdated
--}}
@php
    $eyebrow = $eyebrow ?? null;
    $showUpdated = $showUpdated ?? false;
    $heading = $page?->title ?: $title;
@endphp

@include('sections.page-banner', ['title' => $heading, 'eyebrow' => $eyebrow])

<section class="bg-[var(--dg-paper)] py-12 sm:py-14">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @if ($showUpdated && $page?->updated_at)
            <p class="mb-8 text-[11px] font-medium uppercase tracking-[0.16em] text-[#8a8a8a]">
                Last updated {{ $page->updated_at->format('j F Y') }}
            </p>
        @endif

        @if ($page && $page->is_published && filled($page->content))
            <div class="dg-body text-[15px] text-[#4a4a4a]
                [&_h2]:dg-caps [&_h2]:mt-10 [&_h2]:mb-4 [&_h2]:text-[24px] [&_h2]:leading-tight [&_h2]:text-[var(--dg-ink)]
                [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:text-[17px] [&_h3]:font-semibold [&_h3]:text-[var(--dg-ink)]
                [&_p]:mb-5
                [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2
                [&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2
                [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-[var(--dg-gold)] [&_blockquote]:pl-5 [&_blockquote]:italic
                [&_a]:text-[var(--dg-red)] [&_a]:underline [&_a]:underline-offset-2
                [&_img]:my-6 [&_img]:w-full
                [&_strong]:text-[var(--dg-ink)]">
                {!! $page->content !!}
            </div>
        @else
            {{-- Said plainly. A visitor who has arrived at an empty legal page needs to know it
                 is unfinished, not wonder whether their browser failed. --}}
            <div class="border border-[#e4e4e4] bg-white p-8 text-center">
                <p class="text-[15px] text-[#6a6a6a]">This page is being prepared. Please check back soon.</p>
                @if (filled(\App\Helpers\BrandingHelper::contactEmail()))
                    <a href="mailto:{{ \App\Helpers\BrandingHelper::contactEmail() }}"
                        class="mt-4 inline-flex items-center border border-[#c9c9c9] px-6 py-2.5 text-[11px] font-bold uppercase tracking-[0.16em] text-[var(--dg-ink)] transition-colors duration-200 ease-out hover:border-[var(--dg-ink)]">
                        Get in touch
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
