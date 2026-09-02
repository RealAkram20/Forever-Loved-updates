{{--
    A-Plus, on a memorial page.

    Pulled in by `pages/memorials/public.blade.php` through @includeIf, so it exists only for
    resellers running this template and costs every other site nothing.

    Scope is deliberately narrow: the shell, the hero, and the few pieces of chrome that carry
    the platform's own brand colour into the middle of somebody else's site — the open tab, the
    initial discs, the invite button. Everything else — the tributes, the gallery, the comments,
    the timeline — is left exactly as the platform built it. Those are working parts and
    somebody's memories; a theme has no business restyling either, and a rule written here that
    reached them would be found by a grieving family rather than by us.
--}}
{{-- The template's own tokens first, and the same file the site itself uses.

     Not optional, and not a nicety. This page extends layouts/fullscreen-layout and renders
     <x-home-header /> from a layout that never defines `--ap-blue`, `--ap-gold` or `--ap-ink`.
     Without this line the utility strip draws white text on an undefined background and the
     "Call Us Now" pill loses its fill — the header is there and unreadable, on every memorial
     this reseller hosts. Verified by rendering the page with the include removed, which is the
     only way this class of bug is ever found. --}}
@include('ap-theme-style')

@php
    $apBlue = \App\Helpers\BrandingHelper::primaryColor();
    $apGold = \App\Helpers\BrandingHelper::accentColor();
@endphp

{{-- The petals that fall when Leave a Flower is tapped.

     A spread along one flower's own gradient rather than one average tint, the way
     public/images/tributes/README.md says to sample them — so what falls is a flower coming
     apart rather than confetti.

     Gold, not blue. This template's two colours are a deep navy and a gold, and a navy petal
     does not read as a petal at all: at the size these fall it reads as a chip of something
     broken. The gold runs from bronze at the outer edge to pale at the heart, which is a real
     flower and is still unmistakably this brand's second colour.

     The extremes are left out at both ends, for the same reason the platform's are. Each petal
     carries a black overlay and a white one; on true bronze the first takes the shape to a hole
     in the page, and on the palest gold the second takes it to near-white, which against this
     template's white paper is not a petal either.

     Set before the memorial bundle runs, which is what lets it read this rather than its own. --}}
<script>window.__tributePetalColours = ['#7A5A05', '#A87C08', '#CE9A0B', '#E5B310', '#F3C635', '#F8D566'];</script>

{{-- The night the candle scene is painted on.

     The platform's is violet, so its field of candles sits in our brand colour rather than in a
     void. This template is not built out of violet, and on a blue-and-gold brand that sky was
     the one surface still saying somebody else's name — it opens full-screen, so it says it
     loudly.

     Five stops, top to bottom, keeping the shape the platform's has rather than inventing one:
     deepest at the very top and the very bottom, lightest across the horizon band. That band is
     where the candles crowd, and letting the ground lift there reads as their own light hanging
     in the air instead of a flat backdrop behind them.

     Navy rather than the near-black Dignified uses, because gold on navy keeps the hue contrast
     that made the platform's violet work — the flames stay separate from their own sky. It stays
     dark because it has to: every flame, halo and star composites additively onto this, so the
     ground is what they are adding to. --}}
<script>window.__candleSceneSky = ['#04102B', '#071A3E', '#0C2A5C', '#061733', '#030B1C'];</script>

<style>
    /* Where to crop the backdrop this template ships.

       The band is a wide, short sliver — at 1440px it shows about a fifth of the artwork's
       height — so the crop decides what the picture is *of*.

       This one is a lit candle at the lower left under a blue-to-gold wash. Centred or high,
       the band is a pretty gradient and the candle never appears at all; the flame is the only
       thing in the frame that means anything, so the window is pushed down until it is in it.
       Low enough to hold the flame and the top of the candle, not so low that the base is all
       that is left. The candle meeting the gold rule at the foot of the band is the intended
       reading, not an accident of cropping.

       Narrow screens show a much taller slice of the same image — 9rem of a 260px-tall render
       is over half its height, against a fifth on the desktop — so the same offset lands more
       of the candle there, which is the right way round for the smaller picture. */
    :root { --t-memorial-backdrop-position: 50% 82%; }

    /* "In Loving Memory" — the template's eyebrow voice: gold, spaced, and in the body face
       rather than the heading face, exactly as it is above every section of the main site. */
    .memorial-hero__eyebrow {
        font-family: 'Open Sans', system-ui, sans-serif;
        color: {{ $apGold }};
    }

    .dark .memorial-hero__eyebrow { color: {{ $apGold }}; }

    /* The name carries the template's heading face. This is the single change that makes the
       page read as the same brand as the site hosting it. */
    .memorial-hero__name {
        font-family: 'Montserrat', system-ui, sans-serif;
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--ap-ink);
    }

    .dark .memorial-hero__name { color: #f7f7f7; }

    .memorial-hero__years {
        font-family: 'Montserrat', system-ui, sans-serif;
        font-weight: 600;
        color: {{ $apBlue }};
    }

    .dark .memorial-hero__years { color: {{ $apGold }}; }

    /* Line, heart, line. Gold, like every other rule on this template. */
    .memorial-hero__divider { color: {{ $apGold }}; }
    .dark .memorial-hero__divider { color: {{ $apGold }}; }

    /* The page's own ground.

       The memorial page is wrapped in `glass-bg-mesh`: soft radial washes in indigo, pink and
       blue over a grey-to-white gradient. That is the platform's atmosphere and it is lovely on
       the platform. Here it fights the navy band above it and puts a pink cast on a page whose
       whole palette is two colours, neither of them pink. Flat paper instead — the same value
       every section of the main site is set on, so the band ends against the page rather than
       against a wash of somebody else's colour. */
    .glass-bg-mesh {
        background-image: none;
        background-color: var(--ap-paper);
    }

    .dark .glass-bg-mesh { background-color: var(--ap-navy); }

    /* The scene ends, rather than fading out.

       The platform dissolves the foot of its band so it can sit on any ground without knowing
       what colour that ground is. This template does know. What replaces the dissolve is the
       mark this template draws under everything else — the gold bar that sits under every
       heading, every card title and every footer column. It gives the edge a reason to be there
       and puts the brand's second colour across the full width of the one part of this page a
       template is allowed to dress.

       The height is deliberately left alone. `.memorial-hero__band`'s height and
       `.memorial-hero__frame`'s padding-top are a matched pair in the base stylesheet —
       9rem, then 11rem, then 13rem, each set twice — and the frame's padding is the only thing
       keeping the name below the scene. Raising the band without raising the padding drops the
       name onto the photograph, half on and half off the edge, in dark ink on a dark picture.
       Found by rendering it. The edge treatment is all this template needs, so the edge
       treatment is all it changes. */
    .memorial-hero__band {
        -webkit-mask-image: none;
        mask-image: none;
        border-bottom: 4px solid {{ $apGold }};
    }

    /* Solid, not frosted.

       `glass-card` is white at 55% with a 16px backdrop blur — the platform's material. This
       template builds in flat white cards with a hairline border and a soft drop shadow, and
       the translucency was the remaining reason the page read as a different website behind the
       same header.

       The radius is deliberately left alone. Dignified squares everything here because it is a
       square-cornered design; this one is not, and the platform's rounded cards are already
       what this template's own cards look like. A rule that changed them would be a template
       restyling for the sake of having restyled. */
    .glass-card {
        background: #ffffff;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border: 1px solid var(--t-border);
    }

    .dark .glass-card {
        background: rgb(255 255 255 / 0.03);
        border-color: rgb(255 255 255 / 0.08);
    }

    /* The tabs.

       The open tab is a solid block of the brand blue with a white label on it and the amber
       bar underneath. Asked for directly, against a reference showing a filled dark tab with a
       coloured rule beneath it.

       Two earlier attempts are worth recording, because both were wrong in the same direction.
       The platform's own version tints the open tab pale — on this palette that lands as a
       wash that reads as a *disabled* control rather than the open one. Replacing the tint with
       a coloured label on white then failed for the opposite reason: the platform sets the
       closed tabs `text-gray-600` (#4B5563), which against #1B3A6B is a difference of hue and
       almost none of lightness, so four tabs read as one row of grey-blue words and the amber
       bar did all the work alone. Bolding the open one helped and was still not enough.

       A fill settles it. Nothing else on this page is a solid blue rectangle, so there is no
       ambiguity about which tab is open, and white on #1B3A6B is 11.27:1.

       Keyed on aria-selected rather than the utility classes: the tab script rewrites those on
       every click, but it sets the attribute in the same breath, so this holds for the tab the
       page opens on and for every tab opened after. */
    .memorial-tab-btn[aria-selected='true'],
    .dark .memorial-tab-btn[aria-selected='true'] {
        color: #ffffff;
        background-color: {{ $apBlue }};
        border-bottom: 3px solid {{ $apGold }};
        font-weight: 700;
    }

    /* The closed tabs. Muted, but not faded — they are 14px body text and still have to clear
       AA on white. `--ap-ink-soft` is 5.92:1; the obvious lighter greys #6B7A91 and #7C8AA0
       measure 4.37:1 and 3.50:1 and both fail. Measured before choosing. */
    .memorial-tab-btn[aria-selected='false'] {
        color: var(--ap-ink-soft);
        font-weight: 500;
        background-color: transparent;
    }

    .dark .memorial-tab-btn[aria-selected='true'] {
        background-color: transparent;
        color: {{ $apGold }};
    }

    /* The initial that stands in for someone with no photograph.

       A tinted disc beside every story, tribute and comment — so the more a family used the
       page, the more of it turned that tint. The brand blue with a white letter instead, which
       is this template's filled shape everywhere else, and which lets the names beside these
       read as the thing on the row that matters.

       `rounded-full` is untouched, so these stay circles. */
    .rounded-full.bg-brand-100,
    .dark .rounded-full.bg-brand-100 {
        background-color: {{ $apBlue }};
        color: #ffffff;
    }

    /* An icon inside one of those discs carries its own text-brand-* class, so it does not
       inherit the colour above and has to be told. */
    .rounded-full.bg-brand-100 svg {
        color: #ffffff;
    }

    /* "Invite the family and friends".

       The loudest use of the brand colour in the sidebar, on a page that already spends it on
       the years, the open tab and every initial disc. Blue on white with the dashed outline
       kept — the dash is what says this is something to add to rather than something already
       there.

       By id, which is what the button has and what beats the utility classes on it — including
       the hover, which would otherwise put the tint back on the way past. */
    #invite-share-btn,
    #invite-share-btn:hover {
        background-color: #ffffff;
        border-color: {{ $apBlue }};
        color: {{ $apBlue }};
    }

    #invite-share-btn:hover {
        border-color: {{ $apGold }};
    }

    /* The profile card parks below this template's header, not the platform's.

       That card is `md:sticky md:top-16 lg:top-[4.5rem]` — 64px and 72px, both measured against
       the platform's header. This one is taller at every width because it pins whole: the
       utility strip and the identity bar together, 117px from md and 124px from lg. Left alone
       the card slides under it.

       Each offset is the header's measured height plus a 1rem breath, so the card sits against
       it rather than touching. If the header's height changes, these change with it. */
    @media (min-width: 768px) {
        main aside > div { top: 8.25rem; }
    }

    @media (min-width: 1024px) {
        main aside > div { top: 8.75rem; }
    }

    /* The portrait, in the template's own two colours.

       The platform's mount is a plain white card, which is right for a page that has to suit
       every brand. Here it is the only frame on the page and it sits directly under the band,
       so it carries the blue and the gold the rest of the site is built from.

       Read from the reseller's palette rather than written out, so a funeral home that changes
       its brand takes this with it. Two stops only: a hand-placed colour in the middle would be
       a third nobody chose, and the gradient produces the transition anyway. */
    .memorial-hero__portrait {
        padding: 0.28rem;
        border-radius: 1.15rem;
        background: linear-gradient(180deg, {{ $apBlue }} 0%, {{ $apGold }} 100%);
        box-shadow: 0 18px 42px rgb(6 33 79 / 0.28);
    }

    .dark .memorial-hero__portrait {
        box-shadow: 0 18px 42px rgb(0 0 0 / 0.5);
    }

    .memorial-hero__portrait img {
        border: 0;
        border-radius: 0.95rem;
    }
</style>
