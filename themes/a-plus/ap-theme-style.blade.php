{{--
    Everything mechanical about how this template looks: its tokens, its type, its square navy
    buttons, its pale blue bands and its one amber rule.

    A partial rather than a block inside layouts/visitor.blade.php, because a memorial page
    never passes through that layout — it extends layouts/fullscreen-layout and renders this
    template's header component with none of the variables that header is written against.
    Dignified learned this the expensive way: its nav bar came out white on white on every
    memorial hosted by a reseller running it. Two copies of these tokens would have fixed that
    and then drifted apart; one file included twice cannot.

    ------------------------------------------------------------------
    Rewritten 2026-09-02 to the client's final mockup. Navy is no longer a ground.

    The template shipped on 2026-08-31 was navy-dominant: a navy hero over a drained
    photograph, a filled-blue lead card, a navy statistics plate, gold pill buttons, a navy
    footer. The mockup inverts all of it. Navy became ink — headings, buttons, the closing bar
    — and the grounds became white and a pale blue sampled from the client's own photographs.
    Gold stopped being a fill and became one short amber rule under a centred heading.

    Almost all of that is these tokens. The platform's widget views read `--t-*` and follow, so
    a change of this size still edits four blades and this file rather than forking the widget
    layer. Where a fork does exist (section-banner, section-grid) it is because the *structure*
    differs, never because a colour does — if a change can be made here, it belongs here.

    The colours are still read from BrandingHelper rather than written out, so a funeral home
    that changes its brand on the Appearance page moves the whole template with it. theme.json
    supplies the defaults.
--}}
<style>
    :root {
        /* The workhorse. Headings, buttons, icons, the footer's closing bar — in this design
           they are all one colour, and it is this one. Softer and deeper than the bright blue
           sampled from the logo, which now appears only in the logo itself: at the weight this
           design uses navy (every heading, every button) #0033A0 vibrates against a pale blue
           ground, and the mockup's answer was to desaturate it rather than to use less of it. */
        --ap-blue: {{ \App\Helpers\BrandingHelper::primaryColor() }};

        /* The rule under a centred heading, and nothing else. A muted ochre rather than the
           logo's bright yellow: at 2px on a white ground #FECB01 disappears, and at any weight
           that makes it visible it becomes the loudest thing in a very quiet page. */
        --ap-gold: {{ \App\Helpers\BrandingHelper::accentColor() }};

        /* Deeper than the brand navy, for the two places that still carry white type: the
           footer's closing bar and the memorial page's furniture. Kept as its own token rather
           than a dimmed copy of --ap-blue so those surfaces do not drift when a reseller
           rebrands to a light or mid-tone primary. */
        --ap-navy: #16305A;

        /* The pale blue band behind the hero and the closing feature section.

           Sampled off the mockup itself (#E4ECF3 in the "We Are Here For You" band), not off
           the supplied photograph. Those disagree, and the mockup wins: it lays a blue wash
           over the whole hero — the picture included — so the band is not trying to match the
           photograph's own background at all. The wash is `.ap-bleed-fade` below. */
        --ap-sky: #E4ECF3;

        --ap-ink: #1B3A6B;
        /* Body copy, and the tightest contrast pairing in the design. Measured, not judged:
           at the #5A6B85 this started as it came to 4.31:1 against --ap-sky, which fails AA for
           normal text — and the hero standfirst is exactly that, 15px on the pale band. This
           clears it at 4.71:1 there and 5.92:1 on white. Darkening it any further starts to
           read as a second heading colour rather than as body text. */
        --ap-ink-soft: #55657E;

        /* Body copy on the hero, which is a darker ground than anywhere else.

           The blue wash over the opening takes the band to about #D8E0E7 where the
           standfirst sits, and --ap-ink-soft on that is 4.43:1 — under AA, again, and
           again invisibly. Measured off the rendered PNG rather than off the token,
           because the token is not what the text is actually sitting on.

           This is 5.50:1 there, and it happens to be right for the design too: the
           mockup's hero copy is visibly heavier than the column copy further down. */
        --ap-ink-strong: #48566E;
        --ap-paper: {{ \App\Helpers\BrandingHelper::bgLight() }};

        /* The alternating section ground — and in this design it is very nearly not one.

           Measured off the mockup, "Our Services" is #FDFDFB and "Why Choose A-Plus" is
           #FDFDFC: the same near-white, a shade warm. There is no blue-tinted band between
           them. The earlier `color-mix(--ap-blue 3%, white)` invented a cool tint the design
           does not have, and put a visible seam between two sections meant to read as one
           sheet of paper. Kept as a token rather than collapsed into white so the footer and
           the grids can still be told apart from a card if one is ever put on them. */
        --ap-mist: #FDFDFB;

        /* The hairline. This design has no card borders and no shadows — the only thing
           separating one column from the next is this, so it is a token rather than a value
           repeated in three views. */
        --ap-line: color-mix(in srgb, var(--ap-blue) 14%, #ffffff);
    }

    /* ------------------------------------------------------------------
       The theme token vocabulary.

       Everything the platform's widget views ask a template to decide. Setting these is what
       lets this template ship four blades instead of a fork of every section — the same
       economy the base token block in resources/css/app.css was written for.
       ------------------------------------------------------------------ */
    :root {
        /* A serif, and the single biggest change in the redesign. Montserrat made the template
           read as a service business; the mockup wanted a funeral home. Playfair carries that
           at heading sizes and nowhere else — the body, the navigation, the buttons and the
           column titles all stay on Open Sans, because a serif at 13px on a pale ground is
           where this kind of design usually falls apart. */
        --t-heading-family: 'Playfair Display', Georgia, 'Times New Roman', serif;
        --t-heading-weight: 700;
        --t-heading-caps: normal;

        /* Zero, not negative. Montserrat wanted tightening; a high-contrast serif does not, and
           at hero size the tightened version collides the "W" and the "e" of "We Care". */
        --t-heading-tracking: 0;
        --t-heading-leading: 1.2;

        --t-body-family: 'Open Sans', system-ui, sans-serif;
        --t-body-leading: 1.75;

        /* The mockup has no eyebrows at all — a centred serif heading with a rule under it does
           the work the small blue caps used to. These are kept sane rather than removed because
           a reseller can still type one into any section, and it should not arrive unstyled. */
        --t-eyebrow-size: 0.75rem;
        --t-eyebrow-weight: 700;
        --t-eyebrow-transform: uppercase;
        --t-eyebrow-tracking: 0.18em;

        --t-h1-size: 2.5rem;
        --t-h2-size: 1.875rem;
        --t-h3-size: 0.75rem;
        --t-h4-size: 1.125rem;
        --t-h5-size: 1rem;
        --t-h6-size: 0.9375rem;

        --t-radius: 0.5rem;
        --t-radius-sm: 0.375rem;

        /* Square, near enough. The pill was the loudest thing about the old template and the
           first thing the mockup threw away: a small radius on a solid navy rectangle reads as
           an institution rather than as a product, which is the whole distance between the two
           designs. Everything else about a button follows from this one value. */
        --t-btn-radius: 0.25rem;
        --t-btn-transform: none;
        --t-btn-tracking: 0.01em;
        --t-btn-weight: 600;
        --t-btn-size: 0.875rem;
        --t-btn-pad-x: 2.25rem;
        --t-btn-pad-y: 1rem;

        /* More air than the old template carried. With the cards gone there is no border
           holding a section together, so the space around it is what separates one idea from
           the next — under-padding this design makes it read as a list rather than as a page.

           These are the phone values, and the desktop ones are further down. The numbers the
           mockup gives are measured off a 1440px page: 104px above and below a section is a
           tenth of that screen and two fifths of a phone's, which on a stack of six sections
           was 600px of scrolling through nothing. The ratio between the three is kept, so the
           rhythm is the same page seen closer up rather than a different one. */
        --t-pad-sm: 2.25rem;
        --t-pad-md: 3rem;
        --t-pad-lg: 4rem;

        --t-surface-page: var(--ap-paper);
        --t-surface-muted: var(--ap-mist);
        --t-surface-dark: var(--ap-navy);
        --t-accent: var(--ap-blue);
        --t-accent-2: var(--ap-gold);
        --t-border: var(--ap-line);
    }

    /* A tablet is neither: a phone's padding leaves a 768px page looking cramped, and the
       desktop's leaves it looking empty. Halfway, and it stops being a decision anyone has to
       revisit at every new section. */
    @media (min-width: 640px) {
        :root {
            --t-pad-sm: 2.5rem;
            --t-pad-md: 3.5rem;
            --t-pad-lg: 5rem;
        }
    }

    @media (min-width: 1024px) {
        :root {
            --t-h1-size: 4.375rem;
            --t-h2-size: 2.25rem;

            /* The mockup's own numbers, restored at the width they were measured at. */
            --t-pad-sm: 3rem;
            --t-pad-md: 4rem;
            --t-pad-lg: 6.5rem;
        }
    }

    /* ------------------------------------------------------------------
       This template's flourishes, drawn on the shared hooks rather than in forked views.
       ------------------------------------------------------------------ */

    /* No filter. The old template drained every banner photograph to grayscale so the navy
       scrim could carry the colour; there is no scrim any more, and both supplied photographs
       are already almost monochrome — a candle on pale blue, and a blue-grey mountain range.
       Draining them a second time turns them grey. Stated rather than deleted, because the
       platform's base stylesheet sets a filter that would otherwise apply. */
    :root { --t-image-filter: none; }

    /* The amber rule under a centred section heading.

       Two elements carry it — a heading in a forked view where the markup is ours, and this
       hook for the platform's own views — so it is written once as a class and used by both.
       Short and thin on purpose: it is a full stop under the heading, not a divider. */
    .ap-rule {
        display: block;
        width: 3.25rem;
        height: 2px;
        background: var(--ap-gold);
    }

    .ap-rule-center { margin-inline: auto; }

    /* Hairlines between columns.

       This design has no cards. A row of four things is held together by three 1px lines and
       nothing else, which means the line has to survive wrapping: at two columns the divider
       belongs between the pair and not down the left edge of the row, and at one column it
       should not be there at all. Done with a border on the element rather than `divide-x`,
       which cannot express "except at the start of a row" at three different widths. */
    .ap-col { border-inline-start: 1px solid transparent; }

    /* Two columns is now the layout from the narrowest phone up to `lg`, so the "except at the
       start of a row" marker for a pair applies at every width below it — it used to start at
       640px because below that the grid was a single stack with no divider to draw. */
    .ap-col:not(.ap-col-row-start-sm) { border-inline-start-color: var(--ap-line); }

    @media (min-width: 1024px) {
        .ap-col { border-inline-start-color: var(--ap-line); }
        .ap-col-row-start-lg { border-inline-start-color: transparent; }
    }

    /* The circular icon badge. A pale blue disc with a navy line mark in it, repeated in the
       services row and the reasons row, so it is one rule rather than two copies of six
       utility classes. */
    .ap-badge {
        display: flex;
        align-items: center;
        justify-content: center;

        /* 92px. Measured, not guessed: the disc is 56px across in the mockup scaled to 854,
           which is 94px at 1440. The first pass had it at 3.5rem and the row read as a compact
           feature list rather than as the mockup's open, well-spaced services. */
        width: 5.75rem;
        height: 5.75rem;
        border-radius: 999px;
        background: var(--ap-sky);
        color: var(--ap-blue);
    }

    /* Both marks come down a size on a phone.

       92px is a fifteenth of the mockup's width and a quarter of a phone's. At that size the
       disc is the first thing on the screen and the service it belongs to is the second — and
       six of them stacked put 550px of circle into a page that is mostly circle already. The
       icon inside comes down with the disc so the ring around it stays the width the design
       drew it at. */
    @media (max-width: 639px) {
        .ap-badge { width: 4.25rem; height: 4.25rem; }
        .ap-badge svg { width: 2rem; height: 2rem; }

        .ap-mark { height: 4.25rem; }
        .ap-mark svg { width: 2.25rem; height: 2.25rem; }
    }

    /* The title over a column: the heading serif, at label size.

       This was briefly set to the body face on the assumption that a high-contrast serif goes
       spindly at 15px. Reading the mockup at full size says otherwise — "Funeral Services",
       "Compassionate Care" and the rest are unmistakably Playfair, and the serif is most of
       what stops the row reading like a feature table. Stated explicitly rather than left to
       the global `h1..h6` rule AppearanceHelper emits, so it is a decision on the record and
       not an inheritance that happens to be right. */
    .ap-col-title {
        font-family: var(--t-heading-family);
        font-weight: 700;
        letter-spacing: var(--t-heading-tracking);
    }

    /* An icon with no disc behind it.

       The mockup draws the services with a pale disc and the reasons without one — a tick in a
       circle is already a self-contained mark and a disc behind it makes two concentric
       circles. Same box as `.ap-badge` so the two rows sit on the same baseline grid. */
    .ap-mark {
        display: flex;
        align-items: center;
        justify-content: center;

        /* The same box as the disc, so the services row and the reasons row share a baseline
           grid even though only one of them draws a circle. */
        height: 5.75rem;
        color: var(--ap-blue);
    }

    /* The pricing tick.

       `partials.pricing.plan-bullets` and `partials.pricing.comparison-rows` colour their tick
       `text-green-500`. Those partials carry the plan limits and the unlimited-sentinel
       formatting, so they are the last thing this template should fork over a colour — but
       green is not in this palette and three green columns on a navy-and-amber page look like
       a different site. Scoped to a wrapper the theme puts around the includes, so it recolours
       them here and nowhere else. */
    .ap-pricing .text-green-500 { color: var(--ap-blue); }

    /* Photographs beside text.

       No frame and no shadow. The old template hung a deep drop shadow under every figure;
       this design's pictures bleed out of the band they sit in, and a shadow on a picture with
       no edge draws an edge back on. */
    .t-figure > img { box-shadow: none; }

    /* The enquiry form on a feedback band: white fields on the pale ground, navy submit. */
    .t-banner-form input {
        border: 1px solid var(--ap-line);
        background: #fff;
        color: var(--ap-ink);
        border-radius: var(--t-btn-radius);
        padding-inline: 1rem;
    }

    .t-banner-form input::placeholder { color: var(--ap-ink-soft); }

    .t-banner-form input:focus {
        border-color: var(--ap-blue);
        outline: 2px solid color-mix(in srgb, var(--ap-blue) 30%, transparent);
        outline-offset: 1px;
    }

    .t-banner-form button {
        background: var(--ap-blue);
        color: #fff;
        border-radius: var(--t-btn-radius);
        font-weight: 600;
        font-size: 0.8125rem;
    }

    .t-map-frame {
        border: 1px solid var(--ap-line);
        border-radius: var(--t-radius);
    }

    /* ------------------------------------------------------------------
       The header follows the page down.

       Asserted by ThemeConformanceTest rather than left to taste: on a long services page or a
       memorial, a header that scrolls away makes the way back a scroll to the top. The test
       accepts either a `sticky` utility class or a rule like this one; this template keeps the
       rule because the shadow belongs with it.

       Lighter than it was. With the blue utility strip gone the header is a single white bar
       against a pale blue hero, and the old 20px shadow drew a grey band across the top of the
       photograph. This is enough to separate the two surfaces and no more.
       ------------------------------------------------------------------ */
    .ap-header {
        position: sticky;
        top: 0;
        z-index: 40;
        box-shadow: 0 1px 0 0 var(--ap-line);
    }

    /* The active item in the navigation. The mockup marks it by weight and colour alone — no
       underline, no block, nothing that adds a shape to a bar whose job is to be quiet. */
    .ap-nav-link[aria-current='page'] {
        color: var(--ap-blue);
        font-weight: 700;
    }

    /* ------------------------------------------------------------------
       The hero, and the closing feature band.

       One idea used twice: a pale blue band with the copy on the left and a photograph
       bleeding out of the right-hand side. The band and the picture are the same colour where
       they meet, so there is no edge — which only works because the fade below is wide enough
       to cover the difference between --ap-sky and whatever the photograph actually starts at.
       ------------------------------------------------------------------ */
    [data-banner-height='tall'] { min-height: 30rem; }

    /* The hero copy is narrower than the width prop allows.

       The mockup sets the opening line over two lines — "In Need" above "We Care" — and there
       is no line-break prop to say so with. Capping the column is what makes the wrap happen,
       and it is the honest version of the same intent: the copy is a narrow left column beside
       a photograph, not a paragraph that happens to break. It caps the body copy with it,
       which is what the mockup does too. */
    [data-banner-height='tall'] .t-banner-copy { max-width: 27rem; }

    /* One line per span, on the two bands that carry a composed heading. `.t-banner-line`
       is `display: inline` in the base stylesheet precisely so a template can do this;
       everywhere else the heading should still wrap on its own. */
    [data-banner-height='tall'] .t-banner-line,
    [data-banner-height='band'] .t-banner-line { display: block; }

    [data-banner-height='tall'] .t-banner-line + .t-banner-line::before,
    [data-banner-height='band'] .t-banner-line + .t-banner-line::before { content: none; }

    [data-banner-height='tall'],
    [data-banner-height='band'] {
        display: grid;
        align-content: center;
    }

    /* The fade that joins the band to the photograph.

       Opaque across the copy, gone by the time it reaches the subject. Percentages rather than
       a hard stop because the two supplied pictures put their subject at different distances
       from the left edge, and a stop tuned to the candle cuts into the mountains. */
    .ap-bleed-fade {
        background: linear-gradient(
            90deg,
            var(--ap-sky) 0%,
            var(--ap-sky) 38%,
            color-mix(in srgb, var(--ap-sky) 55%, transparent) 58%,
            transparent 78%
        );
    }

    /* The hero's blue wash.

       A second layer over the first, and over the photograph with it. The mockup's hero is not
       a pale band with a picture dropped into it — the whole opening, picture included, sits
       under a blue tint that is deepest at the top and gone by two thirds down. Measured: the
       band reads #AEBDD5 at the top and #E2E8F1 at the foot, and the photograph is tinted the
       same way rather than left at its own colour.

       Hero only. On the closing feature band the mockup leaves the picture alone, and washing
       that one too turns a light mountain range into a grey one. */
    [data-banner-height='tall'] .ap-bleed-fade {
        background:
            linear-gradient(
                180deg,
                rgb(126 147 181 / 0.46) 0%,
                rgb(126 147 181 / 0.20) 40%,
                rgb(126 147 181 / 0) 72%
            ),
            linear-gradient(
                90deg,
                var(--ap-sky) 0%,
                var(--ap-sky) 34%,
                color-mix(in srgb, var(--ap-sky) 55%, transparent) 56%,
                transparent 76%
            );
    }

    /* Under 1024px the photograph stops being a bleed and becomes a band under the copy.

       That is what this block always claimed to do. What it actually did was `display: none`,
       and the picture was simply gone: every phone visitor to the front page got an empty pale
       band with a heading and a button in it, and the closing section lost its landscape the
       same way. On a funeral home's site the candle *is* the hero.

       The reasoning that put the rule here still holds — at phone width there is no room for a
       picture beside two lines of serif heading, and fading one out behind the text turns the
       heading grey on grey. So the composition rotates rather than collapsing: the copy keeps
       the pale band, and the photograph runs full width beneath it, flush to the foot of the
       section, its top edge faded into the band exactly as the desktop version fades its left
       edge into the copy. Same two elements, same idea, turned ninety degrees.

       `.ap-bleed` — set by the view on the sections that carry a bleed — rather than the height
       attribute, because a centred banner with no picture must keep its bottom padding. */
    @media (max-width: 1023px) {
        .ap-bleed {
            display: flex;
            flex-direction: column;

            /* The picture takes the foot of the section, so the section's own bottom padding
               would put a band of sky under the photograph and undo the bleed. */
            padding-bottom: 0;

            /* `tall` asks for 30rem of hero. With the picture stacked under the copy the
               section is well past that on its own, and the min-height only pushes the two
               apart. */
            min-height: 0;
        }

        .ap-bleed-image {
            position: static;
            /* After the copy, which comes second in the markup because on desktop this
               picture is a background. */
            order: 2;
            width: 100%;

            /* Fluid, not a fixed plate.

               A picture pinned to 16rem is the same 256px on a 360px phone and on a 430px one,
               so it reads as a stamp on the small screen and as a stripe on the large. The
               middle term ties it to the width it sits on, and the two ends stop it collapsing
               on the narrowest phone or running away on a tablet — where the desktop rules take
               over a breakpoint later anyway. */
            height: clamp(10rem, 48vw, 15rem);

            /* The air between the button and the picture is the fade, and nothing else.

               It used to be paid for twice: a margin put ~48px of sky under the button and then
               the mask held the first fifth of the picture transparent on top of it, which is
               why it read as a hole rather than as a join. The margin is gone. The picture's
               box now begins where the copy ends, and the only gap is the part of the picture
               that has not arrived yet — space doing two jobs instead of space doing none. */
            margin-top: 0;
            object-position: center 30%;

            -webkit-mask-image: linear-gradient(180deg, transparent 0%, #000 12%);
            mask-image: linear-gradient(180deg, transparent 0%, #000 12%);
        }

        /* The hero carries the composition, so it gets the taller plate — and a subject that
           runs from the flame at the top of the frame to the flowers at the bottom, which is
           why this one stays near the photograph's own proportions instead of cropping to a
           band. Cropping it is what puts the flame under the fade. */
        [data-banner-height='tall'] .ap-bleed-image { height: clamp(12rem, 60vw, 18rem); }

        /* The hero opens tighter than the sections below it. 4rem of sky above a heading is
           right when there is a page of it underneath; at the top of a phone screen it is the
           first thing a visitor is given and it is nothing. */
        .ap-bleed[data-banner-height='tall'] { padding-top: var(--t-pad-md); }

        /* The left-to-right gradient has nothing left to join, and `absolute inset-0` over a
           picture that is now in the flow would simply cover it. The section's own
           background is the band. */
        .ap-bleed-fade { display: none; }
    }

    @media (max-width: 1023px) and (prefers-reduced-transparency: reduce) {
        /* A mask is a transparency effect; without it the picture keeps a hard top edge, which
           is the honest fallback rather than a missing photograph. */
        .ap-bleed-image { -webkit-mask-image: none; mask-image: none; }
    }
</style>
