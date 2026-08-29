# Tribute card artwork

The one-tap tribute cards on the public memorial page look for three files here:

- `flower.png` — Leave a Flower
- `candle.png` — Light a Candle
- `prayer.png` — Send a Prayer

`flower-bouquet.png` is the violet bouquet `flower.png` used to be, kept because this
folder is not in version control and replacing it would otherwise be one-way. Nothing
reads it; only the three names above are looked for.

Square transparent PNGs, rendered at 56–64px, so ~256px assets are plenty.

Keep them small. The originals here arrived at ~1536px and 2.3MB each; trimming the
transparent margins and downscaling to 256px took all three from 7.0MB to 201KB with no
visible difference at render size. This page is regularly opened on mobile data from a
shared link, so a few megabytes of decorative artwork is not a rounding error.

Dropping a file in is all that is needed — no code change, no rebuild.
`resources/views/pages/memorials/partials/tribute-art.blade.php` checks for each file at
render time and uses it when present. When a file is absent it draws the motif inline as
SVG instead, so the cards always look finished rather than showing an empty square.

## Preparing new artwork

Source art usually arrives on a white background. Do not key it by testing each pixel for
whiteness — pale petals are near-white themselves and a per-pixel test punches holes
through them. Flood-fill inwards from the border instead, so only white actually connected
to the outside is removed, then take the remaining white back out of the anti-aliased ramp
so the subject does not keep a pale halo. Check the result on both a light and a dark
ground before committing it; a halo is invisible on white and obvious on black.

## How the current three were made

- **Candle** — a photoreal render (mauve pillar on a plum dish) that arrived on white.
  Keyed with a 7% flood-fill from the border, the alpha eroded by one pixel and softened
  so the anti-aliased white ramp does not survive as a halo, then trimmed, padded square
  and downscaled to 256px. No recolour: it already sits in the palette.
- **Praying hands** — same treatment, same source style, purple cuffs.
- **Flower** — a painted rose running coral at its heart to purple at the petal edges,
  with a few petals already falling. Keyed the same way; it arrived inside a screenshot
  frame, so the black edges were shaved before the flood-fill (a frame touching the border
  stops the fill reaching the white). No recolour.

Earlier versions were recoloured from other hues; if that ever comes back, the method that
worked was to carry each pixel's offset from the middle of the source hue range over as
the same offset around the target hue and keep its lightness — a flat hue rotation
flattens the shading into one block of colour.

## What a replacement has to be

The cards sit on a white surface at 56–64px, three abreast. That sets four hard
requirements, and source art almost never arrives meeting them:

- **Transparent background.** Anything else renders as a coloured tile on a white card.
- **Square, and cropped to the subject.** These are laid out in a square box with
  `object-contain`, so a tall portrait letterboxes — the flower ends up visibly smaller
  than the candle and the hands beside it. Crop to the part that reads at 64px, which for a
  single flower means the bloom, not the stem.
- **Nothing baked in that the page already animates.** Falling petals, glows and sparkles
  belong to the tap effect, not the artwork; a static copy of them sitting in the card
  fights the real ones the moment anyone taps it.
- **Small.** ~256px. See above.

## The colour is not only in the file

`flower.png` is a coral-to-purple rose, and two other places are set to match it. If the artwork changes
again, these change with it:

- `PETAL_COLOURS` in `resources/js/memorial-public.js` — the petals that rain down when the
  card is tapped, sampled from this file so they are made of the same colours. Sample a
  spread along the flower's own gradient rather than one average tint: this rose runs
  coral to purple, and a field of one flat pink reads as confetti instead of that flower
  coming apart. The artwork's palest tints are deliberately left out: each petal carries a
  white highlight overlay that takes a pale tint to near-white, which against the page's
  own pale ground is not a petal at all.
- The inline flower motif in `tribute-art.blade.php`, the fallback drawn when this file is
  missing.

The marker chips in the story composer and the medallion on a marked story both read this
same file through that partial, so they follow along on their own.

## A template can bring its own

`tribute-art.blade.php` looks in `public/images/themes/{template}/tributes/` before it looks
here, so a template ships whichever of the three it wants to replace and inherits the rest.
Everything above applies to those files too — transparent, square, cropped to the subject,
nothing baked in that the page animates, ~256px.

Dignified ships all three: a black-to-gold rose, a pillar candle in the same gradient, and a
pair of praying hands with black cuffs running to crimson and gold. It shipped two of them for a
while and inherited our hands, which is per-file inheritance doing its job — adding the third
took a file and no code.

The rose: It arrived
inside a screenshot frame with an Edit button and a download icon in the corners, and with four
petals already falling. The chrome was painted out and the black frame shaved before the
flood-fill — a frame touching the border stops the fill reaching the white — and the loose
petals were cropped away with the stem, because the page rains its own the moment anyone taps.

The palette moves with the artwork. A template that ships a flower sets `window.__tributePetalColours`
in its `memorial-theme.blade.php`; `PETAL_COLOURS` in `memorial-public.js` reads it and falls back
to the platform's coral-to-purple when there is none. Sample the same way — a spread along that
flower's own gradient, extremes left out at both ends. Dignified's runs `#5E1A19` through
`#E7AA52`: its true near-black takes the per-petal black overlay to a hole in the page, and its
palest gold takes the white overlay to something that is not a petal at all.

The pop that lands at the point of contact reads that same list. It used to be literal pink, so
a template with a black-and-gold rose got its own petals raining past a pink flower — two
flowers on screen, and only after somebody tapped. It now takes three tints out of the six: a
deep one and a bright one for the alternating petals, and the lightest for the particles,
because at 72px on a pale page a pop made of the dark end alone reads as a smudge.

`memorial-candle-scene.js` and `memorial-prayer-scene.js` — the full canvas scenes a candle and
a prayer open — take a sky the same way:
`window.__candleSceneSky`, five stops top to bottom, deepest at top and bottom with the horizon
band lightest, because that is where the candles crowd. Everything else in that scene is flame,
and flame suits any brand.

Two things to know before picking five. It has to stay dark: every flame, halo and star there
composites additively onto the ground. And it has to keep hue contrast against gold — dignified's
first attempt carried ember brown the whole way up, which is a lovely colour and cost the flames
their separation, so the field read as a muddy wash. The one that shipped holds the warmth to the
horizon band and leaves the rest near-black. Paint any candidate against the flames before
believing it; the difference does not show in the swatches.

One declaration, both scenes. The prayer scene's backdrop is a radial wash rather than a
vertical ramp, so it takes three of those five — horizon nearest the light, deepest at the edge
— rather than a set of its own. A visitor can open both within a minute of each other, and two
different nights on one memorial reads as two different sites.

The prayer scene also draws the card's own artwork, so a template that replaces `prayer.png`
gets its light rising out of its own hands with no further change.
