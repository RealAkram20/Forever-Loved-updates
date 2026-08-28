# Theme pages in the page builder — implementation plan

> **Status: not started.** Scope: build the rest of the Dignified site — Services, a single
> Service page, About and Contact — **as page-builder documents**, so a reseller can edit every
> one of them from their own account without being able to break the design.

---

## What the study found

### The page builder is already the right shape for this

`App\PageBuilder\WidgetRegistry` auto-discovers every class in `app/PageBuilder/Widgets/`
implementing `PageWidgetContract`. Twelve exist today. Each declares:

| Method | What it controls |
|---|---|
| `defaultProps()` | what a freshly dropped widget contains |
| `rules()` | **server-side validation — this is where a character limit is actually enforced** |
| `fieldSchema()` | the editor's property panel |
| `viewName()` | the blade that renders it |
| `previewFields()` | the label on the canvas |

Adding a widget is one file plus one blade. Nothing needs registering by hand.

### The load-bearing discovery: widget views go through the theme cascade

`components/page-layout/renderer.blade.php` renders each widget with
`@include($class::viewName(), …)`. That resolves through Laravel's view finder — the same
finder `ActiveTheme` prepends the active template onto.

So `themes/dignified/page-builder/widgets/split-feature.blade.php` **overrides** the default
rendering of that widget, for that theme only, with no registry change and no second widget
type. That gives us exactly the split the brief needs:

- **The widget owns the content** — headings, body, image, links. The reseller edits these.
- **The theme owns the appearance** — the gold rule, the small-caps serif, the offset frames.
  The reseller cannot touch it.

One widget set, N themes. A theme that does not override a widget inherits the plain version.
This is why the answer is *not* "a Dignified hero widget and a Basic hero widget".

### Character limits do not exist in the editor yet

`rules()` already enforces server-side (`'text' => 'required|string|max:500'`), so a
too-long value is rejected on save. But `resources/views/pages/settings/pages/partials/field-renderer.blade.php`
supports ten field kinds — text, textarea, number, checkbox, select, json, richtext, color,
alignment, size_unit — and **none of them honour a maximum**. A reseller types 400 characters
into a heading built for 40, sees it render fine in the preview, and gets a validation error
on save with no idea which field.

That is the gap to close, and it is small: a `max` key on the field schema, a `maxlength` on
the input, and a live counter.

### The four pages map onto seven widgets

Studying the design's sections against the existing twelve widgets:

| Page section | Widget | Status |
|---|---|---|
| Inner-page banner (title over image) | `page_banner` | **new** |
| About split (image + eyebrow + heading + body + button) | `split_feature` | **new** |
| Services grid (the six tiles) | `service_grid` | **new** |
| Why Choose Us (icon + title + body list) | `icon_points` | **new** |
| Feedback bar (band + 3-field form) | `feedback_bar` | **new** |
| Contact details + locator | `contact_details` | **new** |
| Single service body (intro, inclusions, aside) | `service_detail` | **new** |
| Prose blocks, images, headings | `paragraph`, `image`, `heading` | exists |
| Enquiry form | `contact_form` | exists |
| Plans | `pricing_plans` | exists |

`split_feature` and `icon_points` are deliberately generic — About uses both, the home page
uses both, and a single service page uses `split_feature` again. Seven new widgets cover four
pages plus the home page we already built, because none of them is named after a page.

---

## Phase 1 — Character limits, before any widget uses them

1. `max` (and optional `min_rows`) on the field schema; `field-renderer` applies `maxlength` to
   `text` and `textarea` and renders a live `n / max` counter that turns amber in the last 10%.
2. The counter reads from the same number as `rules()`, so the editor and the server can never
   disagree. A `LimitedText` helper on the widget base returns both, from one declaration.
3. `richtext` gets a plain-text length cap too — the sanitizer already strips tags, so the cap
   counts what a reader sees rather than what the markup weighs.
4. Tests: a value over the limit is rejected on save; the field schema reports the same number
   the rule enforces.

**Why first:** every widget below declares limits. Building them before the mechanism exists
means going back through all seven.

## Phase 2 — The seven widgets

5. One class per widget in `app/PageBuilder/Widgets/`, one default blade in
   `resources/views/page-builder/widgets/`. The default rendering is plain and correct —
   it is what every un-themed site gets.
6. Limits set from the design's own measurements, not guessed: the About heading wraps to two
   lines at ~30 characters, so that is the cap. Where a limit would be arbitrary, there is no
   limit.
7. `service_grid` and `icon_points` take a repeatable list. The editor's `json` field kind can
   express that today but is hostile to a non-technical reseller; a `repeater` kind is the
   honest fix and is scoped here rather than pretended around.

## Phase 3 — Dignified's own widget views

8. `themes/dignified/page-builder/widgets/*.blade.php` for each of the seven, carrying the
   design: small-caps serif, gold/crimson rules, the offset double frame, the dark bands.
9. The home page moves from its hand-built sections to the same widgets, so there is one
   implementation of each section rather than two that drift. `sections/*.blade.php` becomes
   the theme's widget views.

## Phase 4 — Provisioning the pages so they arrive editable

10. `theme.json` gains `default_pages`: for each standard slug, the widget document that page
    starts as. This is what makes the brief's "resellers should be able to edit this website"
    literally true — the page exists, in the builder, already populated and already correct.
11. `ResellerSiteProvisioner` applies them on creation, and a reseller applying a theme later
    is offered them for any page they have not customised. **Never overwrites a page someone
    has edited** — same rule the provisioner already follows.
12. Services are ordinary pages, not a new model: `/funeral-arrangements` is a `Page` with a
    `service_detail` document. `service_grid` links to them by slug, so adding a seventh
    service is creating a page, and the reseller can already do that.

## Phase 5 — Real icons

13. Replace the hand-drawn service SVGs. They are the weakest thing in the build and read as
    such. See "Icons" below — this needs a decision before it can land.

---

## Icons

The current six are drawn by hand and look drawn by hand. No icon library is installed
(`package.json` has none, `composer.json` has none), so this is an addition either way.

The problem is that the design's pictograms are funeral-specific — a casket on a bier, a
hearse, a headstone, an urn — and no general-purpose set contains them.

| Option | Style match | Coverage | Cost |
|---|---|---|---|
| **Lucide** (`lucide-static`, MIT) | Excellent — 1.5–2px line art, exactly the design's weight | Good for cross, flower, heart-handshake, shield-check, users, truck, file; **no** casket, hearse, headstone, urn | ~1 MB, tree-shakeable to the dozen used |
| **Font Awesome Free 6** | Poorer — solid/filled, heavier than the design | Better semantics: `monument`, `cross`, `dove`, `place-of-worship`, `file-contract`, `hands-holding-heart` | ~1.5 MB self-hosted |
| **Commission/buy a funeral set** | Exact | Exact | Money and lead time |
| **Redraw the six properly** | Exact | Exact | My time, and they stay bespoke |

Recommendation: **Lucide for the general icons** (contact, why-choose-us, UI) **plus six
properly redrawn funeral pictograms** on Lucide's exact geometry — 24px grid, 2px stroke,
round caps — so they sit in the same family rather than looking imported. That is the only
option that is both consistent and actually depicts a hearse.

---

## Risks

| Risk | Mitigation |
|---|---|
| A reseller deletes a widget the design depends on and the page looks broken. | Widgets are removable but the *page* keeps rendering; nothing 500s. A "reset this page to the theme default" action per page, using the `default_pages` document. |
| Character limits that are too tight become the complaint instead of the fix. | Limits come from the design's measured wrap points, and every one is a number in one place that can be raised without touching a blade. |
| The theme's widget views drift from the default ones as features land. | `themes:doctor` (already planned) reports which default views each theme shadows and warns when a shadowed default has changed. |
| Seven new widgets is a lot of surface for the editor's property panel. | They share field vocabulary — eyebrow, heading, body, image, button — so the panel looks the same across all of them. |

## Deliberately not doing

- **A Service model.** Services are pages. A custom post type would give resellers a second
  editing surface with its own permissions and its own bugs, to express something `Page`
  already expresses.
- **Locking widgets to a page.** A reseller who wants the feedback bar on their About page
  should be able to put it there.
- **Per-widget colour controls on themed views.** The theme owns colour. Exposing it per widget
  is how a white-label site ends up with eleven shades of gold.
