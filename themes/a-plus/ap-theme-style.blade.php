{{--
    Everything mechanical about how this template looks: its tokens, its type, its pill
    buttons, its blue and its yellow.

    A partial rather than a block inside layouts/visitor.blade.php, because a memorial page
    never passes through that layout — it extends layouts/fullscreen-layout and renders this
    template's header component with none of the variables that header is written against.
    Dignified learned this the expensive way: its nav bar came out white on white on every
    memorial hosted by a reseller running it. Two copies of these tokens would have fixed that
    and then drifted apart; one file included twice cannot.

    The colours are read from BrandingHelper rather than written out, so a funeral home that
    changes its brand on the Appearance page moves the whole template with it. theme.json
    supplies the defaults — the blue and the yellow sampled from A-Plus's own logo.
--}}
<style>
    :root {
        --ap-blue: {{ \App\Helpers\BrandingHelper::primaryColor() }};
        --ap-gold: {{ \App\Helpers\BrandingHelper::accentColor() }};

        /* The dark ground: hero scrim, statistics band, footer. Deeper than the brand blue
           rather than a dimmed copy of it — white type on the brand blue itself sits at about
           7:1, which passes, but the photograph underneath the hero eats most of that margin.
           This clears it with room to spare and still reads as the same colour family. */
        --ap-navy: #06214F;

        --ap-ink: #16233F;
        --ap-ink-soft: #4A5878;
        --ap-paper: {{ \App\Helpers\BrandingHelper::bgLight() }};

        /* The faint blue-white behind alternating sections. Mixed from the brand blue rather
           than declared, so it follows a reseller who rebrands instead of staying the one
           surface still tinted with somebody else's colour. */
        --ap-mist: color-mix(in srgb, var(--ap-blue) 4%, #ffffff);
    }

    /* ------------------------------------------------------------------
       The theme token vocabulary.

       Everything the platform's widget views ask a template to decide. Setting these is what
       lets this template ship four blades instead of a fork of every section — the same
       economy the base token block in resources/css/app.css was written for.
       ------------------------------------------------------------------ */
    :root {
        --t-heading-family: 'Montserrat', system-ui, sans-serif;
        --t-heading-weight: 700;
        --t-heading-caps: normal;
        --t-heading-tracking: -0.015em;
        --t-heading-leading: 1.18;

        --t-body-family: 'Open Sans', system-ui, sans-serif;
        --t-body-leading: 1.75;

        /* "OUR SERVICES", "WHY CHOOSE US" — small, wide and blue. No rule before it: the base
           hook draws nothing by default and this template leaves it that way on purpose.
           Dignified's gold dash is a flourish; this design's character is that it has none. */
        --t-eyebrow-size: 0.75rem;
        --t-eyebrow-weight: 700;
        --t-eyebrow-transform: uppercase;
        --t-eyebrow-tracking: 0.2em;

        --t-h1-size: 2.25rem;
        --t-h2-size: 1.875rem;
        --t-h3-size: 0.75rem;
        --t-h4-size: 1.25rem;
        --t-h5-size: 1.0625rem;
        --t-h6-size: 0.9375rem;

        /* Softly rounded, not square and not a capsule. Cards and photographs share one
           radius; buttons are the deliberate exception below. */
        --t-radius: 0.625rem;
        --t-radius-sm: 0.5rem;

        /* Full pills. The single loudest thing about this template, and the reason it reads as
           a service business rather than a funeral parlour — it is the shape of the "Call Us
           Now" button in the header, and every other button on the site follows it. */
        --t-btn-radius: 999px;
        --t-btn-transform: none;
        --t-btn-tracking: 0.01em;
        --t-btn-weight: 600;
        --t-btn-size: 0.8125rem;
        --t-btn-pad-x: 1.875rem;
        --t-btn-pad-y: 0.875rem;

        --t-pad-sm: 2.5rem;
        --t-pad-md: 4.5rem;
        --t-pad-lg: 6rem;

        --t-surface-page: var(--ap-paper);
        --t-surface-muted: var(--ap-mist);
        --t-surface-dark: var(--ap-navy);
        --t-accent: var(--ap-blue);
        --t-accent-2: var(--ap-gold);
        --t-border: color-mix(in srgb, var(--ap-blue) 14%, transparent);
    }

    @media (min-width: 1024px) {
        :root {
            --t-h1-size: 3rem;
            --t-h2-size: 2.375rem;
        }
    }

    /* ------------------------------------------------------------------
       This template's flourishes, drawn on the shared hooks rather than in forked views.
       ------------------------------------------------------------------ */

    /* Hero photographs are drained and let the navy scrim carry the colour, so the picture
       reads as one blue field with the type on it rather than as a photograph competing with
       the brand. Scoped to `--t-image-filter`, which the base stylesheet applies to banner
       backgrounds only — the portraits in split sections keep their own colour, which is the
       whole reason people are in them. */
    :root { --t-image-filter: grayscale(0.85) contrast(1.06); }

    /* The scrim. Written out rather than left to the view's `bg-black/50`, which Tailwind v4
       resolves through oklab to a colour that is not neutral — Dignified's hero came out
       tinted navy over a warm photograph before this was noticed. Here the tint is wanted, so
       it is stated rather than inherited by accident. */
    .t-banner-overlay { background: rgb(6 33 79 / 0.78); }

    /* The front page hero, and only it.

       Everything below is scoped to the *tall* banner, which on this template is one section
       per page: the opening. The same view also renders every inner-page title band, the
       feedback bar and any call to action, and a rule meant for the hero that reached those
       would shout over the content beneath them. */
    [data-banner-height='tall'] {
        min-height: 26rem;
        display: grid;
        align-content: center;
    }

    /* Darker where the words are. The photograph's subject sits centre-right in all three of
       the pictures this template ships, so the copy gets the side with the least in it and the
       gradient buys back the contrast that a flat wash spends evenly. */
    [data-banner-height='tall'] .t-banner-overlay {
        background: linear-gradient(
            100deg,
            rgb(6 33 79 / 0.93) 0%,
            rgb(6 33 79 / 0.82) 45%,
            rgb(6 33 79 / 0.58) 100%
        );
    }

    /* The rule under a hero heading.

       The base view offers a rule down the *side* of left-aligned banner copy, which is
       Dignified's idiom, not this one's. Here the same intent is a short gold bar underneath
       the heading, so the side border is zeroed and the padding the view adds with it —
       otherwise the copy stays indented against nothing.

       On the heading rather than the copy block, so it sits tight under the last line instead
       of below the paragraph and the buttons too. */
    .t-banner-ruled {
        border-inline-start-width: 0;
        padding-inline-start: 0;
    }

    [data-banner-height='tall'] .t-heading::after {
        content: '';
        display: block;
        width: 3.5rem;
        height: 3px;
        margin-top: 1.75rem;
        background: var(--ap-gold);
    }

    [data-banner-height='tall'] .t-body {
        max-width: 30rem;
    }

    /* Photographs beside text.

       No frame. Dignified brackets its pictures with two offset rectangles; this design does
       not decorate, and adding a flourish here because the hook exists would be the template
       wearing somebody else's character.

       No width cap either. The platform's view caps the figure at 365px, which is right for a
       portrait beside a serif column; this template's split view drops that cap so the picture
       fills its half of the grid, because it has to be wide enough to carry the call card that
       sits over its foot. A 27rem cap left the card narrower than its own phone number. */

    .t-figure > img {
        box-shadow: 0 18px 40px -18px rgb(6 33 79 / 0.45);
    }

    /* The enquiry form on a feedback band: white pills on the photograph, gold submit. */
    .t-banner-form input {
        border: 1px solid rgb(255 255 255 / 0.3);
        background: rgb(255 255 255 / 0.1);
        color: #fff;
        border-radius: 999px;
        padding-inline: 1.25rem;
    }

    .t-banner-form input::placeholder { color: rgb(255 255 255 / 0.6); }

    .t-banner-form input:focus {
        border-color: var(--ap-gold);
        background: rgb(255 255 255 / 0.16);
        outline: none;
    }

    .t-banner-form button {
        background: var(--ap-gold);
        color: var(--ap-navy);
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.8125rem;
    }

    .t-map-frame {
        border: 1px solid var(--t-border);
        box-shadow: 0 18px 40px -22px rgb(6 33 79 / 0.4);
    }

    /* ------------------------------------------------------------------
       The header follows the page down.

       Asserted by ThemeConformanceTest rather than left to taste: on a long services page or a
       memorial, a header that scrolls away makes the way back a scroll to the top. The test
       accepts either a `sticky` utility class or a rule like this one; this template needs the
       rule because it pins two bars of different heights together.
       ------------------------------------------------------------------ */
    .ap-header {
        position: sticky;
        top: 0;
        z-index: 40;
        box-shadow: 0 4px 20px -6px rgb(6 33 79 / 0.18);
    }

    /* The active item in the navigation: a gold underline sitting on the baseline of the bar,
       not a filled block. The bar is white and the type is dark, so a filled marker would be
       the heaviest thing in a header whose job is to be quiet. */
    .ap-nav-link { position: relative; }

    .ap-nav-link[aria-current='page']::after {
        content: '';
        position: absolute;
        inset-inline: 0.75rem;
        bottom: 0;
        height: 3px;
        background: var(--ap-gold);
    }

    /* A gold rule under a footer column heading — the one place this template repeats
       Dignified's habit of underlining a label, because four columns of white-on-navy text
       need something separating the heading from the list under it. */
    .ap-foot-heading::after {
        content: '';
        display: block;
        width: 2rem;
        height: 2px;
        margin-top: 0.625rem;
        background: var(--ap-gold);
    }
</style>
