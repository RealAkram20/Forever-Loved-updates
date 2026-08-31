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
