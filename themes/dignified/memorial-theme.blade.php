{{--
    Dignified, on a memorial page.

    Pulled in by `pages/memorials/public.blade.php` through @includeIf, so it exists only for
    resellers running this template and costs every other site nothing.

    Scope is deliberately the hero and only the hero: the backdrop, the name, the dates and the
    rule under them. Everything below — the tabs, the tributes, the gallery, the comments, the
    timeline — is left exactly as the platform built it. Those are working parts and somebody's
    memories; a theme has no business restyling either, and a rule written here that reached
    them would be found by a grieving family rather than by us.

    The colours are read from BrandingHelper rather than hardcoded for the same reason the
    layout does it: a reseller who changes their brand colours moves this with them. The
    variables are redeclared here rather than borrowed, because this page extends
    layouts/fullscreen-layout and never passes through this template's own visitor layout.
--}}
{{-- The template's own tokens first, and the same file the site itself uses.

     This page renders <x-home-header /> — this template's header — from a layout that never
     defines them, so the nav bar was drawing white text on `bg-[var(--dg-ink)]`, an undefined
     variable, which resolves to nothing. On every memorial hosted by a reseller on this
     template, the navigation was invisible. --}}
@include('dg-theme-style')

@php
    $dgGold = \App\Helpers\BrandingHelper::accentColor();
    $dgRed = \App\Helpers\BrandingHelper::primaryColor();
@endphp
<style>
    /* Where to crop the backdrop this template ships.

       The band is a wide, short sliver, so only a fraction of the artwork can ever show. The
       platform's own scene is square and framed low and right; this one is a wide plate with
       flowers massed in the two lower corners and a dark middle, and it wants to be read
       across its lower half — which also leaves the centre dark, where the portrait sits. */
    :root { --t-memorial-backdrop-position: 50% 60%; }

    /* "In Loving Memory" — the template's eyebrow voice: gold, spaced, and in the body face
       rather than the serif, exactly as it is above every section of the main site. */
    .memorial-hero__eyebrow {
        font-family: 'Lato', system-ui, sans-serif;
        color: {{ $dgGold }};
    }

    .dark .memorial-hero__eyebrow { color: {{ $dgGold }}; }

    /* The name carries the template's heading face and its small caps. This is the single
       change that makes the page read as the same brand as the site it is hosted on. */
    .memorial-hero__name {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-variant-caps: small-caps;
        letter-spacing: 0.015em;
        font-weight: 500;
        color: #1a1a1a;
    }

    .dark .memorial-hero__name { color: #f7f7f7; }

    .memorial-hero__years {
        font-family: 'Cormorant Garamond', Georgia, serif;
        color: {{ $dgRed }};
    }

    .dark .memorial-hero__years { color: {{ $dgGold }}; }

    /* Line, heart, line. Gold, like every other rule on this template. */
    .memorial-hero__divider { color: {{ $dgGold }}; }
    .dark .memorial-hero__divider { color: {{ $dgGold }}; }

    /* The page's own ground.

       The memorial page is wrapped in `glass-bg-mesh`: three soft radial washes in indigo,
       pink and blue, over a grey-to-white gradient. That is the platform's own atmosphere and
       it is lovely on the platform. Under a black plate it is not: the washes are cool, so
       the band appeared to dissolve into one grey layer and then into a second, faintly
       lilac one before the page finally reached white — two edges where there should be
       none, which is exactly what "double white layer" describes.

       Flat paper instead, the same value the rest of this template's sections are set on, so
       the scene ends against the page rather than against a wash of somebody else's colour. */
    .glass-bg-mesh {
        background-image: none;
        background-color: var(--dg-paper);
    }

    .dark .glass-bg-mesh { background-color: var(--dg-ink); }

    /* The scene ends, rather than fading out.

       The platform dissolves the foot of its band so it can sit on any ground without knowing
       what colour that ground is. This template does know, and a dissolve is the wrong idiom
       for it anyway: every dark block on this site — the nav, the services band, the footer —
       ends on a hard edge, and a long grey ramp over a near-black plate reads as smudge rather
       than as atmosphere. The mask went, and with it the haze.

       What replaces it is the rule this template draws under everything else: gold, then
       crimson, in the same proportions as the footer bar and the heading rules. It gives the
       edge a reason to be there, and puts the brand's two colours across the full width of the
       one part of this page a template is allowed to dress. */
    .memorial-hero__band {
        height: 13rem;
        -webkit-mask-image: none;
        mask-image: none;
        border-bottom: 4px solid transparent;
        border-image-source: linear-gradient(
            to right,
            var(--dg-gold) 0%,
            var(--dg-gold) 38%,
            var(--dg-red) 72%,
            var(--dg-red) 100%
        );
        border-image-slice: 1;
    }

    @media (min-width: 640px) {
        .memorial-hero__band { height: 15rem; }
    }

    @media (min-width: 1024px) {
        .memorial-hero__band { height: 18rem; }
    }

    /* Square, and solid.

       Everything this template builds is a rectangle with a thin border — `--t-radius: 0` is
       the single loudest thing about it. The memorial page arrived rounded and translucent
       (`glass-card` is white at 55% with a 16px backdrop blur), which is the platform's
       material, not this one's, and it was the remaining reason the page read as a different
       website behind the same header.

       `rounded-full` is deliberately absent from the list: the avatars, the age bubble and the
       status pills are circles because they are circles, not because of a house style. */
    .glass-card {
        background: #ffffff;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border: 1px solid rgb(0 0 0 / 0.10);
    }

    .dark .glass-card {
        background: rgb(255 255 255 / 0.03);
        border-color: rgb(255 255 255 / 0.08);
    }

    main .rounded,
    main .rounded-md,
    main .rounded-lg,
    main .rounded-xl,
    main .rounded-2xl,
    main .rounded-3xl {
        border-radius: 0;
    }

    /* The tabs.

       The platform's selected tab is brand-derived three times over: crimson label, crimson
       tint behind it, crimson underline. On a palette whose crimson is already carrying the
       portrait frame, the nav's active block and every dated line on the page, a fourth use of
       it inside a white card is one red too many — and the label is the part that suffers,
       because crimson on pink is the weakest contrast on the page.

       The open tab is all three of this template's colours at once, in the order the rest of the
       site uses them: an ink block, a gold label on it, and under it the gold-then-crimson rule
       that runs beneath the footer, the section headings and the foot of the memorial band.
       Black is a brand colour here, not an absence of one — it is the nav bar, the services band
       and the footer — so the tab that is open is filled the way those are.

       Gold on ink rather than white, because white is what every other label on this card is and
       the open tab should not have to rely on its background alone to say so.

       border-image rather than a border-colour, for the same reason the band's foot uses it: two
       colours on one edge, with hard stops so the join is clean. The element has width on its
       bottom border only, so that is the only edge the image paints — a pixel thicker than the
       platform's, because a marker that cannot be seen from the other side of the card is not
       marking anything.

       Keyed on aria-selected rather than the utility classes: the tab script rewrites those on
       every click, but it sets the attribute in the same breath, so this holds for the tab the
       page opens on and for every tab opened after. */
    .memorial-tab-btn[aria-selected='true'],
    .dark .memorial-tab-btn[aria-selected='true'] {
        color: var(--dg-gold);
        background-color: var(--dg-ink);
        border-bottom: 3px solid transparent;
        border-image-source: linear-gradient(
            to right,
            var(--dg-gold) 0%,
            var(--dg-gold) 38%,
            var(--dg-red) 72%,
            var(--dg-red) 100%
        );
        border-image-slice: 1;
    }

    /* The initial that stands in for someone with no photograph.

       A crimson letter on a pink disc, beside every story, tribute and comment — so the more a
       family used the page, the more of it turned pink. The same block as the open tab, down to
       the letter on it: ink with gold, which is this template's pairing everywhere else and
       which lets the names beside these read as the thing on the row that matters.

       The subscribe bell's disc goes with them. It was left out of an earlier pass on the
       grounds that a crimson bell on black would be worse than the tint it replaced — which was
       true of a crimson bell. The icon is gold now, so it belongs with the rest.

       `rounded-full` is untouched by the squaring rule above, so these stay circles. */
    .rounded-full.bg-brand-100,
    .dark .rounded-full.bg-brand-100 {
        background-color: var(--dg-ink);
        color: var(--dg-gold);
    }

    /* An icon inside one of those discs carries its own text-brand-* class, so it does not
       inherit the colour above and has to be told. */
    .rounded-full.bg-brand-100 svg {
        color: var(--dg-gold);
    }

    /* "Invite Wilson's family and friends".

       A dashed crimson outline on a pink wash — the loudest thing in the sidebar, and the
       loudest use of crimson on a page that already spends it on the portrait frame, the years
       and the open tab. Ink and gold, like the tab and the discs; the dash stays, because it is
       what says this is something to add to rather than something already there.

       By id, which is what the button has and what beats the utility classes on it — including
       the hover, which would otherwise put the pink back on the way past. */
    #invite-share-btn,
    #invite-share-btn:hover {
        background-color: var(--dg-ink);
        border-color: var(--dg-gold);
        color: var(--dg-gold);
    }

    #invite-share-btn:hover {
        border-color: var(--dg-red);
    }

    /* The profile card parks below this template's header, not the platform's.

       That card is `md:sticky md:top-16 lg:top-[4.5rem]` — 64px and 72px, both measured against
       the platform's header. This one is taller at every width because it pins whole, logo and
       nav together: 100px from md, 167px from lg. Left alone the card slid under it by 36px and
       then by 95px.

       Each offset is the header's height plus a 1rem breath, so the card sits against it rather
       than touching. If the header's height changes, these change with it. */
    @media (min-width: 768px) {
        main aside > div { top: 7.25rem; }
    }

    @media (min-width: 1024px) {
        main aside > div { top: 11.5rem; }
    }

    /* The portrait, in the template's own two colours.

       The platform's mount is a plain white card, which is right for a page that has to suit
       every brand. On this one it is the only frame on the page, and it sits directly under a
       black-and-white photograph — so it carries the crimson and the gold the rest of the site
       is built from, top to bottom, with the blend between them doing the work of a third
       colour.

       Read from the reseller's palette rather than written out, so a funeral home that changes
       its brand takes this with it. Two stops only: a hand-placed orange in the middle would
       be a third colour nobody chose, and the gradient produces it anyway.

       Thin, and with no white mount inside it. The white ring read as a second frame around
       the first and made the whole thing heavier than the page it sits on; the photograph
       should meet the colour directly. */
    .memorial-hero__portrait {
        padding: 0.28rem;
        border-radius: 1.15rem;
        background: linear-gradient(180deg, {{ $dgRed }} 0%, {{ $dgGold }} 100%);
        box-shadow: 0 18px 42px rgb(0 0 0 / 0.28);
    }

    .dark .memorial-hero__portrait {
        background: linear-gradient(180deg, {{ $dgRed }} 0%, {{ $dgGold }} 100%);
        box-shadow: 0 18px 42px rgb(0 0 0 / 0.5);
    }

    .memorial-hero__portrait img {
        border: 0;
        border-radius: 0.95rem;
    }
</style>
