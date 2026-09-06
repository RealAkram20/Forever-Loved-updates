# Worklog

Append-only record of work in this repository. Newest at the bottom.

Read it before touching anything. Add your entry **before** you write code.

## The rules

1. Claim your files before you edit them.
2. Re-read the tree before you write; `git status` from an hour ago is stale.
3. Never re-implement a shared module. Extend it.
4. Say what you did not build.

## Entries

### 2026-08-31 — A-Plus Funeral Management template

**Status:** complete
**Owns:** `themes/a-plus/**`, `themes/_source/a-plus/**`,
`public/images/themes/a-plus/**`
**Shares:** none. The theme layer let this be additive from start to finish —
no file outside those three directories was edited, which is the whole reason
the view-path design exists.

**What this is:** the third template (after `basic` and `dignified`), built to
a supplied mockup for A-Plus Funeral Management, Kampala. Corporate blue
(`#0033A0`) and yellow (`#FECB01`) sampled from the client's own logo, a white
identity bar over a blue utility strip, pill buttons, and a services grid whose
first card is a filled blue tile.

**Design decisions taken before the first line:**

- Slug is `a-plus`, not `A-Plus`. `ThemeRegistry::isValidSlug()` is
  `^[a-z0-9][a-z0-9\-]{0,48}$` and the value reaches a filesystem path.
  `themes/A-Plus/` stays as the supplied source art and is invisible to the
  registry, which globs `themes/*/theme.json`.
- Fonts are Montserrat (headings) and Open Sans (body). Both are in
  `config/google-fonts.php`; a family outside that list is dropped silently by
  `AppearanceHelper::fontLinks()` and the page renders in Outfit.
- Everything mechanical goes in `ap-theme-style.blade.php` as `--t-*` tokens.
  Dignified's own comments record that setting those tokens is what let two
  forked widget views be deleted; a fork here would be repeating a mistake this
  repo has already paid for and undone.

**What shipped:** `theme.json` (14 tokens, 8 seeded pages),
`ap-theme-style.blade.php` (the `--t-*` token block and the flourishes),
`layouts/visitor`, `components/home-header`, `components/visitor-footer`,
`pages/visitor/home`, `page-builder/widgets/section-grid` (the only fork, and
only because its card structure genuinely differs), `memorial-theme`,
`preview.webp`, and six webp photographs derived from the supplied art.

**Verified:**

- 137 theme tests pass, including all 11 `ThemeConformanceTest` cases for
  `a-plus` — standard pages, the header/footer contract, no link off a reseller
  site onto the platform, the pinned header, one `Powered by` in a new tab,
  every reseller-addable widget, the seeded documents opening in the builder.
- `themes:doctor` reports every template in step; baselines recorded.
- Rendered in a browser at 1440px: home, `/services`, and a memorial page.
- Rendered at a true 390px viewport: `scrollWidth` 375 against a 390 viewport
  and **zero** overflowing elements.

**Not verified:**

- Never rendered on a real reseller *host* or custom domain — only the
  `/r/{slug}` development fallback. The reseller feature routes on the Host
  header, so `RESELLER-PRODUCTION-CHECKLIST.md` still governs deployment.
- The contact section's map is an empty grey frame locally (no Maps key).
- No real device; no Safari or Firefox.
- The enquiry form on the feedback band was styled but never submitted.

**Three traps found by doing, each worth more than the code:**

1. **A case-mismatched theme directory swallows the theme silently.** Windows
   is case-insensitive, so `themes/a-plus/` created beside an existing
   `themes/A-Plus/` *is the same directory*, and its real name `A-Plus` fails
   `ThemeRegistry::isValidSlug()`. `themes:sync` then lists every template
   except the new one, with no error — the registry globs `themes/*/theme.json`
   and simply skips it. Renaming needs a temp name in between.
2. **`.t-btn` beats Tailwind's `hidden`.** The `--t-*` rules are plain CSS
   written after `@import 'tailwindcss'` in `app.css`, so they sit outside the
   utility layer. `class="t-btn hidden lg:inline-flex"` stays visible at every
   width. Put the visibility on a wrapper element.
3. **The memorial band's height and the hero frame's top padding are a matched
   pair** (9/11/13rem, each set twice in `app.css`). Raising the band without
   raising the padding drops the name onto the photograph. See the finding
   below — Dignified does exactly this today.

**Deliberately not built:**

- **The statistics band** from the reference ("24/7 Support · 30+ Years
  Experience · 1,000+ Families Served · Nationwide"). A template's
  `default_pages` become the starting content of *every* site that applies it,
  so shipping those numbers would put unverifiable trading claims on a funeral
  home's front page until somebody noticed and removed them. It is A-Plus's own
  claim to make, on their own site, in the page builder. The widget vocabulary
  already supports it: a `section_grid` at `columns: 4` on a dark background.
- **Breadcrumbs** under inner-page banners. There is no breadcrumb widget and
  inventing one is a platform change, not a template's.
- **The blue "Need Immediate Help?" card overlapping the why-choose-us
  photograph.** `section_split` has no slot for it, and adding one is a
  platform change. The same content is in the header CTA and the feedback band.
- **A `t-figure` frame.** Dignified brackets its photographs; this design does
  not decorate, and adding a flourish because the hook exists would be the
  template wearing someone else's character.

**For whoever is next:**

- `php artisan themes:sync` makes a template selectable;
  `php artisan themes:doctor --record` writes shadow baselines into
  `theme.json` and must run *after* the blades settle, or the conformance suite
  fails on unrecorded shadows. `npm run build` is required after adding a
  template — Tailwind scans `themes/**` via an `@source` line, and without a
  rebuild the blades generate no utilities and the page renders unstyled.
- Local preview: reseller 3 (`robel-bogisich`) is on A-Plus at
  `/Forever/r/robel-bogisich`, with demo contact details and a tagline. Its
  `resellers.primary_color` column was set to `null` deliberately — it held a
  factory-seeded `#8e61db`, and because `branding.primary_color` is a **column
  alias**, that column is layer 1 and beat the theme's blue. A theme looking
  "wrong" on a tenant is usually this, not the theme.
- Tests run on in-memory SQLite (forced in `phpunit.xml`), so the suite cannot
  touch `forever_love`. Confirmed while chasing a tenant whose `theme_id`
  changed mid-session — see the entry below, which explains it.

### 2026-08-31 — Not a bug: vandervort-west rendering as A-Plus

**Status:** resolved, no code changed

`/r/vandervort-west` was serving the A-Plus design although it is a Dignified
site. Restored by setting `resellers.theme_id` back to `2`; verified over three
consecutive requests (`--dg-` 65, `--ap-` 0, Cormorant present) and by
rendering the page.

**Cause: somebody applied the theme from Reseller → Themes.** Not the template,
and not an anonymous request. `resellers.theme_id` is written by exactly one
code path — `Reseller\ThemeController::apply()`, an authenticated POST.
`ThemePreview` only ever writes to the session, and neither an anonymous GET of
any page nor a bare `artisan` boot moves the column (both tested in isolation).

The page table dates it precisely, because `ThemePages::seed()` creates the
pages a template ships at the moment it is applied:

- `18:42:13` — `coffins-caskets`, `hearse-transport`, `pre-planning` and four
  others created. Those slugs exist only in A-Plus. A-Plus was applied.
- `19:08:07` — `documentation-advisory`, `burial-cremation`,
  `condolence-support` created. Those exist only in Dignified. Dignified was
  applied.
- Then A-Plus again, which added no rows because its pages already existed —
  which is why the last change left no trace except `updated_at`.

**The side effect worth knowing about:** applying a theme *adds* the pages that
template ships, and switching back does not remove them. That is deliberate —
`ThemePages` never deletes, because a page may hold an afternoon of somebody's
work. So `vandervort-west` now carries ten pages it did not have before: seven
from A-Plus and three from Dignified. They are published and will appear in its
page list. Left in place rather than cleaned up, because deleting a tenant's
pages is not a call to make without being asked.

**The lesson for previewing a new template:** use Preview, not Apply. Preview
is session-scoped, switches the markup as well as the palette, and leaves no
rows behind. Apply writes the column and seeds pages.

### 2026-08-31 — A-Plus, second pass: the services page against the mockup

**Status:** complete for what the widget vocabulary can express
**Owns:** `themes/a-plus/**`

Reworked the `services` page to the supplied reference. Three additions, all
inside the theme layer:

- **`page-builder/widgets/section-banner.blade.php`** — adds the breadcrumb the
  reference puts under every inner-page hero. Derived, not authored: there is no
  breadcrumb prop, and a hand-typed trail goes stale the moment a page is
  renamed. It reads the served page's own title, falls back to a tidied URL
  segment, and is suppressed on the four front-page route names and on `band`
  banners (where it would point at the page it is already on).
- **`section-grid`** — four columns on a dark ground now render as the
  reference's inset statistics strip: a navy plate over a drained photograph,
  gold icon, figure, label, hairlines between. Keyed on the two props a reseller
  already sets rather than a hidden style flag, so it is reachable from the page
  builder without a secret. Same arrangement Dignified already uses, where a
  dark grid becomes butted tiles on a florals plate.
- **`page-builder/widgets/section-split.blade.php`** — the photograph is now a
  full-width plate rather than a 365px portrait, and **a primary button whose
  URL is a `tel:` link is drawn as a card over the foot of that photograph**
  instead of as a pill under the text. That is the reference's most distinctive
  element. Keyed on something a reseller controls and can discover; any other
  link renders as an ordinary button, so no existing section changes. Below
  `sm` the card leaves the overlay and sits under the picture — inset into a
  340px photograph it put the phone number on two lines across a face.

The `.t-figure` width cap was removed with it: at 27rem the call card came out
narrower than its own phone number.

**Verified:** 137 theme tests still pass; `themes:doctor` in step after
re-recording (7 shadows now). Rendered at 1440px top to bottom.

**The stats figures are the client's claim, not the template's.** "30+ Years
Experience" and "1,000+ Families Served" now ship in `default_pages`, which
means any *other* reseller applying this theme inherits them until they edit.
That reverses the call recorded in the entry above, on the grounds that this
template is built for A-Plus to their own mockup — but it is a real edge and
whoever offers this theme to a second reseller must change those two figures.

**Still not matching the reference, and why:**

The "Why Choose Us" band is three columns in the mockup — photograph with the
call card, then heading plus two icon/title/text features, then Our Mission,
Our Promise and a testimonial. It renders here as a two-column split followed
by a two-column grid. Two things block an exact match, and both are **platform
changes rather than theme changes**:

1. `section_split` has no `items` prop, so the two features render as body
   paragraphs instead of icon/title/text entries. Adding an optional `items`
   list to `SectionSplitWidget` would fix it and is additive — documents
   without `items` render identically — but it touches the widget class, its
   validation, its field schema and the platform's own view.
2. Nothing in the vocabulary lays out three columns of *different* content
   types in one band, and there is no testimonial widget at all.

Faking either through a naming convention in `body` was rejected: a layout that
depends on a reseller punctuating a sentence a particular way breaks silently
the first time they edit it.

### 2026-08-31 — "Switching to A-Plus changes the colours but not the home page"

**Status:** data fixed for one tenant; the underlying behaviour is unchanged and
is a product decision, not a defect in the template

**What was reported:** applying A-Plus to `vandervort-west` restyled the site
but left the front page exactly as it was.

**Why.** Two rules meet, and both are working as written:

1. `ThemePages::seed()` skips any page where `hasLayout()` — *"applying a theme
   must never cost someone an afternoon."*
2. `PageController::home()` ranks the reseller's own `visitor-home` layout
   **above** the template's designed front page, and only falls through to the
   template when they have built nothing.

`vandervort-west`'s `visitor-home` was seeded by Dignified on 2026-07-30 and has
had a layout ever since, so every later theme switch restyled it without
replacing it. Its stored first heading was still *"Dignified care. Compassionate
service."* and its widget sequence was Dignified's five, not A-Plus's six.
`robel-bogisich` is the control: its home was empty when A-Plus was applied, so
it took A-Plus's document and reads *"Honouring Life With Dignity"*.

**What was done.** Backed the page up, cleared the layout, re-seeded:

    storage/app/backups/page-1-visitor-home-20260831-191955.json

Restoring is writing that file's `layout` back onto the row. Verified by
rendering: hero, services grid with the filled first tile, Why Choose Us,
Mission and Promise, feedback band, contact.

Only `visitor-home` was touched. `about` and `about-us` on that tenant also
predate the switch and are still their own; A-Plus ships no document for either,
so clearing them would leave them empty rather than redesigned.

**The gap worth fixing in the product.** The apply flow says nothing about this.
`Reseller\ThemeController::apply()` builds its message as
`"Your site is now using {name}." . ThemePages::summary($seeded)`, and
`summary()` returns `''` when nothing was seeded — so in exactly the case above
the reseller is told nothing at all. `summary()`'s own docblock says its job is
that *"a reseller who has already built their home page needs to know why the
new theme's front page is not the one in the screenshot"*; the code only
delivers the other half of that sentence. Until that is closed, this will be
rediscovered by every reseller who changes theme twice.

### 2026-08-31 — Applying a theme now says what it kept, and offers to replace it

**Status:** complete
**Owns:** nothing new
**Shares — claimed before editing, minimal diffs:**

- `app/Themes/ThemePages.php` — exact edit: two additive methods, `keptByOwner()`
  and `resetToTheme()`. Nothing existing changes signature or behaviour.
- `app/Http/Controllers/Reseller/ThemeController.php` — exact edit: `index()`
  passes the kept list; `apply()` appends a sentence when pages were kept; one
  new `resetPage()` action.
- `routes/web.php` — exact edit: one POST route beside `theme.apply`.
- `resources/views/pages/reseller/theme.blade.php` — exact edit: one panel,
  modelled on the `shadowedCount` panel already there.

**Why.** The reseller-facing half of the theme system already names the
confusing state for *colours* — `shadowedCount` exists precisely so "I applied
a theme and half of it did not take" is answered before it is asked. The same
state exists for *pages* and is silent, which is how the A-Plus home page
appeared not to be built at all. This closes it with the pattern the file
already uses rather than inventing a second one.

The "never overwrite work" rule is unchanged. Applying a theme still keeps
every page the reseller has built. The difference is that they are now told,
and can choose.

**The subtlety that made the first attempt wrong.** "Kept" cannot mean "has a
layout" — on a fresh site, seeding *creates* those layouts, so the first version
offered to replace every page with the design it had just installed. It has to
mean "has a layout that is not the template's own document". The comparison runs
the manifest through `PageLayoutService` first, so defaults and ordering are
filled in on both sides, and strips widget `id`s, which are minted fresh on
every validation and would otherwise make two identical documents never match.
Caught by rendering the real tenants, not by the tests.

**Verified:** 144 theme tests pass, five of them new and all covering the path
that had none — applying a theme to a site that already has pages:

- the reseller's home page survives the apply, word for word
- the success message says it was kept
- the theme screen offers the swap for that page
- the swap replaces it with the template's document
- a slug the template does not ship is refused
- a freshly seeded page is *not* offered for swapping

**Not verified:** the new panel and button were exercised through the test
suite's rendering, not clicked in a browser.

### 2026-08-31 — Gallery screenshots for Basic and Dignified

**Status:** complete
**Owns:** `themes/basic/preview.webp`, `themes/dignified/preview.webp`
**Shares:** `themes/basic/theme.json`, `themes/dignified/theme.json` — exact
edit: one `"screenshot": "preview.webp"` key each.

A-Plus shipped a real screenshot and the other two did not, so the theme gallery
showed one photograph beside two grey wireframes and read as though the older
templates were unfinished. Both now ship one, at the same 1200x900 as A-Plus so
the three cards match.

Captured from sites already running each design, so no tenant was switched and
no pages were seeded:

- **Basic** — the platform's own front page. Basic *is* `resources/views`, so
  the platform site is the design, exactly.
- **Dignified** — `ferry-heller`, which already runs it.

**The one thing worth knowing.** `ferry-heller` overrides the theme's crimson
with its own amber (`primary_color` = `#f99f0c`, a column alias and therefore
layer 1), so the first capture showed an amber nav block on a template whose
description promises "gold and crimson accents". The override was lifted, the
page captured, and the value restored in the same sequence — verified restored.
A gallery tile has to show the *theme's* palette; a tenant's overrides are true
of that tenant, not of the template.

`themes/basic/` may hold this file without tripping the "basic ships no blades"
rule: `ThemeShadows::bladesIn()` matches `*.blade.php` only, and `themes:doctor`
still reports it in step.

**Verified:** 144 theme tests pass, including the conformance case that a
template never promises a gallery image it does not ship. All three
`themes/{template}/screenshot` routes return `200 image/webp` at the expected
byte sizes.

### 2026-08-31 — Page builder: every repeater rendered empty and duplicated

**Status:** complete
**Owns:** nothing new
**Shares — claimed before editing, minimal diffs:**

- `resources/js/page-builder-alpine.js` — exact edit: delete the second,
  duplicate set of `repeater*` methods (the dead one).
- `resources/views/pages/settings/pages/partials/field-renderer.blade.php` —
  exact edit: delete the unguarded `@include` of the repeater on the last line.

**Reported as:** "the cards are not editable in the page builder". Opening a
services grid showed an empty Cards list reading "0 of 12" on a section with six
cards, the Cards block drawn twice, and a stray "Add item" list after every
other field.

**Two separate bugs, both in committed code, neither from the A-Plus template.**

1. **The repeater methods are declared twice in one object literal.**
   `Alpine.data("pageBuilder")` is a single object, and it defines
   `repeaterRows`, `repeaterAdd`, `repeaterRemove`, `repeaterMove`,
   `repeaterSet` and `repeaterRowLabel` at both line ~477 and line ~622. A
   duplicate key in an object literal silently wins, so the *second* set was
   live — and the two sets take different arguments: the first takes the field
   object, the second a string key.

   Every Blade call passes the field object. So `repeaterRows(field)` ran
   `props[field]`, which JavaScript stringifies to `props["[object Object]"]`,
   found nothing, and returned `[]`. **Every repeater in the builder rendered
   empty regardless of its content** — not just A-Plus's, and not just cards.
   `repeaterCanAdd` existed only in the first set, so it survived, which is why
   the "Add card" button still appeared under an empty list.

   The first set is the one the views are written against, and `repeaterVal` —
   defined only in the second — is called from nowhere. The second set was
   deleted whole.

2. **The repeater partial was included unconditionally.** The last line of
   `field-renderer.blade.php` included it outside every `x-if`, so a repeater
   was drawn after *every* field of *every* widget, on top of the correctly
   guarded one at `field.kind === 'repeater'`. That is both the duplicated Cards
   block and the phantom "Add item" lists.

**Why nobody caught it:** the builder's coverage asserts the editor *renders*
and that the field schema reaches it, which it does. Nothing asserted that a
saved list comes *back* into its own editor.

**Also found, not fixed:** `Reseller\PageController::editHome()` seeds an empty
homepage from `starterHomeLayout()`, which copies the **platform's** home page
and ignores the active theme entirely. A reseller on A-Plus opening a blank
homepage editor would get `hero, memorial_showcase, features_grid, cta_banner`
rather than A-Plus's design. Harmless while every tenant here already has a
theme layout; the same class of bug as the seeding one above.

### 2026-08-31 — A-Plus memorial backdrop: the candle plate

**Status:** complete
**Owns:** `public/images/themes/a-plus/memorial-backdrop.webp`,
`themes/a-plus/memorial-theme.blade.php`

Replaced the memorial band's artwork with a supplied plate: a lit candle at the
lower left under a blue-to-gold wash, with bokeh, leaf silhouettes and soft wave
forms. It is already in this template's two colours, and it is *about* mourning
rather than being a photograph of mourners — which the band, being decoration
rather than anybody's memory, ought to be.

The previous backdrop (a family group) is kept at
`scratchpad/memorial-backdrop-previous.webp` for this session; the original art
is in `themes/_source/a-plus/`.

**The crop is the whole job.** The band is a wide, short sliver — at 1440px it
shows about a fifth of the artwork's height — so `--t-memorial-backdrop-position`
decides what the picture is *of*. At the old `50% 42%` the band was a pleasant
gradient and the candle never appeared at all. Moved to `50% 82%`, which holds
the flame and the top of the candle and lets the base meet the gold rule at the
foot of the band. Narrow screens show over half the image height at the same
offset, so they get the whole candle — the right way round for the smaller
picture.

**Verified by rendering**, which is the only way a crop can be checked:

- desktop 1440px, memorial with no portrait — candle, bokeh, wash, gold rule
- 390px true viewport (through a same-origin iframe, since headless Chrome's
  `--window-size` is not the layout viewport here) — whole candle
- desktop, a memorial that *has* a portrait (`claud-kohler`) — the portrait
  pulls up into the band centre in its blue-to-gold mount, the candle sits clear
  to its left, and the rule runs behind it. Nothing collides.

`themes:doctor` still reports every template in step; `memorial-theme.blade.php`
is an own view, not a shadow, so no baseline changed.

### 2026-08-31 — Finding: Dignified's memorial hero name is illegible

**Status:** reported, not fixed
**Owns:** nothing — this is a note about `themes/dignified/memorial-theme.blade.php`

`memorial-theme.blade.php` raises `.memorial-hero__band` to 13/15/18rem but
leaves `.memorial-hero__frame`'s `padding-top` at the platform's 9/11/13rem.
The two are a matched pair, and the frame's padding is the only thing keeping
the name below the scene. So on every memorial hosted by a reseller on
Dignified, the name straddles the gold-and-crimson rule — half on the black
florals plate, in `#1a1a1a` ink on a near-black photograph.

Verified by rendering `/r/vandervort-west/ethelyn-moore`: "Ethelyn Moore" sits
across the rule and is barely readable. Two resellers run this template today.

**Not fixed here because the fix has two valid shapes and the choice is a
design call for the template's owner:** either raise the frame's padding to
match the taller band (keeping the tall scene), or drop the height override and
keep only the edge treatment (which is what `a-plus` does). Both are a few
lines; they produce noticeably different pages.

### 2026-08-31 — A-Plus tribute artwork: all three cards

**Status:** complete
**Owns:** `public/images/themes/a-plus/tributes/{flower,candle,prayer}.png`
**Shares:** `public/images/tributes/README.md` — the A-Plus paragraphs and the
two keying traps; `tests/Feature/MemorialThemeBlendTest.php` — two added tests,
nothing edited.

**What this is:** A-Plus shipped no tribute artwork, so its three cards were
the platform's coral-and-purple rose, mauve candle and purple-cuffed hands
under a navy-and-gold header. Supplied art for all three — a yellow-hearted
rose with blue edges, a yellow-to-blue pillar on a cobalt dish, and praying
hands with navy cuffs — goes in. Three images and two tests; no view, no CSS
and no rebuild, because `tribute-art.blade.php` already looks in
`public/images/themes/{template}/tributes/` per file.

This also closes the mismatch the first pass reported: `memorial-theme.blade.php`
was already raining gold petals off the platform's coral rose. The petals now
fall from a flower that is their colour.

**Verified:**

- 23 `MemorialThemeBlendTest` cases pass, including two new ones — all three
  cards on the template's own artwork with none of ours leaking in, and the
  petal list pinned against the rose it now falls from.
- 148 theme tests pass overall.
- The served page returns all three `images/themes/a-plus/...` paths and no
  platform tribute artwork at all.
- Rendered at 1440px: the three cards read as one set. Sizes checked against
  their neighbours — rose 216x256 (dignified's is 212x256), candle 202x256
  (the platform's is 206x256), hands 256x256 (as the platform's hands are).
- **Tapped the flower through CDP and watched it**: gold petals rain from a
  gold-hearted rose. That pairing is the thing the README calls one decision,
  and it is the only way to see it.
- The full-screen prayer scene opened by tapping its card: the hands draw large
  with the light rising out of them, edges clean.
- All three files checked composited on white, on `#06214F`, on black and on
  `#0C2A5C`, at full size and at 64px, with every edge that could hold a halo
  zoomed 5-7x: fingertips, sleeve ends, dish rim, flame, petal edges, leaves.

**Not verified:**

- Never seen on a real reseller *host* or custom domain — only `/r/{slug}`.
  `RESELLER-PRODUCTION-CHECKLIST.md` still governs deployment.
- Dark mode on the memorial page itself. All three were checked on this
  template's dark grounds, but the page was not rendered with `.dark` set.
- No real device, no Safari or Firefox.
- The candle scene was not opened. It does not draw the card artwork — it
  builds its own cup, flame and glow sprites — so this change cannot reach it.
  Whether its generated cups suit a blue-and-gold brand is a separate question
  and was not asked here.

**Deliberately not built:**

- **Any change to `__tributePetalColours`.** Checked, not skipped: quantising
  the new rose's bloom gives `#997338`, `#E8A717`, `#D7AE4D`, `#F7D462`,
  `#F5DF94`, and the gold spread already in `memorial-theme.blade.php` is that
  same ramp — `#F7D462` against its `#F8D566` at the bright end. Blue stays out
  because each petal is filled flat, so a navy entry drops solid chips rather
  than the two-tone petals the artwork has. A test now pins the pair together.
- **Any change to `memorial-theme.blade.php` at all.** It was already correct
  for artwork that did not exist yet.

**For whoever is next:**

- **Three files, three different keying problems. Decide what the near-white in
  a given file actually is before picking a number.** The hands fade to white
  *by design*, so no single fuzz works — 12% keeps the fingertips but leaves an
  opaque white band with a torn edge across the sleeves, 30% clears the band but
  leaks through the fingertips' specular rim and bites them; the fill is 12%
  above a line below the lowest skin and 30% below it, feathered. The candle is
  the reverse: its flame core is *legitimately* near-white, so 30% hollows it
  out and the whiteness ramp that saved the hands had to be left off entirely.
  The rose's palest petal highlights start taking bites at 28%. All three are
  written up in `public/images/tributes/README.md`.
- **Loose petals come off with connected components, not a hand mask.** The
  rose arrived with five painted in. They do not touch the bloom, so labelling
  the alpha mask and keeping only the largest component removed all five plus a
  two-pixel speck exactly, with no risk to the subject's own edges. Command is
  in the README.
- Every one of these was invisible on white and obvious on black. The README's
  "check it on both grounds" line is not optional; the first check sheet for the
  hands looked perfect and was wrong.
- Tapping a tribute card through CDP to verify **records a real tribute**. The
  local memorial `thad-oconner` gained a prayer and a flower this way. Local DB
  only.
- The README still says this folder "is not in version control". That is now
  stale — `git check-ignore` clears both `public/images/tributes/` and
  `public/images/themes/`, so this artwork will commit. The
  `flower-bouquet.png` rationale rests on that sentence, so someone should
  decide whether it still holds rather than let it rot.

### 2026-08-31 — Pricing: wording and feature order

**Status:** complete
**Owns:** `app/Support/PlanFeatures.php`
**Shares:** none. No view was touched — both pricing surfaces and the admin plan
form already read this one declaration, which is what the file was built for.

**What this is:** "Life Story / Obituary" becomes "Biography / Obituary", and
the public feature order is rearranged so the plain, high-priority rows lead and
the technical ones sit at the bottom. The new order:

1. Beautiful Memorial Page, Biography / Obituary, Permanent Preservation, Ad-Free
2. Memorials, Photo Uploads, Video Uploads, Photo and Video Albums, Story Chapters
3. Family & Friends Contributors, Candle & Flower Tributes, Tributes, Easy
   Sharing, Background Music, Advanced Privacy, Guest Notifications
4. AI Biography, Video Allowance, Total Storage, Secure Hosting & Backups

**The thing worth knowing:** array order and `group` were the same axis until
today, and are now two. Array order is what a customer sees — `comparison-rows`
and `plan-bullets` both render `publicRows()` straight down it. `group` only
buckets the admin form. Putting Permanent Preservation (a `Features` row) above
Memorials (an `Allowances` row) means the groups now interleave, and because
`stored()` drops the always-on rows, the first stored entry became a `Features`
one — which would have silently moved the admin screen's Features section above
its Allowances section. So `storedByGroup()` now takes its section order from a
declared `GROUP_ORDER` instead of from whichever group happens to appear first.
Without that, any future reordering of the pricing page reshuffles the admin
form as a side effect, and nobody would connect the two.

**Verified:**

- The full suite: 798 passed, 2496 assertions.
- `publicRows()` dumped in order — 20 rows, reading as listed above.
- `storedByGroup()` still yields Allowances (9), Features (8), Coming (6), in
  that order, unchanged from before.
- Counts intact end to end: catalogue 26, stored 23, public 20 — nothing lost or
  duplicated in the reshuffle.
- Both served pages checked, platform `/pricing` and the reseller
  `/r/robel-bogisich/pricing`: new order in the comparison table and the card
  bullets, "Biography / Obituary" present, "Life Story" returning zero hits.
- Rendered `/pricing` at 1440px and read the table.
- A repo-wide grep confirmed "Life Story" existed in exactly one place.

**Not verified:**

- The admin plan form and the pricing page-builder widget were not opened in a
  browser. Both read the same two methods, and the suite covers them, but
  neither was looked at.
- No dark mode, no mobile width, no Safari or Firefox.

**Deliberately not built:**

- **No change to any `group` value.** Moving a row up the pricing page is a
  presentation decision; which admin section it belongs in is not, and changing
  both at once would have made the admin screen shift for reasons unrelated to
  the request.
- **No change to `help` text.** `max_gallery_videos` still says the total size
  "is set separately below", and Video Allowance is still below it, so it reads
  true.
- **The plan figures themselves.** Worth someone's eye, and nothing to do with
  this change: on this local database the Annual plan shows fewer Memorials than
  Premium (1 against 5) and has Permanent Preservation off while Premium has it
  on, and Free shows Video Allowance "Unlimited" beside Total Storage "100 MB".
  That is plan data, most likely local seed values, not the ordering.

### 2026-08-31 — Pricing: "Story Chapters" becomes "Life Stories"

**Status:** complete
**Owns:** `app/Support/PlanFeatures.php`
**Shares:** none.

**What this is:** the `max_chapters` label, one line, plus a comment and a
`help` string recording what the figure actually counts.

**Read this before touching the label again.** `max_chapters` gates
`$memorial->storyChapters()->count()` — the family's own groupings, the chips
the stories pane files posts under, which its own comment calls "where the
family filed it". **The stories themselves are not limited at all**: there is no
`canAddStory` anywhere in `PlanLimitsHelper`, and the only related gate is this
one. So "Life Stories — 3" names the folders after the things inside them, and
can be read as a cap on how much a family may write when it is nothing of the
kind. That was raised before the change and the label was chosen anyway, which
is a legitimate call — but it is a promise on a pricing page, so it is written
down here rather than left to be rediscovered.

**Verified:** full suite 798 passed; `/pricing` served, row 9 now reads "Life
Stories".

**Not verified:** the admin plan form was not opened, so the new `help` string
was not seen rendered.

**Deliberately not built** — all three were offered and declined in favour of
the pricing label alone:

- **Signup step 3 still says "story chapters"** in its own hand-written copy
  (`resources/views/pages/memorial-signup/step3.blade.php:125`), so the pricing
  page and the signup flow now disagree.
- **The dashboard tile still says "Life Chapters"**
  (`resources/views/pages/dashboard/index.blade.php:453`), which is a third name
  for the same thing.
- **The stories pane still labels the filter row "Chapters"**, which is what a
  family actually sees in the product.

**A real bug found next door, not fixed and not mine:**
`step3.blade.php:125` renders `$plan->max_chapters == 0 ? 'Unlimited' : ...`,
and line 122 does the same for `max_tributes`. Zero has not meant unlimited
since the sentinel changed — `PlanFeatures::UNLIMITED` is **-1**, and 0 now
means "none at all". So a plan withholding chapters or tributes advertises them
as unlimited on the signup page, and a genuinely unlimited plan (-1) prints
"-1". `PlanFeatures::format()` exists precisely to stop each screen inventing
this; that view predates it and never got moved over. Two lines, but it changes
what customers are shown, so it wants deciding rather than slipping in here.

### 2026-08-31 — Signup wizard: plan cards printed the unlimited sentinel

**Status:** complete
**Owns:** `resources/views/pages/memorial-signup/step3.blade.php`,
the three new cases at the foot of `tests/Feature/PlanLimitsSentinelTest.php`
**Shares:** none.

**What this was.** The wizard's plan cards did their own arithmetic:
`$plan->x == 0 ? 'Unlimited' : $plan->x`, five times over, written when zero
meant no ceiling. Since the sentinel flipped, that is wrong in both directions
at once — an unlimited allowance (-1) prints "-1", and one a plan withholds (0)
is advertised as unlimited.

**The first half was live.** Rendered against the seeded catalogue, Premium,
Annual and Lifetime were all showing customers "-1 tributes", "-1 gallery
photos", "-1 story chapters", and Lifetime "-1 MB storage" — on the page where
somebody chooses what to pay for. No seeded plan currently holds a 0, so the
second half was latent rather than visible.

**The fix** routes all five through `PlanFeatures::format()`, which is the
renderer the pricing table and the admin screen already share and which exists
precisely so no screen invents this again. A withheld allowance now greys out
and reads "No tributes", matching the pattern the AI-biography bullet directly
below it already used.

**Verified:**

- Full suite: 804 passed, 2522 assertions.
- **The new tests were run against the old blade and two of the three failed**,
  which is the only thing that proves they bite. Restored, and they pass.
- Rendered every seeded plan through both the old and new expressions to see
  exactly which strings changed.

**Not verified:** the page was never opened in a browser — it needs a completed
step 1 and 2 and an authenticated user, which the tests set up but a manual
pass would not have. Nothing was checked at mobile width or in dark mode.

**A correction, so it does not get repeated.** A first pass at comparing old
against new suggested single-memorial plans read "1 memorials". They did not —
the old code fed `Str::plural` the raw value and got the singular right. That
was a fault in the throwaway comparison script, not in the view, and it was
caught only by running the new test against the old code. The third test still
earns its place as a guard; it just never described a live bug.

**Deliberately not built:**

- **The wizard still says "story chapters"** while the pricing page now says
  "Life Stories". That divergence was chosen, and is recorded in the entry above.
- **The `@if ($plan->max_ai_bio_per_day > 0)` bullet below these was left
  alone.** It reads the value directly rather than through `format()`, but `> 0`
  is correct for a TYPE_DAILY entry where zero genuinely means off, so there is
  no bug to fix and touching it would only widen the diff.

### 2026-09-02 — A-Plus redesigned to the client's final mockup

**Status:** complete
**Owns:** `themes/a-plus/ap-theme-style.blade.php`,
`themes/a-plus/components/home-header.blade.php`,
`themes/a-plus/components/visitor-footer.blade.php`,
`themes/a-plus/page-builder/widgets/section-banner.blade.php`,
`themes/a-plus/page-builder/widgets/section-grid.blade.php`,
`themes/a-plus/page-builder/widgets/section-split.blade.php`,
`themes/a-plus/theme.json`, `themes/a-plus/preview.webp`,
`public/images/themes/a-plus/hero-candle.webp`,
`public/images/themes/a-plus/landscape-mist.webp`
**Not touched after all:** `themes/a-plus/memorial-theme.blade.php`. Claimed
before starting and then left alone — it is written entirely against
`--ap-blue`, `--ap-gold`, `--ap-ink` and `--ap-navy`, so retokenising moved the
whole memorial page with no edit. Verified by rendering it, not assumed.
**Shares:** none intended. If a change cannot be made inside `themes/a-plus/**`
and `public/images/themes/a-plus/**`, it gets its own entry and is named here
before it is made.

**What this is.** The client supplied a second, final mockup and two new
photographs. It is not a polish of the template shipped on 2026-08-31 — it is a
different visual language, and every page of the theme moves to it.

The template today is navy-dominant: a navy hero over a drained photograph, a
filled-blue lead card, a navy statistics plate, gold pill buttons, a navy
footer. The mockup inverts that. Navy stops being a ground and becomes ink.

| | Shipped 2026-08-31 | Final mockup |
|---|---|---|
| Hero | navy scrim over greyscale photo, white type | pale blue band, photo bleeding right in full colour, navy type |
| Headings | Montserrat | serif (Playfair Display) |
| Buttons | gold full pills | solid navy rectangles, ~4px radius |
| Cards | bordered tiles, first filled blue, "Learn More" row | no cards — centred columns with hairline dividers and circular pale-blue icon badges |
| Stats | inset navy plate over a photo | dropped |
| Footer | navy, gold discs, chevron bullets | light ground, navy serif headings, navy bottom bar |
| Accent | gold pills, gold rules, gold icons | one muted amber rule under each centred heading; nothing else |

**Why the tokens carry most of it.** `--t-btn-radius`, `--t-heading-family` and
the surface tokens live in this template's own `:root` block, so a language
change of this size is still additive — the platform's widget views read the
tokens and follow. The forks (`section-banner`, `section-grid`) change only
where the *structure* differs: an image that bleeds beside the copy instead of
sitting under a scrim, and a column group that is not a card.

**Deliberately not built** — recorded here before the fact so the gaps are not
mistaken for oversights:

- **The mockup's content is placeholder and is not being adopted.** It shows
  four services, "123 Funeral Home Lane, City, State 12345" and
  "(012) 345-6789". The template keeps its six real services — they have six
  detail pages in `default_pages` that renaming would orphan — and contact
  details keep coming from `ThemeSetting`. The mockup is taken as a design
  spec, not a copy deck.
- **The statistics band goes, and with it the "30+ Years Experience" /
  "1,000+ Families Served" problem** the wiki flagged as shipping one client's
  claims to every future reseller. Closed by deletion rather than by decision.

**For whoever is next:** `/r/vandervort-west` is the only local tenant on this
theme. It carries pages seeded from the old `default_pages`, and
`ThemePages::seed()` never overwrites — so editing `theme.json` copy will not
move that tenant. Styling changes reach it; content changes do not.

**What was built.** Five files carry the new language; `theme.json` carries the
new tokens and a rebuilt front page.

- **`ap-theme-style.blade.php`** — the whole palette and type vocabulary.
  Playfair Display headings, `--t-btn-radius: 0.25rem`, `--ap-sky` (`#DFE6F0`,
  sampled off the supplied candle photograph so the band and the picture are one
  surface), `--ap-line`, and three new hooks: `.ap-rule`, `.ap-badge`,
  `.ap-col`.
- **`section-banner`** — the hero stops putting the photograph *behind* the
  words. `background: image` now means a pale band with the picture bleeding out
  of the right-hand edge in full colour, under a `.ap-bleed-fade` gradient.
  Explicit `dark`/`accent` still gets the old scrim, so the prop keeps meaning
  something.
- **`section-grid`** — cards replaced by centred columns separated by hairlines.
- **`visitor-footer`** — light ground, navy serif headings, one navy closing bar.
- **`home-header`** — the blue utility strip is gone; one white bar.

**Three bugs found by rendering and measuring, none by the test suite.**

1. **Every column title came out in the heading serif.** `AppearanceHelper`
   emits `h1, h2, h3, h4, h5, h6 { font-family: <heading font> }` from the
   Appearance page, so an `<h3>` is Playfair whether it asks or not. Declining
   `.t-heading` is not enough — the rule has to be overruled. Fixed with
   `.ap-col-title { font-family: var(--t-body-family); }`. **Worth knowing for
   any future template:** on this platform a heading element is opinionated
   before your template says a word.
2. **No width cap produces the mockup's hero break.** "In Need / We Care" needs
   an explicit break; every column narrow enough to wrap it breaks
   "In Need We / Care" instead. `.t-banner-line` is `display: inline` in the
   base stylesheet precisely so a template can make it a block, so the fork now
   splits the heading on a literal newline first and falls back to the old
   sentence split. The break is data in `theme.json`, not a layout accident.
3. **The hero standfirst failed WCAG AA.** This was about to be filed under "not
   verified" on the grounds that it looked fine. Measured instead:
   `--ap-ink-soft` at `#5A6B85` on `--ap-sky` is **4.31:1**, and the hero body
   copy is 15px normal text, which needs 4.5. Every other pairing in the design
   passed comfortably — it was specifically the one surface introduced by this
   redesign that broke. Darkened to `#55657E`: 4.71:1 on the pale band, 5.92:1
   on white. The nine pairings the template actually renders are all AA now,
   worst case 4.71:1. **The lesson is the method, not the value** — the failing
   pair was invisible by eye and would have shipped.

**Verified:**

- **Full suite: 819 passed, 2560 assertions.** Theme subset on its own: 153
  passed, 575 assertions.
- **Rendered, not just asserted.** Headless Chrome over CDP at 1440px: the front
  page, `/funeral-arrangements` (banner + split + grid), and
  `/vandervort-west/ethelyn-moore` (the memorial, which never passes through
  `layouts/visitor`). Front page again at 390px with mobile emulation — the
  bleed image drops out, the band goes flat, the dividers disappear at one
  column, and nothing scrolls sideways.
- **The front page was rendered against the template's own `default_pages`**, by
  creating a throwaway tenant with no saved pages (`aplus-preview`, deleted
  afterwards). `/r/vandervort-west` renders that tenant's *saved* pages and
  therefore shows the new styling with the old copy — which is correct
  behaviour, and is why it is not a valid check of the new document.
- `themes:sync` clean, `npm run build` clean, `theme.json` re-parsed after every
  scripted edit.
- `themes/a-plus/preview.webp` regenerated from the new front page, so the theme
  gallery does not advertise a design that no longer exists.
- **Contrast measured, not eyeballed**, across the nine foreground/background
  pairings this template renders. All pass WCAG AA for their text size; the
  tightest is the hero standfirst at 4.71:1. The script is disposable but the
  pairing list is worth rebuilding for any future template.

**Not verified:**

- No real browser, no Safari, no Firefox. Chromium only.
- **Nothing was checked on a real reseller host or custom domain** — only the
  `/r/{slug}` path fallback. `RESELLER-PRODUCTION-CHECKLIST.md` still governs.
- Tablet widths between 640px and 1024px were not rendered. The two-column
  divider logic is the thing most likely to be wrong there.
- Nobody has opened the page builder against the new widget views. The rendered
  output is right; the *editing* experience for these sections is unchecked.


**Deliberately not built:**

- **The three tribute cards are still in the old brand colours.**
  `public/images/themes/a-plus/tributes/{candle,flower,prayer}.png` are 3D
  renders in `#0033A0` and `#FECB01`. Against the new muted palette they are the
  most saturated thing on the memorial page. Not recoloured: they are supplied
  client artwork, a convincing recolour of rendered art is not a levels tweak,
  and repainting a client's assets is not a call to make unasked. Same applies,
  more mildly, to `memorial-backdrop.webp`.
- **The header button still says "Call Us Now", where the mockup says
  "Reach Us 24/7".** The design was adopted; that particular string was not.
  This is a shared template, and a 24/7 promise printed in the header of every
  reseller who applies it is the same class of problem as the statistics band
  this change just deleted. One line to change if A-Plus wants it.
- **The footer keeps its services column**, where the mockup has
  logo / Quick Links / Contact Us / Follow Us. That column renders
  `footerCompanyItems`, a menu the reseller builds in Reseller → Menus, and
  dropping it would silently stop showing links somebody configured.
- **The mockup's content was not adopted, only its design.** It shows four
  services, "123 Funeral Home Lane, City, State 12345" and "(012) 345-6789" —
  placeholders. The six real services and their six detail pages are unchanged,
  and contact details still come from `ThemeSetting`.
- **The mockup repeats one tick icon four times** under "Why Choose A-Plus".
  Replaced with four distinct marks: a row of identical icons carries no
  information and reads as unfinished artwork.
- **The home page lost its contact-and-map section**, following the mockup. Its
  Google Map was already broken before this change — it renders "Google Maps
  Platform rejected your request. Invalid request. Invalid 'pb' parameter." on
  `/r/vandervort-west`. **That fault is still live on every other page and
  template that uses `section_contact`** and is not fixed here.

**For whoever is next:**

- `ThemePages::seed()` never overwrites, so **the three local tenants on this
  theme keep their old copy and their old section order.** They pick up the new
  styling immediately and nothing else. Reseller → Themes → Apply now offers to
  swap kept pages for the template's version, which is the supported way to move
  one of them onto the new front page.
- `--ap-sky` is tuned to the two supplied photographs. A reseller who swaps the
  hero image for one with a different background colour will see a seam where
  the fade lands. The fade is `.ap-bleed-fade`; widening the transparent stop is
  the first thing to try.

### 2026-09-02 — A-Plus, second pass: measured against the mockup instead of eyeballed

**Status:** complete
**Owns:** the same five theme files and `theme.json`; `themes/a-plus/preview.webp`
**Shares:** none.
**Local data touched:** `ThemePages::resetToTheme()` run twice against
`vandervort-west`'s `visitor-home`. See "the thing that looked like a bug".

**What this is.** The first pass got the language right and the *dimensions*
wrong, and "it looks close" is what let that through. This pass compared the two
renders numerically — same crop, same width, bounding boxes measured with
ImageMagick — and fixed what the comparison showed.

**Measured, and wrong by more than eye:**

| | First pass | Mockup | Now |
|---|---|---|---|
| Icon disc | 56px | ~94px | 92px |
| Column title | 15px sans | ~21px serif | 19px serif |
| Column copy | 13.5px | ~15px | 15px |
| Tick mark | 32px | ~52px | 48px |
| Button | 135×43 | ~175×52 | ~165×52 |
| Hero heading block | 65px tall | 82px | 4.375rem |

The section headings, checked the same way, were already exact — 124×16 against
124×16. Which is the point: the eye was wrong about which things were wrong.

**Three of my own judgement calls, reversed against the mockup.**

1. **Column titles are serif.** The first pass forced them to the body face and
   wrote a confident comment about high-contrast serifs going spindly at label
   size. Reading the mockup at full resolution, they are unmistakably Playfair,
   and the serif is most of what stops the row reading as a feature-comparison
   table. The `.ap-col-title` hook survives; it now asserts the serif instead of
   overriding it.
2. **The four ticks are all the same tick.** The first pass substituted four
   distinct marks on the grounds that a row of identical icons carries no
   information. True in general, wrong here — the row is a checklist and the
   repetition is what makes it read as one.
3. **The button says "Reach Us 24/7".** Held back in the first pass because a
   24/7 promise in a shared template outlives the client who made it. Asked for
   explicitly, so it ships. **The concern stands and is now the live example of
   it** — the note in [[Theme System]] should be read before this template is
   offered to a second reseller.

**Also this pass:** the pale band is the mockup's `#E4ECF3`, not a colour
sampled off the photograph; the hero carries a blue wash over the picture as
well as the band; `--ap-mist` stopped being a cool blue tint and became the
near-white `#FDFDFB` the mockup actually uses for every section; the footer
gained a "Follow Us" column and a single hairline; the services grid is the
mockup's four columns with its copy verbatim, American spelling included.

**A second WCAG failure, caught the same way as the first.** Darkening
`--ap-ink-soft` in pass one fixed the standfirst against `--ap-sky` — and then
this pass put a blue wash over the hero, which took the ground under that same
text to `#D7E0E6` and the ratio back to 4.43:1. **The token was passing; the
pixel was not.** Contrast is now measured by sampling the rendered PNG at the
coordinates the text actually occupies, not by reasoning about the token, and
the hero standfirst has its own `--ap-ink-strong` at 5.54:1 measured. Every
sampled pairing passes.

**The thing that looked like a bug and was not.** Reported as "we are still
getting the old design". The styling on `/r/vandervort-west` was the new one all
along; the *content* was that tenant's own saved page, which `ThemePages::seed()`
correctly refuses to overwrite. Resolved with `ThemePages::resetToTheme()` — the
same call Reseller → Themes makes — against that one page. **`ferry-heller` and
`robel-bogisich` were left alone.** Worth saying plainly because it will be
reported again: after a template redesign, existing tenants get the new paint
and keep their own words, and that is the design working.

**Verified:** full suite 819 passed / 2560 assertions, twice. Rendered at 1440px
and compared crop-for-crop against the mockup. Contrast sampled from the render.
`preview.webp` regenerated.

**Not verified:** unchanged from the first entry — Chromium only, no real
reseller host, no 640–1024px tablet pass, page builder not opened against the
new views. **The mobile and service-page renders predate this pass's size
changes and were not re-taken**, which matters most for the 92px discs at 390px.

**Still deliberately not built:** the tribute artwork is still in the old
`#0033A0`/`#FECB01`; the mockup's `www.aplusfuneral.com` footer row is absent
because there is no globe in the icon set and no website setting to read, and
adding both reaches outside `themes/a-plus/`. Two of the six service detail
pages (repatriation, memorial services) are no longer linked from the front page
because the mockup names four services — they keep their pages and stay reachable
from `/services` and the footer menu.

### 2026-09-02 — A-Plus, third pass: the pages the template never had

**Status:** complete
**Owns (new):** `themes/a-plus/sections/page-banner.blade.php`,
`themes/a-plus/sections/prose-page.blade.php`,
`themes/a-plus/pages/memorial-directory/index.blade.php`,
`themes/a-plus/pages/visitor/{about,cms-page,contact,pricing,privacy-policy,terms-of-use}.blade.php`
**Owns (edited):** `ap-theme-style.blade.php`, `page-builder/widgets/section-grid.blade.php`,
`theme.json`
**Shares:** none. `partials.pricing.*` and the directory partial are *included*, never forked.
**Local data touched:** `resetToTheme()` across all eight default pages of
`vandervort-west`; `subscription_plans.reseller_id` set to 1 and back to NULL to
render the pricing page once (see below).

**What this is.** The redesign had covered the home page and the widget layer.
It had not covered the pages that are not built from widgets — and A-Plus, unlike
Dignified, shipped **no page views at all** beyond `home`. Everything else fell
through to the platform's own Blade.

**The reported symptom was real and worse than it looked.** "On find memorial
page we don't have the header on A-Plus theme." The platform's
`pages/memorial-directory/index.blade.php` extends `layouts.fullscreen-layout`
directly, so it renders the header component but **no footer at all**, on flat
white, under a bare `text-2xl` heading. Confirmed with `grep -c '<footer'`:
`0` on both A-Plus tenants and on the platform host, `1` on Dignified — which
had already hit this and written the fix. A visitor was being dropped out of the
design halfway through a visit.

**A-Plus now covers every visitor view the platform has** except `page-layout`,
which is the builder's renderer and not a page. 14 shadows, 4 own views,
`themes:doctor` in step.

| View | What it was falling through to |
|---|---|
| `memorial-directory/index` | header, no footer, bare heading on white |
| `visitor/contact` | `rounded-2xl` grey cards, platform `.btn-primary` |
| `visitor/pricing` | platform-brand ring and buttons, green/blue/purple trust badges |
| `visitor/about`, `cms-page`, `privacy-policy`, `terms-of-use` | unthemed prose |

Two new partials carry the shared furniture: `sections/page-banner` (the centred
pale band, serif title, amber rule and breadcrumb) and `sections/prose-page`
(banner plus a prose column whose headings take the template's serif). Both are
the template's own views, not shadows — the platform has nothing to shadow.

**Behaviour was not touched.** The contact form keeps its action, field names,
`@error` bags, session flashes and Alpine `sending` guard; pricing keeps
`$plans`, `PriceHelper` and both `partials.pricing.*` includes. A themed form
that drops a validation message is worse than an unthemed one, and the pricing
partials are where the unlimited-sentinel formatting lives — the exact thing the
signup wizard got wrong by reimplementing.

**A bug in my own work from pass two, found by rendering the About page.** The
icon disc was keyed on whether the row's items linked anywhere: services do,
reasons do not. It read well and was wrong within one page — the About page's
services grid has no urls, so six services rendered as bare marks. Re-keyed on
the icon itself: `circle-check-big` and friends draw their own ring and get no
disc; everything else does. **An icon that draws a ring is a fact about the
icon; whether somebody filled in a url is not.**

**One inner-page header, everywhere.** The `services` page still opened with the
old home hero — tall, left-aligned, "Honouring Life With Dignity" bleeding over
`hero.webp` — while `/contact` and `/pricing` opened with the clean centred band.
Now every page that is not the home page opens the same way.

**Verified:** full suite 819 passed / 2560 assertions. `themes:doctor` in step
with 14 baselines recorded. Rendered at 1440px: find-memorial, contact, pricing,
privacy-policy, services, funeral-arrangements, about. find-memorial also
rendered at 390px mobile — title band, filters, cards and the full footer.

**A pre-existing fault, not caused here and not fixed here.** A reseller's
pricing page shows **no plan cards and a one-column comparison table**. All four
`subscription_plans` rows carry `reseller_id = NULL`, so a tenant matches none.
Confirmed pre-existing by re-reading the render taken before this pass — the old
platform view was equally empty. To prove the new plan-card layout actually
works, the four plans were pointed at reseller 1, the page rendered, and the
column set straight back to NULL in the same command. **The cards, the "Most
Popular" treatment and the bullet formatting are correct; whether a reseller
should inherit platform plans is a product question and belongs to whoever owns
billing.**

**The green tick.** `partials.pricing.plan-bullets` and `comparison-rows` colour
their tick `text-green-500`, and green is not in this palette. Not forked —
those partials carry plan logic. Recoloured through a `.ap-pricing` wrapper the
theme puts around the includes, so the override lands here and on no other
template.

**Not verified:** Chromium only; no real reseller host; 640–1024px untested; the
page builder has still not been opened against the new widget views. **The
contact form was never submitted** — the markup keeps every hook, but no round
trip was made through `contact.send`, and neither the success nor the error
flash has been seen rendered.

**Deliberately not built:** the tribute artwork is still in the old brand
colours; the mockup's `www.aplusfuneral.com` footer row still has no globe icon
and no setting to read; `memorial-signup` is untouched, as it is on Dignified —
it is an application flow rather than a public page, and theming a checkout is a
larger decision than theming a marketing site.

### 2026-09-02 — A-Plus memorial tabs: the open tab did not look open

**Status:** complete
**Owns:** `themes/a-plus/memorial-theme.blade.php`
**Shares:** none.

**What this is.** Reported as "make the active tab the brand blue, keep the
yellow underline". It already *was* the brand blue —
`.memorial-tab-btn[aria-selected='true']` had set `color: {{ $apBlue }}` since
the template was written, and sampling the rendered PNG confirmed the Bio label
was exactly `#1B3A6B`. So the request could not be taken literally; something
else was wrong.

**The fault was the other three tabs.** The platform sets closed tabs
`text-gray-600` (#4B5563). Against `#1B3A6B` that is a difference of hue and
almost none of lightness, at the same weight — four tabs read as one row of
grey-blue words, and the amber bar was the only thing saying which was open.
Nothing was wrong with the blue; there was nothing for it to be brighter *than*.

**Two attempts, and the second is what shipped.**

1. **Weight.** Open tab bolded, closed tabs stepped back to `--ap-ink-soft`.
   Better, and still a row of words.
2. **A fill.** Shown a reference — a filled dark tab with a coloured rule under
   it — the answer was a solid block, not a colour. The open tab is now
   `--ap-blue` filled, white bold label, amber bar beneath. Nothing else on the
   page is a solid blue rectangle, so there is no ambiguity left.

Worth recording that the platform's own version *also* fills the open tab, just
palely — and that pale tint is what reads as a disabled control on this palette
and got replaced in the first place. The fix was not "don't fill it", it was
"fill it properly".

**Why the closed tabs are not simply faded out.** They are 14px body text and
still have to clear AA on white. `--ap-ink-soft` (#55657E) is 5.92:1; the
obvious lighter greys measure 4.37:1 (#6B7A91) and 3.50:1 (#7C8AA0) and both
fail. Measured before choosing. White on the filled tab is 11.27:1.

**Verified:** rendered `/r/vandervort-west/ethelyn-moore` before and after and
compared the crops. Theme suite 153 passed / 575 assertions; `themes:doctor` in
step. The amber underline is untouched, which was the explicit ask.

**Not verified:** only Bio was rendered as the open tab — the page opens on it
and no tab was clicked, so the rule is confirmed through `aria-selected` on
first paint, not after the tab script rewrites the classes on interaction. The
comment above the rule claims it holds for both; that claim is inherited from
the original author and was not re-tested. Dark mode not checked, and the dark
branch of this rule now sets a white label on the brand blue rather than gold on
transparent — untested because this template ships light-only.

### 2026-09-02 — A-Plus on a phone: the photographs were never there

**Status:** complete
**Owns:** `themes/a-plus/ap-theme-style.blade.php`,
`themes/a-plus/page-builder/widgets/section-banner.blade.php`,
`themes/a-plus/page-builder/widgets/section-grid.blade.php`,
`themes/a-plus/components/visitor-footer.blade.php`
**Shares:** none. `public/build/**` rebuilt, as any Tailwind class change
requires.

**The report was "the images are not seen" on mobile, and it was literal.**
`ap-theme-style` carried this, from the 2026-09-02 redesign:

```css
@media (max-width: 1023px) {
    .ap-bleed-image { display: none; }
}
```

under a comment saying the photograph "stops being a bleed and becomes a band
under the copy". Nothing ever put it there. Every phone visitor to the front
page got a pale blue rectangle with a heading and a button in it where the
candle should be, and the closing "We Are Here For You" section lost its
landscape the same way. Both photographs were shipped, committed and referenced
by `theme.json`; they were simply switched off at the width where most of this
client's visitors are.

The reasoning that wrote the rule still stands — there is no room for a picture
beside two lines of serif heading at 390px, and fading one out behind the text
turns the heading grey on grey. What was wrong was throwing the picture away
rather than moving it. **The composition now rotates instead of collapsing:**
the copy keeps the pale band, the photograph runs full width beneath it flush to
the foot of the section, and its top edge is masked into the band exactly as the
desktop version fades its left edge into the copy. Same two elements, same idea,
turned ninety degrees. 16rem under the hero, 14rem under the closing band.

**A class, not the height attribute.** The section gives up its bottom padding
to let the picture bleed, and a centred inner-page title band with no picture
must not — `data-banner-height` cannot tell those apart, so the view now sets
`ap-bleed` on the sections that actually carry one. It is the same flag the view
already computes for whether to render the `<img>` at all.

**The rest of the pass, all of it measured at 390px:**

- **Section padding is now a mobile-first scale.** `--t-pad-lg` was 6.5rem at
  every width. That number is measured off a 1440px mockup; on a phone it is two
  fifths of the screen, and six sections of it was ~600px of scrolling through
  nothing. 4rem / 5rem / 6.5rem at base / `sm` / `lg`, ratio between the three
  preserved so the rhythm is the same page seen closer up.
- **Two columns on a phone, not one.** Asked for directly — "instead of having
  this long list can we have two per row" — and right: six services stacked one
  per screen made the front page a scroll rather than a page. `grid-cols-2` is
  now the base and `sm:grid-cols-2` was already the next step, so the change is
  one class and the desktop layouts are untouched. The hairline between a pair
  comes with it: `.ap-col:not(.ap-col-row-start-sm)` used to start at 640px
  because below that there was a single stack and no divider to draw, and it
  now applies from the narrowest width up.
- **What two-up costs, paid back.** At 179px a column cannot carry the mockup's
  sizes. Column gutters `px-6` → `px-3`, titles 19px → 16px, body 15px/1.8 →
  13.5px/1.7, discs 92px → 68px with their icons — all `sm:`-reverted, so
  everything from 640px up is the drawn design to the pixel.
- **The gap between the copy and the photograph**, reported twice — "there is so
  much space", then "reduce further, it is still more". It was being paid for
  twice over: a `--t-pad-md` margin put ~48px of sky under the button and the
  mask then held the first fifth of the picture transparent on top of it. The
  margin is now **gone**, and the fade *is* the air — the picture's box begins
  where the copy ends and the only gap is the part of the picture that has not
  arrived yet. Space doing two jobs instead of space doing none.
- **The picture is fluid, not a plate.** `height: clamp(12rem, 60vw, 18rem)` on
  the hero and `clamp(10rem, 48vw, 15rem)` on a band. A fixed 16rem is the same
  256px on a 360px phone and on a 430px one — a stamp on one and a stripe on the
  other. The middle term ties it to the width it sits on; the two ends stop it
  collapsing or running away before the desktop rules take over.
- **Why the hero is not cropped to a band.** The obvious way to shrink it is a
  short cinematic strip, and it cannot be done here: at 390px the candle
  photograph already renders at almost exactly the box's aspect, and its subject
  runs from the flame at 19% of the frame to the flowers at 92%. Anything
  shorter puts the flame under the fade. So the hero keeps the photograph's own
  proportions and the height came out of the composition around it instead.
- **The copy's own rhythm.** 24px and 32px steps between eyebrow, rule, body and
  button are the mockup's, measured at 1440. On a phone the same four steps are
  separating things inside a third of the screen — each comes down one notch
  below `sm`, and the hero's top padding drops from `--t-pad-lg` to `--t-pad-md`
  because 4rem of sky above the first heading a visitor sees is not an opening,
  it is a wait.
- **Footer tap targets.** Link rows 32px → 44px, phone/email rows 20px → 40px,
  social discs 32px → 40px, all `sm:`-reverted to the sizes the mockup draws.
  The footer is where a phone visitor goes for the number.

**Verified by rendering, which is the only way any of this is ever found.**
CDP with `mobile: true` at 390, 768 and 1440 on the front page, About, Contact
and a memorial, plus 360 for overflow. `document.scrollWidth === clientWidth` on
every page at 360 and 390 — no sideways scroll. **The 1440 render is
pixel-identical to the one before this change** (`compare -metric AE` → 0), so
the desktop design the client signed off is untouched — re-checked after the
two-column pass and again after the hero was tightened, still 0. Theme suite
144 passed across the seven theme test files; `themes:doctor` in step.

The front page on a phone: **4669px** before any of this, with no pictures in it
at all. 4846 once both photographs were put back. 3942 when the columns paired
up. **3706** after the hero's own spacing came down. Two photographs gained and
960px of scrolling lost against where it started.

**Deliberately not built.** The pictures are still the desktop files — no
`srcset`, so a phone downloads a 1600px-wide candle to show it 390px wide. That
is a `ui-performance` job across the whole platform's image handling, not a
theme fork, and doing it here would put a second image pipeline in one template.

**Found and not fixed, because it is not mobile.** `memorial-theme.blade.php`
still parks the sticky profile card at `top: 8.25rem` / `8.75rem` from `md` up,
with a comment measuring those against "the utility strip and the identity bar
together, 117px". The redesign deleted the utility strip; the header is much
shorter now, so that card sits about 40px lower than it should on desktop
memorial pages. One line each, but a desktop change, and this was a mobile
report.

### 2026-09-04 → 2026-09-06 — The phishing relay, and everything that followed

**Status:** complete (PR #7 awaiting merge)
**Owns:** `app/Rules/HumanName.php`, `app/Support/{Honeypot,TrustedProxies,JunkUserPurge}.php`,
`app/Http/Middleware/HoneypotGuard.php`, `app/Console/Commands/PurgeSuspiciousUsers.php`,
`resources/views/partials/honeypot.blade.php`,
`tests/Feature/{ContactHoneypotTest,TrustedProxiesTest,NameSpamRelayTest,GuestInputHardeningTest,AdminBulkDeleteJunkUsersTest}.php`
**Shares (exact edits):** `bootstrap/app.php` (trustProxies narrowed; HoneypotGuard on `web`),
`ContactController`/`RegisteredUserController`/`MemorialSignupController`/`MemorialApiController`/
`MemorialMediaController` (name fields → HumanName), `GuestOnboarding::signUpAndIn` (backstop),
`Admin/UserController` (suspicious filter, `bulkDestroy`), `routes/web.php` (one POST route,
wizard throttles), `pages/users/index.blade.php` (checkbox column, bulk bar), ten public forms
(`@include('partials.honeypot')`), `AppServiceProvider` (sqlite REGEXP for tests).

**What was reported.** "Millions of signups heating our notifications and emails." The admin
Users list showed rows whose *name* was a phishing message and whose *email* was a stranger's.

**What it actually was.** Not a flood — a relay. The account was disposable; the payload was
the verification/welcome mail *we* sent to the victim, from our domain, SPF/DKIM passing,
opening "Hello <name>". `'name' => ['required', 'string', 'max:255']` let a 230-character
sentence with a URL into an email. Rate limits could never have caught it: every request was a
different address and looked ordinary.

**Shipped, in order, each verified live before the next:**

| PR | What | Live check |
|---|---|---|
| #4 | `trustProxies('*')` → private ranges. `'*'` trusted the client's own XFF, which is what every `throttle` keys on — all 22 were resettable per request. | site healthy after |
| #5 | `HumanName` on `register`, wizard, contact | probe → `"name may not contain a web address"` |
| #6 | the *actual* vector: `GuestOnboarding::signUpAndIn` — a guest tribute/heart/story is a signup and sends mail. `guest_name` hardened at both callers **and** a throwing backstop at the choke point. | probe on `first_name` → same rejection |
| #3 | site-wide honeypot (`HoneypotGuard` on the `web` group; absent ≠ filled) | field present on /register /contact /login |
| #7 | suspicious-names filter, bulk delete, `users:purge-suspicious` | tests only (see below) |

**Three things worth more than the code.**

1. **"The signups are still coming" after #5 deployed was the most useful message of the
   incident.** It said the fixed door was not the one in use. Eleven `User::create` paths
   exist; four are reachable signed-out; `GuestOnboarding` does not look like a signup and
   was not in anyone's list of registration endpoints.
2. **Guard at the choke point, not at the callers.** Both the honeypot (a middleware, not a
   per-controller check) and the guest backstop (inside `signUpAndIn`, throwing) exist because
   the original fault was a comment in `banner-form.blade.php` describing a honeypot that no
   form on the site had. Protection written in two places is protection that stops being
   written in one of them.
3. **The bulk delete is built around refusing.** `users.user_id` cascades onto memorials,
   subscriptions and payment orders. Memorial owners, payers, staff, protected and self are
   refused per row in every mode, and the cascade test was run with the guard removed to prove
   it fails. "Delete all" is the suspicious filter only — a surname search must not be one
   confirm away from a cascade.

**Verified:** full suite 853 / 2720. Every merged PR probed on production with a request
carrying a *second* deliberate validation failure (mismatched password, missing `last_name`),
so the rejection could be read without anything being created. Honeypot renders zero pixels
of difference on the contact page.

**Not verified:**
- **#7 has never been clicked.** Local Apache was down; the test asserts the controls render.
  Look at `/users?suspicious=1` on production before pressing anything.
- The guest-write path end to end on the live host: `alwaysforeverloved.com` has **no public
  memorials** (`/api/search/memorials` → `[]`), so the relay was landing on a reseller host or
  reaching `GuestOnboarding` another way. The backstop covers both; the live confirmation is
  inference from code.
- Whether the deployed proxy sits inside the ranges #4 trusts. Site is healthy, which is the
  evidence; `TRUSTED_PROXIES=*` is the one-line revert.

**Deliberately not built:**
- Captcha. A real cost to a grieving family; not warranted while the honeypot + name rule hold.
- Honeypot on the memorial page's JSON endpoints (tributes/comments/stories). Throttled and
  CSRF-protected; a honeypot on a fetch payload buys little against anyone hitting the API.
- Automatic purge. The command exists; running it against production is a human's call, and
  `--dry-run` is the way in.

**Process notes, so they are not rediscovered:**
- `gh pr merge --delete-branch` checks out local `main` and deletes the branch; with local
  `main` ahead of origin it also fails to fast-forward, prints `fatal`, and leaves the working
  tree showing *old* files. The merge itself had succeeded. `git reset --keep origin/main`
  (never `--hard` — two loose files were uncommitted) put it right.
- Two merges the user reported as done had not taken (`origin/main` unchanged, PR `OPEN`).
  Check `gh pr view N --json state` before believing a merge happened.
- Another session was editing `themes/a-plus/**` and `docs/worklog.md` concurrently. Its
  files were left out of every commit here; the worklog was written only once its diff was
  empty.
