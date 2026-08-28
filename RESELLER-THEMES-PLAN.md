# Reseller themes — implementation plan

> **Status: Phases 1, 2 and 4 are built, the base template — `basic` — is the current
> design, and Dignified is the second real template.** 632 tests pass
> (`tests/Feature/ResellerThemeTest.php`, `ThemeConformanceTest.php`,
> `ResellerShellLinksTest.php`, `ThemesDoctorTest.php`, `ThemePreviewTest.php`,
> `ThemePlanGatingTest.php`). Nothing is outstanding in this plan but the third template and
> the two Dignified logo files. See "What is built" below.
>
> Scope decided with the business owner: *template* themes (real blades, not just a different
> palette), applied to **the reseller's public site**, with a platform-authored catalogue that
> resellers can save their own variants of.

---

## What is built

| Piece | Where |
|---|---|
| Template on disk, cascading view locations | `app/Themes/{ThemeRegistry,ThemeManifest,ActiveTheme}.php` |
| `basic` — the current design, left where it was | `resources/views` itself; `themes/basic/` is a manifest and no blades |
| Theme = template + palette | `app/Models/Theme.php`, `themes` table, `resellers.theme_id` |
| Catalogue kept in step with disk | `app/Themes/ThemeCatalogue.php`, `themes:sync`, seed migration |
| Drift detection between a template and what it shadows | `app/Themes/ThemeShadows.php`, `themes:doctor` |
| Preview before apply, on the live site, visible only to them | `app/Themes/ThemePreview.php`, `ThemePreviewController`, `AnnounceThemePreview` |
| Per-theme plan gating, on applying only | `themes.minimum_tier_id`, `Theme::isAvailableTo()`, `/settings/themes` |
| Palette layered under the reseller's own values | `ThemeSetting::get()` — three layers |
| Applied where the tenant is bound | the three `ResolveReseller*` middleware |
| Reseller-facing gallery + nav entry | `/reseller/theme`, `MenuHelper` → "Theme" |

**Phase 3 changed shape twice.** The plan said to *copy* the visitor blades into
`themes/classic/` and leave `resources/views` as the default — fifteen identical pairs of
files on day one, fork rot before a second theme existed. The first attempt therefore *moved*
them into `themes/basic/` and registered that as a permanent view location. **That was
reverted.** It meant the platform's own website was being served from inside the reseller
theme system — the wrong dependency entirely.

What stands now: **`basic` *is* `resources/views`.** It carries a manifest so the catalogue
has a row to point at, and zero blades. The platform's host prepends nothing; a tenant's host
prepends their template, so the chain is `[chosen template] → resources/views`. A template
overrides only what it wants to change — Dignified overrides exactly one widget view
(`section-grid`), and differs otherwise by `--t-*` tokens and pseudo-element hooks.

Do not move the visitor blades back into `themes/basic/`. See `AppServiceProvider::boot()`.

---

The white-label promise is currently colour-deep. Two resellers can pick different hues and
fonts, but their sites have the same header, the same hero, the same card, the same footer,
in the same order. Anyone who has seen one recognises the next one. This is the plan for
letting them look like different companies.

---

## Drift: the failure the conformance suite cannot see

`ThemeConformanceTest` catches **breakage** — a template that 500s, drops its footer, or walks
a visitor onto the platform. It cannot catch **drift**, because a drifted template renders
perfectly. It shadows a view, the original is fixed six months later, and the template goes on
serving the version it was copied from. Nothing throws. The tests pass. The only evidence is a
funeral home quietly missing a fix everyone else got.

Dignified shadows **12** default views today (and brings 4 of its own). At ten templates that
is a hundred-odd silent forks.

**How it works.** Each template records, in its own `theme.json`, a fingerprint of the
`resources/views` **original** it was written against:

```json
"shadows": {
  "layouts/visitor.blade.php": "7726e3ae729de412",
  "components/home-header.blade.php": "c002372f06b53b39"
}
```

The fingerprint is of the *original*, never of the template's own copy — a template is
supposed to differ from what it replaces; that is the whole point of it. What matters is
whether the thing it was derived from has moved.

```
php artisan themes:doctor                     # check all; non-zero exit on drift
php artisan themes:doctor dignified           # check one
php artisan themes:doctor dignified --record  # re-baseline after reviewing the change
```

Four states are reported and all four fail: `drifted` (the original changed), `unrecorded` (a
shadow with no baseline, so drift in it can never be detected), `stale` (a baseline for a view
the template no longer shadows — worse than a missing one, because it reads as "checked"), and
any blade under `themes/basic/` at all. Views a template brings itself are listed but never
failed: they shadow nothing and cannot drift.

Catalogue disagreements — a template on disk with no row, a row with no directory — are
warnings, not failures. They are database state, and the DB that CI runs against is not the one
serving anybody's site. `--strict` promotes them for a check running against a real one.

**Deliberate deviation from the original plan.** The plan said to record fingerprints at
`themes:sync` time. That would not work: `themes:sync` runs from a deploy migration, so every
deploy would re-baseline the drift it was meant to catch and the check would compare the
originals against themselves forever. The baseline has to be committed by the person who
reviewed the template against the changed original, so recording is an explicit, separate act.
`themes:sync` writes nothing to disk.

Drift is asserted in `ThemeConformanceTest` too ("stays in step with the default views it
shadows"), so it fails the suite rather than only a CI step somebody has to remember to wire
up. **When it fails:** read the diff of the changed original, decide whether the template needs
the same change, apply it if so, then `themes:doctor <template> --record` and commit.

---

## Plan gating

Decided with the product owner: **a per-theme minimum tier.** Each catalogue row names the
lowest `reseller_tiers.sort_order` that may *apply* it (`themes.minimum_tier_id`, nullable,
nullOnDelete). An admin sets it per theme on `/settings/themes`. Chosen over a
tiers-that-allow-themes boolean because it prices a *particular* theme without a schema change
each time, and over admin-assigns-and-locks because that would have retired the gallery, the
preview and "save this look" all at once.

**Gating applies to the apply action and nothing else.** Three consequences, all deliberate:

- **A gated theme stays in the gallery and stays previewable.** Nobody upgrades for something
  they have never been shown. The card reads "On Professional and above" where the apply
  button would be, and keeps its Preview button.
- **Gating never moves a live site.** A reseller already running a theme keeps it if their tier
  later drops, or if the theme is gated above them afterwards. The alternative is a funeral
  home's site changing design because a subscription lapsed — and they would hear about it from
  a grieving family rather than from us. Same promise unpublishing already makes.
- **Ungated is the default and stays it.** Every existing row is null, so shipping this changed
  nothing for anyone. `ThemeCatalogue::sync()` rewrites name/template/tokens from the manifest
  and does not touch the minimum, or every deploy would unlock the paid themes.

A reseller on no tier at all counts as below every tier — "not on a plan" is the most
restrictive state, not an exemption. A reseller's own saved themes are never gated: they are
built out of what that reseller was already running. A minimum pointing at a deleted tier reads
as ungated, matching the FK's nullOnDelete, because losing a restriction is recoverable in the
admin screen while silently locking everyone out is found by support ticket.

The admin card shows how many sites already on the theme sit below the line it is being given,
before and after the change — the number that decides whether the gate was the right call.

---

## Two things that looked cosmetic and were not

**The offset frame was painting over the photograph.** `.t-figure::after` follows the `<img>`
in DOM order, and positioned siblings with no `z-index` paint in that order — so the crimson
rectangle drew its top and left edges straight across the picture while the gold one, being a
`::before`, behaved. It read as a printing error, and only on one of the two. The photograph
now carries `z-index: 1` so only the L of each shows, which is what the comment beside it
always claimed. Insets tightened from `1.25rem` to `0.875rem` at the same time.

**The theme gallery was serving a broken image.** Dignified's manifest declared
`screenshot: preview.webp` and the template shipped none, so the card on the one screen whose
whole job is showing what a theme looks like rendered a broken tile — silently, because the
page still returned 200. Three fixes, because one would not have held:

- `ThemeManifest::screenshotUrl()` checks the file is actually on disk, so a missing or
  mistyped screenshot degrades to the wireframe instead of a broken image.
- The stale `screenshot` key is gone from Dignified. Per the note in
  `partials/theme-preview.blade.php`, templates are *meant* to fall back to the wireframe —
  a real screenshot shows one tenant's palette to every other tenant.
- The wireframe was drawn from `default_home_blocks`, which is a hand-written summary and had
  drifted: Dignified's still claimed hero / features / CTA long after its front page became six
  section widgets. `ThemeManifest::homeShape()` reads the shipped page document instead, and
  the partial learned to draw `section_banner` / `_split` / `_grid` / `_contact` — without
  which every card looked identical for exactly the themes people are choosing between.

Both are asserted in `ThemeConformanceTest`, parameterised over templates, so template three
inherits the coverage.

---

## Preview before apply

Applying changed the public site immediately, for everyone, with no way back except applying
the old one and hoping nothing was seeded in between. Preview is the way out of that: the
reseller's *real* site, on its real address, wearing a theme only they can see.

**Not `?theme=`.** It would be four lines and is wrong twice over — it lets anyone re-skin a
stranger's public site with a link, and it hands a cache key to any proxy in front of us, so
one visitor's `?theme=` response gets served to the next hundred who asked for the plain URL.
The preview lives where a stranger's URL cannot reach it: the previewer's own session.

**The handoff is the hard part.** The session that carries it is on the *public site's* host;
the person setting it is on the dashboard, which is a different host — and a different domain
once they verify their own. `SESSION_DOMAIN` is null, so cookies are host-only and a flag set
on the dashboard is invisible on the site. So:

```
POST /reseller/theme/preview   (their staff, their theme)
  → mints a 5-minute *relative* signed URL addressed at their site
  → GET {their site}/theme-preview/{id}   (unauthenticated — the signature is the credential)
      → re-checks the theme against the tenant the HOST resolved, sets the session flag
      → redirects to their home page
```

Relative signatures because the link is minted on our host and spent on theirs; an absolute
one would be computed over the wrong host and never validate. Two route registrations, for the
same reason `login`/`register` have two: the `/r/{slug}` fallback keeps the tenant in the path.

**Both halves swap.** `ThemePreview::resolveTemplate()` is the single seam the three
`ResolveReseller*` middleware call instead of `$reseller->templateSlug()`, and it primes
`ThemeSetting::useThemeTokens()` as well — a preview that changed the blades but kept the
applied palette would show Dignified in somebody else's brand. The reseller's own hand-set
colours still win, exactly as they would if it were applied, so what they see is what they get.

**The bar is welded onto the response, not into a layout** (`AnnounceThemePreview`, injected
before `</body>`). A template that forgot to include it would look exactly like a site already
changed, which is the one mistake this feature exists to prevent — and templates are written by
people who have never read that file. The same middleware marks previewed responses `no-store`.

Nothing here writes to `resellers.theme_id`. A preview that could persist would be an apply
with a worse name, and there is a test that says so.

**Gotcha worth keeping.** Laravel hands scalar route parameters to a controller
*positionally*, not by name. `enter()` took `string $theme` and was handed the reseller's slug
on the `/r/{slug}` route — every preview 403'd, on the one route development actually uses. It
reads `$request->route('theme')` now.

---

## The recurring bug: a white-labeled site linking home to us

Found and fixed five times now, in five different disguises. Worth stating once so the sixth
is recognised on sight.

`url('/')` and `route('home')` do not *fail* on a reseller's site — they quietly answer with
the platform's address, because `AppServiceProvider` calls `URL::forceRootUrl(config('app.url'))`
so subdirectory installs work. A grieving client of a funeral home clicks the funeral home's
own logo and lands on our marketing site.

**The rule:**

> `route()` / `url()` for **application screens** that exist on every host.
> `App\Support\SiteUrl::to()` for anything on the **tenant's public site**.

**Why it keeps looking fixed.** On a real reseller host, `ResolveResellerByHost` re-roots the
whole URL generator at the request's own host, so a bare `route('home')` there already answers
with the reseller's address — every link on the page is theirs for free. The leak survives
only where that re-rooting deliberately does not happen: the **`/r/{slug}` path fallback**,
which runs on the platform's host, and which subdirectory and development installs actually
serve. Any test for this bug class must ride the fallback host, or it asserts nothing.

**Fixed (Aug 2026):** the logout redirect (`AuthenticatedSessionController`), the cancelled-
payment CTA (`PaymentController`), and the logo / "back to home" links on the sign-in,
sign-up, guest-layout, memorial-signup, unsubscribed and payment-result blades.
`resources/views/pages/errors/error-404.blade.php` carried the same bug and was deleted — no
route, controller or view referenced it; `resources/views/errors/404.blade.php` is the live one.

**Covered by** `tests/Feature/ResellerShellLinksTest.php` — the screens outside the theme
system — and `ThemeConformanceTest`'s "never walks a visitor from a reseller site onto the
platform" for the themed pages.

**Known limit, not fixed.** Only `login` and `register` have `/r/{slug}` routes. Paying,
starting a memorial, resetting a password and unsubscribing have none, so on the fallback host
no tenant is bound and those pages are the platform's. On a real reseller host all of them are
tenant-bound and correct. Closing that gap means adding fallback routes, not more `SiteUrl`
calls.

---

## What already exists, and why this is not a rewrite

`RESELLER-APPEARANCE-PLAN.md` built the whole tenant-aware settings pipeline, and it works:

- `App\Helpers\ThemeSetting::get()` resolves any appearance key as *this tenant's value, else
  the platform's*, with the tenant bound by four different middleware and a sensible auth
  fallback. It already distinguishes **branding tenancy** (`tenant()` — follows the user)
  from **content tenancy** (`siteTenant()` — follows the host). That distinction is the one
  this plan needs, and it is already made.
- `BrandingHelper::brandColorCss()` emits ~60 custom properties derived from a handful of
  hexes; `AppearanceHelper::css()` emits fonts, six typography roles and text colours. Both
  read through `ThemeSetting`, so **a new layer inserted there themes everything at once.**
- `reseller_settings` stores per-tenant overrides with the same key vocabulary as
  `system_settings`, so nothing new has to be learned or validated differently.
- `pages` and `menus` already carry `reseller_id`, and `ResellerSiteProvisioner` gives a new
  reseller a working site on creation.

None of that changes. This plan adds **one resolution layer below the tenant override**, and
**one view-location layer above the default blades**.

## The surface being themed

The reseller public site is smaller than it feels — 24 blades, ~1,550 lines:

| Group | Files | Lines |
|---|---|---|
| Shell — `layouts/visitor`, `components/home-header`, `components/visitor-footer` | 3 | 422 |
| Visitor pages — home, cms-page, page-layout, about, pricing, contact, privacy, terms | 8 | 414 |
| Site blocks — hero, memorial-showcase, features-grid, cta-banner | 4 | 256 |
| Page-builder widgets | 8 | 392 |
| Renderers — `site/layout-renderer`, `page-layout/renderer` | 2 | 64 |

Everything funnels through three seams that are already indirection points:
`layouts.visitor` for the shell, `SiteBlockRegistry::classForType()->viewName()` for home
blocks, and `WidgetRegistry` for builder widgets. A theme overrides views at those seams; it
does not need a controller of its own, a route of its own, or a second copy of any logic.

**Out of scope, deliberately:** the memorial page, the reseller dashboard and the embed
widget. They keep today's single template with token-level theming. See "The seam we are
accepting" below — this is a real consequence, not an oversight.

---

## Phase 0 — Prove that "no theme" changes nothing

The one thing that must never happen is an existing reseller's site moving because we shipped
a theme engine they never opted into.

1. Characterisation tests over the visitor surface: render home (block layout *and* fallback),
   about, pricing, contact, both legal pages and a builder-laid-out page — as the platform,
   and as a reseller — and assert on the rendered markup. These are the regression net for
   every phase below, so they land before any of it.

---

## Phase 1 — Theme resolution, with only the default theme in existence

2. `themes/{slug}/` — a directory that **mirrors the `resources/views/` paths it
   wants to replace**, and only those. A theme that changes the header and the hero is two
   files. There is no scaffolding to copy and no obligation to be complete.
3. `App\Themes\ThemeManifest` — reads `themes/{slug}/theme.json`:

   ```json
   {
     "name": "Chapel",
     "description": "Serif, generous margins, one column.",
     "screenshot": "preview.webp",
     "css": "theme.css",
     "tokens": { "branding.primary_color": "#3f4a5a", "appearance.font_heading": "Fraunces" },
     "default_home_blocks": [ { "type": "hero", "props": {} } ]
   }
   ```

4. `App\Themes\ThemeViewResolver::apply(?string $slug)` — prepends the theme directory to the
   view finder, so a view the theme provides wins and one it omits falls through to
   `resources/views` unchanged. Two hazards to handle *here*, once, rather than discovering
   them in production:
   - The finder caches resolved names in `$views`, and `prependLocation()` mutates a shared
     instance. `flush()` must be called whenever the applied theme differs from the last one
     applied in this process — otherwise a queued job, a test, or any Octane worker serves
     one tenant's header to the next.
   - Compiled blade output is keyed by full file path, so two themes' copies of
     `site-blocks/hero.blade.php` do **not** collide. Nothing to do; worth writing down,
     because it is the thing everyone worries about first.
5. Middleware applies the theme from `ThemeSetting::siteTenant()` — **the host, not the
   viewer.** A reseller's own staff browsing the platform's site must see the platform's
   theme; this mirrors the content/branding split `ThemeSetting` already documents.
6. Migration `create_themes_table`: `reseller_id` (nullable — null means the platform
   catalogue), `name`, `slug`, `template` (which `themes/` folder), `tokens` (json),
   `is_published`, `created_by_user_id`. Unique on `(reseller_id, slug)`.
7. Migration: `resellers.theme_id`, nullable, `nullOnDelete`.
8. `php artisan themes:sync` — idempotent, reads every `theme.json` and upserts the platform
   catalogue row for it. The folder stays the source of truth for *templates*; the table holds
   *selectable instances*.

At the end of Phase 1 there is exactly one template — `default`, which is the current
`resources/views` and is applied by prepending nothing. Every reseller has `theme_id = null`.
**Phase 0's tests must pass byte-for-byte.** If they do not, stop here.

---

## Phase 2 — Themes carry tokens, not just markup

9. Insert one layer into `ThemeSetting::get()`, between the tenant override and the platform
   default:

   1. `reseller_settings` / column alias — what this reseller explicitly set
   2. **active theme tokens** — new
   3. `SystemSetting` — the platform default

   A theme therefore ships a coherent palette *and* a reseller can still overrule any single
   value of it, which is exactly the relationship the Appearance page already implies.
10. This creates one confusing state, so name it in the UI rather than letting people find it:
    a reseller who tuned 20 colours and then applies a theme keeps their 20. The Appearance
    page already computes `$overrideCount`; surface it on the theme page as *"12 of your own
    colours are overriding this theme"* with a reset. Applying a theme must **not** silently
    delete work someone did.
11. Theme tokens are validated on sync against `AppearanceKeys::resellerWritable()` — a
    `theme.json` cannot introduce a key the appearance vocabulary does not know, and every
    value still passes `sanitizeHex()`. These are interpolated into a `<style>` block.

---

## Phase 3 — Two real templates, so the mechanism is exercised

12. ~~Copy today's visitor blades into a `classic` template.~~ **Done differently:** they were
    *moved* into `themes/basic/`, which is registered as a permanent view location.
    One copy of every blade, a cascade of `[chosen template] → basic → resources/views`, and a
    template that overrides one file inherits the other fourteen. See "What is built" above.
13. Author a second template that is genuinely a different design, not a recolour: different
    shell, different hero composition, different card treatment, different rhythm. One theme
    proves nothing; the second is what finds the assumptions baked into the seams.
14. Theme CSS: a theme's optional `theme.css` is added to the Vite input list and loaded
    alongside `app.css`. Tailwind's `@source '../**/*.blade.php'` in `resources/css/app.css`
    scans `resources/**` only, and templates live at `<project>/themes` — so `app.css` carries a
    second `@source '../../themes/**/*.blade.php'` line. Without it a template's blades generate
    no utilities at all and the page renders unstyled, which is why it is called out here.

---

## Phase 4 — The reseller picks one

15. `/reseller/theme` — a gallery of catalogue themes with screenshots, the active one marked,
    and each reseller's own saved themes alongside.
16. **Preview before apply.** A `?theme=` preview must be gated to this reseller's own signed-in
    staff — a session flag set by a POST, or a signed URL. Open preview by query string is a
    defacement vector and a cache-poisoning one, and it will be found.
17. "Save as my theme" — writes a `themes` row with this `reseller_id`, pointing at a platform
    template, carrying their current tokens. This is what "resellers author themes too" means
    in practice: they compose template + palette + type and name the result. **Resellers do not
    author blades** — that is arbitrary code execution on a shared host, and no UI makes it
    safe.
18. Switching template keeps tokens, so trying a different layout does not cost someone their
    palette.

---

## Phase 5 — Admin, gating, defaults

19. Admin catalogue management: publish/unpublish a theme, set the platform's own, preview any
    reseller's site as it currently renders.
20. **Not tier-gated.** Same argument the appearance plan made and won: theming is the core
    white-label promise already being sold, and a lower tier being shown a locked gallery makes
    the pitch false. If a flag is wanted later it is one column.
21. `default_home_blocks` from the manifest feeds `ResellerSiteProvisioner`, so a reseller who
    applies a theme before building a home page gets that theme's intended arrangement rather
    than the platform's hero → showcase → features → CTA. This is also why `site_layouts` does
    **not** need `reseller_id`: the reseller home page already falls through to
    `Page::getBySlugForReseller(home)->hasLayout()`, and the theme supplies the default for
    everyone who has not built one.

---

## The seam we are accepting

A themed reseller site links to memorial pages that are **not** themed — the memorial page
keeps the single platform template with token-level branding. A visitor clicking a name on a
Chapel-themed directory lands on a page whose shape they have seen on every other memorial
site. Colours, fonts and logo will follow them across; layout will not.

That is the deliberate scope decision, and it is defensible — the memorial page is the most
tested, most feature-dense surface in the app, and forking it N ways means every future
feature ships N times. But it should be a stated trade-off in the sales conversation, not a
surprise a reseller discovers after signing. If it becomes the top complaint, the mechanism
built here extends to it without redesign: `themes/{slug}/pages/memorials/` already
resolves.

## Risks worth naming now

| Risk | Mitigation |
|---|---|
| A theme shadows a default view, the default later changes, and the theme silently keeps the old behaviour — the classic fork rot. | `php artisan themes:doctor`: list every default view each theme shadows, store the shadowed file's hash in `theme.json` at sync, warn when it has moved. This is the difference between a theme system that survives a year and one that quietly breaks. |
| View-finder state leaking between tenants in a long-running process. | One `apply()` path, `flush()` on change, reset after the request. Covered by a test that renders two different tenants in one process. |
| Preview used as an open redirect / defacement. | Signed or session-gated, staff-only. |
| A theme's markup drops something a controller assumes exists. | Phase 0 tests run against every theme, not just the default. |
| Adding a new visitor page means touching every theme that overrides the shell. | Themes override minimally by design, and `themes:doctor` reports coverage gaps. |

## Not doing

- **Reseller-authored blades or CSS uploads.** Arbitrary markup on a shared host, executed in
  our layout, with our session cookie in scope.
- **Per-memorial themes.** A family choosing a layout is a different product decision, and it
  would put theme selection in the hands of people who did not buy the white label.
- **Theming the reseller dashboard.** Staff tooling benefits from looking the same everywhere;
  it already carries their logo and palette, which is the part that matters.
