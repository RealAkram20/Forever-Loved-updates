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

## Recolouring, and why each one needed a different method

All three are violet, and each needed a different way of selecting what to move — worth
knowing before the next one:

- **Flower** — replaced outright, no recolour.
- **Candle** — the wax, the holder *and* the flame are all warm, so a hue test takes the
  flame with everything else. The flame is protected by *where* it is instead: a
  soft-edged ellipse over the wick, with the ramp on that edge doing the work of stopping
  the join reading as a cut-out pasted onto a purple candle.
- **Praying hands** — only the sleeves moved. Here hue alone was enough: the sleeves sit at
  195–225°, the skin and the light at 15–60°, and those do not overlap. No mask needed.

In every case the pixel's own distance from the middle of the source range is carried over
as the same offset around the new hue, and its lightness is kept. That is what preserves
the shading — a flat hue rotation flattens the subject into one block of colour.

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

`flower.png` is violet, and two other places are set to match it. If the artwork changes
again, these change with it:

- `PETAL_COLOURS` in `resources/js/memorial-public.js` — the petals that rain down when the
  card is tapped, sampled from this file so they are made of the same violets. The
  artwork's palest tints are deliberately left out: each petal carries a white highlight
  overlay that takes a pale tint to near-white.
- The inline flower motif in `tribute-art.blade.php`, the fallback drawn when this file is
  missing.

The marker chips in the story composer and the medallion on a marked story both read this
same file through that partial, so they follow along on their own.
