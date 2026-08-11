# Reseller appearance & white-label — implementation plan

> **Status: Phases 0–3 are built.** 139 tests pass (`php artisan test`); the one failing test,
> `ExampleTest`, fails on a clean checkout too — it requests `/` relatively, which the test
> client resolves against the `/Forever` subdirectory root. Phase 3 item 32 and all of Phase 4
> are **not started** — see "Still open" at the bottom.

Scope decided with the business owner:

- **Full colour + font set** — everything the admin Appearance page exposes. Font *uploads*
  stay admin-only; resellers pick from the existing catalogue plus whatever the admin has
  uploaded.
- **Free for every reseller** — no `feature_custom_appearance` tier flag. Theming is the core
  white-label promise already being sold; gating it would make the existing Branding page's
  claim false for lower tiers.
- **One page** — the existing `/reseller/branding` is folded into a single Appearance page.

---

## Why this is small

Every colour and font in the app already funnels through two helpers:
`App\Helpers\BrandingHelper` and `App\Helpers\AppearanceHelper`. Both read
`SystemSetting::get()` directly. Redirect those reads through one tenant-aware resolver and
**all ~30 values become per-reseller in a single pass** — no duplicated form, no duplicated
CSS generation, no second validation vocabulary.

`SystemSetting::getDefaults()` is already a complete registry of every key with its group and
type. The resolver reuses it rather than redeclaring anything.

---

## Phase 0 — Stop printing a URL that does not exist

The reseller address is currently assembled inline in nine views as
`{{ $reseller->slug }}.{{ config('reseller.domain') }}`, with no relation to where the app
actually runs. `RESELLER_APP_DOMAIN` is unset, so it resolves to the literal
`foreverloved.com` while `APP_URL` is `http://localhost/Forever`.

1. Set `RESELLER_APP_DOMAIN` in `.env`.
2. Add to `App\Models\Reseller`:
   - `publicHost(): string` — verified custom domain, else `{slug}.{base}`.
   - `publicBaseUrl(): string` — scheme + host, or the dev fallback below.
   - `subdomainRoutingAvailable(): bool` — false when `APP_URL` carries a path, or when its
     host is not the reseller base domain or a subdomain of it.
3. Register a dev fallback route `/r/{reseller}/{slug}` (same controller + `ResolveReseller`
   middleware) used by `publicBaseUrl()` when `subdomainRoutingAvailable()` is false — so
   reseller memorial pages are actually viewable on a subdirectory install.
4. Route `Memorial::publicUrl()` and all nine view sites through the accessor. Fix the
   dashboard copy button, which hardcodes `https://`.
5. Where subdomain routing cannot work in the current environment, **say so** — on the
   reseller dashboard and in admin Reseller Settings — instead of printing a confident dead
   URL. Point at `RESELLER-PRODUCTION-CHECKLIST.md` §1–2.

**Files:** `app/Models/Reseller.php`, `app/Models/Memorial.php`, `routes/web.php`,
`resources/views/pages/reseller/{dashboard,settings,branding,memorials}.blade.php`,
`resources/views/pages/admin/{resellers,reseller-show,reseller-settings}.blade.php`, `.env`.

---

## Phase 1 — Correct attribution (prerequisite)

`Reseller\DashboardController::storeMemorial()` is the only writer of `reseller_id` on a
memorial — its own comment says so. A reseller's clients hold the `user` role and can use the
normal `/memorials` and `/create-memorial` flows, and anything they create that way is
invisible to the reseller and **uncounted against the tier allowance and storage cap**.

Theming is meaningless on memorials the tenant does not own, so this lands first.

6. In `MemorialController::store()` and
   `MemorialSignupController::createMemorialFromSession()`, stamp `reseller_id` and
   `original_reseller_id` from the owning user's `reseller_id`.
7. Enforce `Reseller::hasMemorialCapacity()` on both paths, with the same message
   `storeMemorial()` uses.
8. `php artisan memorials:backfill-reseller-attribution` — set `reseller_id` on existing
   memorials whose owner belongs to a reseller and whose `reseller_id` is null. `--dry-run`
   first, matching the existing `memorials:backfill-photo-sizes` convention.
9. Tests: a client-created memorial counts against allowance, appears in the reseller's list,
   and resolves on their subdomain.

---

## Phase 2 — Reseller Appearance

### 2a. Storage

10. Migration `create_reseller_settings_table`: `reseller_id` (FK, cascade delete), `key`,
    `value`, `type`, unique on `(reseller_id, key)`. Deliberately mirrors `system_settings`
    so the key vocabulary is identical and nothing new has to be learned or validated
    differently.
11. `App\Models\ResellerSetting` with `get(Reseller|int, string $key, $default)`,
    `set(...)`, `getByGroup(...)`, cached per tenant under `reseller_settings.{id}` and
    forgotten on write — same shape as `SystemSetting::getAllCached()`.

**The three existing columns stay.** `resellers.logo_path`, `favicon_path` and
`primary_color` are already referenced by `BrandingHelper::tenantOverride()`, the admin views,
the branding form and the passing tests. Rather than migrate them, the resolver treats them
as **aliases** for `branding.logo_path`, `branding.favicon_path` and
`branding.primary_color`. No data migration, nothing existing breaks.

12. Add `resellers.logo_dark_path` — resellers currently have one logo for both themes, so a
    dark logo is invisible on the dark sidebar.

### 2b. The resolver

13. `App\Support\ThemeSetting::get(string $key, mixed $default = null)`:
    - Resolve the active tenant exactly as `BrandingHelper::tenantOverride()` does today —
      `app(Reseller::class)` when bound (public subdomain / custom domain / reseller staff
      routes), else `auth()->user()?->reseller`.
    - Column aliases first, then `ResellerSetting`, then `SystemSetting`. A blank or unset
      reseller value falls through to the platform, so an untouched reseller looks exactly
      as it does today.
14. Replace every `SystemSetting::get()` call in `BrandingHelper` and `AppearanceHelper` with
    `ThemeSetting::get()`. This is the change that makes brand, accent, page background, all
    four button roles in light *and* dark, CTA banner, CTA scrim, `default_theme`, body and
    heading fonts, the six per-element type roles and the visitor text colours all
    tenant-aware at once.
15. `AppearanceHelper::customFonts()` keeps reading `SystemSetting` — uploads stay
    platform-wide, and resellers select from that catalogue. Font *selection* is per-tenant;
    the files are not.
16. Delete `BrandingHelper::tenantOverride()` once the resolver subsumes it, and fix
    `logoUrl(?string $variant = 'light')`, whose `$variant` parameter is currently ignored.

### 2c. Shared form, two pages

17. Extract the admin Appearance page's sections into partials under
    `resources/views/pages/settings/appearance/` — `_fonts`, `_colors-brand`,
    `_colors-background`, `_colors-buttons`, `_colors-cta`, `_type-roles`, `_text-colors`.
    Both the admin page and the reseller page include them, so the two cannot drift.
    `pages/settings/partials/color-field.blade.php` is already reusable as-is.
18. `App\Http\Controllers\Reseller\AppearanceController` — `edit` / `update`, plus
    `resetToPlatform` (delete this tenant's overrides). Reuse the admin controller's
    validation arrays by promoting `TEXT_COLOR_KEYS`, `BRANDING_COLOR_KEYS` and
    `BRANDING_PERCENT_KEYS` to a shared `App\Support\AppearanceKeys` class. Writes go to
    `ResellerSetting`, scoped to `$request->user()->reseller` — never to `SystemSetting`.
19. `resources/views/pages/reseller/appearance.blade.php` — logo, dark logo, favicon and the
    full colour/font set. Each field shows the inherited platform value as its placeholder,
    so "not set" reads as "inherited", not as "broken".

### 2d. Nav and routes

20. `routes/web.php`, reseller group: `GET/PUT /reseller/appearance`,
    `DELETE /reseller/appearance/reset`. Keep `/reseller/branding` as a 301 to
    `/reseller/appearance` so existing links survive; retire
    `Reseller\BrandingController` and `pages/reseller/branding.blade.php`.
21. `MenuHelper::getResellerMenuGroups()` — replace the **Branding** entry under "Your
    business" with **Appearance** (`'icon' => 'appearance'`, matching the admin entry).
22. Update the "More Settings" card on `pages/reseller/settings.blade.php`, which links to
    Branding.

### 2e. Tests

23. A reseller override wins over the platform value; an unset one inherits.
24. One reseller's appearance never leaks into another's, or into the platform's own pages.
25. A non-hex value is rejected — these are interpolated into a `<style>` block, and
    `BrandingHelper::sanitizeHex()` is the existing floor.
26. A reseller cannot write `SystemSetting` through the reseller endpoint.
27. Font selection is restricted to the catalogue, mirroring the admin's
    "Pick a font from the list or upload it first" check.

---

## Phase 3 — Make the white-label actually white-label

28. `layouts/embed.blade.php` hardcodes its fonts and colours and carries no branding at all.
    Emit `BrandingHelper::brandColorCss()` + `AppearanceHelper::css()` and add the reseller's
    logo and favicon. `feature_embedding` is a billable flag whose current output is a
    generic grey iframe.
29. Bind the tenant in `WidgetController::show()` (`app()->instance(Reseller::class, ...)`)
    so the resolver sees it — the widget route has no reseller middleware today.
30. Per-memorial embed-snippet copy button on the reseller Memorials list. The snippet
    currently makes the reseller hand-type a slug into a `MEMORIAL-SLUG` placeholder.
31. Constrain platform-only routes so a reseller host stops serving the platform site.
    Only `/{slug}` is registered under the reseller domain group; `/`, `/pricing`, `/about`
    and the rest match any Host, so `acme.foreverloved.com/` currently serves **the
    platform's homepage with platform branding**. Middleware on a reseller host that
    redirects non-memorial paths to the apex is the smallest fix.
32. Reserve the platform's public path segments (`about`, `pricing`, `contact`,
    `find-memorial`, `login`, `dashboard`) against memorial slugs — those routes are
    registered earlier and win on every host, so such a memorial is unreachable.

---

## Phase 4 — A reseller's own website (separate, larger)

`Page`, `SiteLayout` and `Menu` have no `reseller_id`; the page builder, site layout editor
and menu system are single-tenant, and `Admin\PageController` explicitly scopes
`whereNull('reseller_id')`. A reseller subdomain therefore has no homepage, no marketing
pages and no navigation.

33. `reseller_id` on `pages`, `site_layouts`, `menus`, `menu_items`, `seo_entries`.
34. Tenant-scoped page builder and menu editor under `/reseller/*`, reusing
    `PageLayoutService` / `SiteLayoutService` / `WidgetRegistry` unchanged.
35. A real root route per reseller host, backed by that tenant's home `SiteLayout`.

Not started until Phases 0–3 land — it is a larger surface than all of them combined.

---

---

## Still open

Everything above through Phase 3 item 31 is built. Item 31 landed as
`App\Http\Middleware\ResolveResellerByHost` on the web group: it resolves the tenant from the
Host header for *every* route (not just the two that declare a domain), so a reseller's own login
screen and dashboard carry their branding, and it redirects the platform's marketing pages
(`about`, `pricing`, `contact`, `find-memorial`) back to the reseller's front page. The home page,
public directory and memorial search are all tenant-scoped in both directions, and the header and
footer drop the platform's marketing navigation on a reseller site — including admin-defined
Menu items, withheld in `AppServiceProvider`'s view composer.

What is deliberately **not** done:

**Reseller-authored navigation.** A reseller cannot yet *add* their own nav or footer links —
the platform's are withheld on their site, leaving a deliberately minimal Home / Create Memorial
/ legal set. Giving them their own menus is Phase 4 work, since it needs per-reseller Menu rows.

**Notification emails still carry the platform name.** `NotificationService` reads
`branding.app_name` directly. `SiteShareMetaHelper::appDisplayName()` is tenant-aware and would
work there, but mail is frequently sent from queued jobs where no tenant is bound, so doing this
properly means passing the reseller explicitly rather than relying on ambient state.

**Phase 3 item 32 — platform path segments can shadow a memorial slug.** A memorial slugged
`about`, `pricing` or `contact` is unreachable, because those routes are registered earlier and
win on every host. Needs adding to `Memorial` slug generation as reserved words.

**Phase 4 — a reseller's own website.** Untouched. `Page`, `SiteLayout` and `Menu` still have no
`reseller_id`.

**Deliberately not done: blocking client self-serve creation at the tier allowance.** The plan
originally said to enforce `hasMemorialCapacity()` on the client-facing creation paths, the way
`Reseller\DashboardController::storeMemorial()` does for reseller staff. On reflection that is
the wrong call: the platform already bills overage (`overageProfiles()`, `overageAmount()`,
snapshotted onto every `ResellerPayment`), so exceeding the included allowance is an expected,
invoiced state — not an error. Hard-blocking would mean telling a grieving family their funeral
home has run out of quota. Attribution alone closes the actual defect, which was that overage
went **uncounted**. The `--dry-run` backfill reports any reseller the correction pushes into
overage so it can be discussed rather than appearing unannounced on an invoice. Worth an
explicit decision: the existing hard block on reseller *staff* creation is now the inconsistent
one, and could reasonably become a warning instead.

---

## Also worth fixing while in here

| Finding | Where |
|---|---|
| A reseller's slug can never be changed by anyone — `update()` accepts only `name` + tier. A rebrand means a permanently wrong subdomain. | `Admin\ResellerController::update` |
| `verifyDomain()` marks a domain verified with no DNS check and no audit trail, unlike `recordPayment()` which records `recorded_by_user_id`. | `Admin\ResellerController::verifyDomain` |
| `domains.target_host` validates as bare `string\|max:255` — a typo is handed to every reseller as DNS instructions. | `Admin\ResellerSettingsController::update` |
| Invited clients get two different account states: `Hash::make(random(32))` vs `password => null`. | `Reseller\ClientController::store` vs `Reseller\DashboardController::storeMemorial` |
| `EmbedFrameHeaders` sets `frame-ancestors *` with no per-reseller origin list — any site can embed any reseller's memorial. | `app/Http/Middleware/EmbedFrameHeaders.php` |
| `www.{base}` matches `{reseller}` = `www` in the subdomain group, so `ResolveReseller` 404s that host on `/{slug}`. `reservedSlugs()` stops it being *claimed*, but not *matched*. | `routes/web.php:314` |
| Admin reseller-show says a custom domain "is set up by the reseller from their own settings", but a tier without `domain_routing` shows them a locked card and admin has no way to set one for them. | `pages/admin/reseller-show.blade.php:336` |
| §6 "Known gap" claims no client invite is sent. Invites now send from all three creation paths — the doc contradicts the code. | `RESELLER-PRODUCTION-CHECKLIST.md` |
