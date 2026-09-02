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
    @param string      $title
    @param string|null $eyebrow
    @param bool        $showUpdated
--}}
@php
    $eyebrow = $eyebrow ?? null;
    $showUpdated = $showUpdated ?? false;
    $heading = $page?->title ?: $title;
@endphp

@include('sections.page-banner', ['title' => $heading, 'eyebrow' => $eyebrow])

<section class="bg-[var(--ap-paper)] py-[var(--t-pad-md)]">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        @if ($showUpdated && $page?->updated_at)
            <p class="mb-8 text-[12px] font-semibold uppercase tracking-[0.16em] text-[var(--ap-ink-soft)]">
                Last updated {{ $page->updated_at->format('j F Y') }}
            </p>
        @endif

        @if ($page && $page->is_published && filled($page->content))
            {{-- Headings inside pasted content take the template's serif; body, lists and
                 quotes take the body face. The link colour is the brand navy rather than a
                 default blue, which is the one thing people notice immediately when it is
                 wrong on a legal page. --}}
            <div class="t-body text-[15px] text-[var(--ap-ink-soft)]
                [&_h2]:mt-10 [&_h2]:mb-4 [&_h2]:font-[family-name:var(--t-heading-family)] [&_h2]:text-[26px] [&_h2]:leading-tight [&_h2]:text-[var(--ap-ink)]
                [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:font-[family-name:var(--t-heading-family)] [&_h3]:text-[19px] [&_h3]:text-[var(--ap-ink)]
                [&_p]:mb-5
                [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2
                [&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2
                [&_blockquote]:my-6 [&_blockquote]:border-l-2 [&_blockquote]:border-[var(--ap-gold)] [&_blockquote]:pl-5 [&_blockquote]:italic
                [&_a]:text-[var(--ap-blue)] [&_a]:underline [&_a]:underline-offset-2
                [&_img]:my-6 [&_img]:w-full [&_img]:rounded-[var(--t-radius)]
                [&_strong]:text-[var(--ap-ink)]">
                {!! $page->content !!}
            </div>
        @else
            {{-- Said plainly. A visitor who has arrived at an empty legal page needs to know it
                 is unfinished, not wonder whether their browser failed. --}}
            <div class="rounded-[var(--t-radius)] border border-[var(--ap-line)] bg-[var(--ap-mist)] p-10 text-center">
                <p class="t-body text-[15px] text-[var(--ap-ink-soft)]">This page is being prepared. Please check back soon.</p>

                @if (filled(\App\Helpers\BrandingHelper::contactEmail()))
                    <a href="mailto:{{ \App\Helpers\BrandingHelper::contactEmail() }}"
                        class="t-btn mt-6 bg-[var(--ap-blue)] text-white transition-[filter] duration-200 ease-out hover:brightness-110">
                        Get in touch
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
