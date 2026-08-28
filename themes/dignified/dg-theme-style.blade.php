{{--
    Everything mechanical about how this template looks: its tokens, its type, its square
    corners, its gold and its crimson.

    A partial rather than a block inside layouts/visitor.blade.php, because a memorial page
    never passes through that layout — it extends layouts/fullscreen-layout and renders this
    template's header component with none of the variables that header is written against.
    The nav bar came out white on white on every memorial hosted by a reseller running this
    template. Two copies of these tokens would have fixed that and then drifted apart; one
    file included twice cannot.
--}}
<style>
    :root {
        --dg-gold: {{ \App\Helpers\BrandingHelper::accentColor() }};
        --dg-red: {{ \App\Helpers\BrandingHelper::primaryColor() }};
        --dg-ink: #1a1a1a;
        --dg-ink-soft: #2b2b2b;
        --dg-paper: {{ \App\Helpers\BrandingHelper::bgLight() }};
    }

    /* The headings are small-caps throughout, which is the single loudest thing about this
       template. Applied by class rather than to h1–h6, because one heading deliberately is
       not: the feedback band's "Help us serve you better." stays in sentence case, where the
       whole point is that it sounds like a person asking. */
    /* The theme token vocabulary. Everything mechanical about this template's appearance —
       its serif, its small caps, its square corners, its shouted buttons — is expressed here
       rather than in a fork of each widget view. Setting these is what let the `heading` and
       `paragraph` overrides be deleted outright. */
    :root {
        --t-heading-family: 'Cormorant Garamond', Georgia, serif;
        --t-heading-weight: 500;
        --t-heading-caps: small-caps;
        --t-heading-tracking: 0.015em;
        --t-heading-leading: 1.2;

        --t-body-family: 'Lato', system-ui, sans-serif;
        --t-body-leading: 1.7;

        --t-eyebrow-size: 0.625rem;
        --t-eyebrow-weight: 700;
        --t-eyebrow-tracking: 0.22em;

        --t-h1-size: 2rem;
        --t-h2-size: 1.625rem;
        --t-h3-size: 0.625rem;
        --t-h4-size: 1.1875rem;
        --t-h5-size: 1rem;
        --t-h6-size: 0.9375rem;

        /* Square throughout. The single change that most separates this template from Basic,
           and it is one number rather than a rounded-corner class removed from forty places. */
        --t-radius: 0;
        --t-radius-sm: 0;
        --t-btn-radius: 0;

        --t-btn-transform: uppercase;
        --t-btn-tracking: 0.16em;
        --t-btn-weight: 700;
        --t-btn-size: 0.6875rem;
        --t-btn-pad-x: 2rem;
        --t-btn-pad-y: 0.875rem;

        --t-pad-sm: 1.5rem;
        --t-pad-md: 2rem;
        --t-pad-lg: 3rem;

        --t-surface-page: var(--dg-paper);
        --t-surface-dark: var(--dg-ink);
        --t-accent: var(--dg-red);
        --t-accent-2: var(--dg-gold);
    }

    /* This template's flourishes, drawn on the shared hooks rather than in a forked view.
       These two rules are what let section-split's 92-line override be deleted. */

    /* Photographs on this template are desaturated, so the palette is carried entirely by the
       gold and the crimson rather than competing with whatever is in the picture. */
    :root { --t-image-filter: grayscale(1); }

    /* Two sentences, two lines. "Dignified care. Compassionate service." is set that way in
       the reference, and a reseller typing any two-sentence heading gets the same treatment
       without being asked to think about where the break goes. */
    .t-banner-line { display: block; }
    .t-banner-line + .t-banner-line::before { content: none; }

    /* The rule down the left of hero copy. Only where the view says a rule belongs.

       Gold, then crimson, then gold — not a solid gold line. Both of this template's colours
       appear on it, in that order and those proportions, which is what the reference does and
       what keeps the crimson present in a hero that is otherwise black and white. border-image
       rather than three elements: hard stops in the gradient give clean joins with no blend,
       and brown is the one colour this palette cannot produce. */
    .t-banner-ruled {
        border-inline-start-width: 4px;
        border-image-source: linear-gradient(
            to bottom,
            var(--dg-gold) 0 54%,
            var(--dg-red) 54% 84%,
            var(--dg-gold) 84% 100%
        );
        border-image-slice: 1;
    }

    /* The scrim over hero photographs.

       Written out rather than left to the view's `bg-black/50`, which Tailwind v4 resolves to
       an oklab colour that is not neutral — it computed to a blue-grey, and the whole hero
       came out tinted navy over a photograph that is warm sepia underneath. A plain rgba is
       both neutral and legible as an intent. */
    .t-banner-overlay { background: rgb(0 0 0 / 0.55); }

    /* The front page hero.

       Everything below is scoped to the *tall* banner, which on this template is exactly one
       section: the front page hero. The same view also renders every inner-page title band and
       the feedback bar, and an earlier version of these rules moved those too.

       The composition is the reference's: the photograph's lily fills the left, and the copy
       sits in the darker right half rather than on top of the flower. Below lg the picture is
       too narrow to divide, so the copy goes back to full width over the scrim. */
    /* Centring lives on the section, and only on the section. Making the inner wrapper a flex
       container as well turned the copy into a shrink-to-fit flex item, so its width came from
       its own longest line — which landed one pixel under what that line needed at 1600px and
       silently wrapped the heading to three. A block-level box sized by max-width cannot do
       that to itself. */
    /* Grid rather than flex, for the vertical centring the extra height needs.
       A flex item is shrink-to-fit, so the copy's width came from its own longest line and
       landed one pixel under what that line needed at 1600px — the heading then wrapped to
       three lines, on wide screens only. Grid items stretch, so the copy is sized by its
       max-width like any block and cannot argue with itself. */
    [data-banner-height='tall'] {
        min-height: 30rem;
        display: grid;
        align-content: center;
    }

    @media (min-width: 1024px) {
        /* Wide enough that "Compassionate service." stays on one line at this size — the
           padding of the ruled block eats into it, so the box has to clear the longest line
           plus 2rem or the heading silently wraps to three. */
        [data-banner-height='tall'] .t-banner-copy {
            margin-inline-start: 45%;
            /* Must clear the longest heading line at the clamp's *ceiling*, not at the size it
               happens to be on the machine this was tuned on. Sized to 38rem it fitted at
               1440 and silently wrapped to three lines at 1600 and above, where the font stops
               growing but the box did not. */
            max-width: 40rem;
        }
        /* Bigger than --t-h1, and only here. A hero heading carries the page on its own; the
           same size on an inner-page band would shout over the content under it. */
        /* The ceiling is what matters here, and it is set by arithmetic rather than taste: the
           copy column is what a 45% start leaves of a max-w-6xl container (598px), less the
           4px rule and 2rem of padding — 562px. "Compassionate service." has to fit that at
           the largest size the clamp can reach, or it wraps to three lines on wide screens
           only. 3.5rem lands at 522px, which is also within 1% of the reference. */
        [data-banner-height='tall'] .t-heading { font-size: clamp(2.5rem, 3.9vw, 3.5rem); }
        /* The hero's standing copy is a size up from body text, and narrow enough that the
           two sentences break where the reference breaks them — after "and". Both numbers are
           measured rather than guessed: at a 1400px render the reference's first line runs
           294px, which is this string at about 22px. */
        [data-banner-height='tall'] .t-body {
            font-size: 1.375rem;
            max-width: 23rem;
        }
    }

    /* The enquiry form on the feedback band: square, translucent over the photograph, with a
       crimson submit. */
    .t-banner-form input {
        border: 1px solid rgb(255 255 255 / 0.25);
        background: rgb(0 0 0 / 0.25);
        color: #fff;
        border-radius: 0;
    }
    .t-banner-form input::placeholder { color: rgb(255 255 255 / 0.55); }
    .t-banner-form input:focus { border-color: var(--dg-gold); outline: none; }
    .t-banner-form button {
        background: var(--dg-red);
        color: #fff;
        border-radius: 0;
        text-transform: uppercase;
        letter-spacing: 0.16em;
        font-weight: 700;
        font-size: 0.6875rem;
    }

    /* A short gold rule before every section label. */
    .t-eyebrow::before {
        content: '';
        display: block;
        width: 2.5rem;
        height: 1px;
        flex: none;
        background: var(--dg-gold);
    }

    /* Two thin rectangles the size of the picture, one nudged up-left in gold and one
       down-right in crimson, with the photograph on top. Only an L of each is ever visible,
       which is what makes it read as a frame and not as two stray boxes.

       Two elements rather than one gradient border, because a gradient blends gold into
       crimson through brown across every edge — the one colour this palette cannot afford. */
    .t-figure::before,
    .t-figure::after {
        content: '';
        position: absolute;
        pointer-events: none;
        border: 1px solid;
    }

    /* The photograph has to sit above *both* rectangles for the L to be all that shows.
       Without this the crimson one painted on top of it — ::after follows the <img> in DOM
       order, and positioned siblings with no z-index paint in that order — so its top and
       left edges were drawn straight across the picture while the gold one behaved. It read
       as a printing error rather than as a frame, and only on one of the two. */
    .t-figure > img {
        z-index: 1;
    }

    .t-figure::before {
        inset: -0.875rem 0.875rem 0.875rem -0.875rem;
        border-color: var(--dg-gold);
    }

    .t-figure::after {
        inset: 0.875rem -0.875rem -0.875rem 0.875rem;
        border-color: var(--dg-red);
    }

    .dg-caps {
        font-variant-caps: small-caps;
        letter-spacing: 0.015em;
    }

    /* Gold above, crimson below, meeting without a blend. Used on the rules that bracket the
       services grid and around the feedback form. A blended gradient muddies to brown in the
       middle third, which is the one colour this palette must not produce. */
    .dg-rule {
        background: linear-gradient(to right, var(--dg-gold) 0%, var(--dg-gold) 38%, var(--dg-red) 72%, var(--dg-red) 100%);
    }

    /* A one-pixel rule around the feedback form, gold on the left and crimson on the right.
       border-image is what keeps the inside transparent, so the photograph behind it stays
       visible — a padded gradient wrapper would fill the middle. */
    .dg-frame {
        border: 2px solid transparent;
        border-image: linear-gradient(to right, var(--dg-gold), var(--dg-red)) 1;
    }

    /* Body copy is Lato at a size that survives being read by someone who is upset. */
    .dg-body { line-height: 1.75; }
</style>
