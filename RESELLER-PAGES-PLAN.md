# Reseller standard pages — implementation plan

> **Status: all eight phases built.** 289 tests pass, 18 of them new
> (`tests/Feature/ResellerStandardPagesTest.php`). Decisions below were agreed with the
> business owner before implementation and are all in place.
>
> Three things surfaced during the work that were not in the original plan:
>
> 1. **`redirect('/')` sent reseller visitors to the platform.** `AppServiceProvider` calls
>    `URL::forceRootUrl(config('app.url'))` to make subdirectory installs work, so every
>    generated path is rooted at *our* address. `ResolveResellerByHost` redirected a disabled
>    page with `redirect('/')` — landing the visitor on the platform's homepage, the exact
>    hand-off its own comment says it exists to prevent. Fixed by building the target from the
>    request. The existing test asserted the bare `'/'` and so passed throughout.
> 2. **`ThemeSetting::tenant()` is wrong for page content.** It falls back to the signed-in
>    user's own reseller, which is right for branding and wrong for pages: a reseller's staff
>    would have been served their own About on the *platform's* domain, with no way to read
>    ours. Added `siteTenant()` / `siteTenantId()`, which follow the host only.
> 3. **`sellableTo()` was latent, not live** — see the corrected note under "What blocks it".

The brief: *"these people should have all the other pages, only that they can be disabled or
enabled."*

---

## What a reseller actually has today

Of the seven standard pages the platform ships, a reseller gets **one**.

| Page | URL | On a reseller host today | Built from |
|---|---|---|---|
| Home | `/` | **Theirs** — own layout, own memorials | `visitor-home` page + page builder |
| About | `/about` | Redirects to `/` | `about` page (content *or* layout) |
| Pricing | `/pricing` | Redirects to `/` | `pricing` page + live plans |
| Contact | `/contact` | Redirects to `/` | `contact` page + form handler |
| Find Memorial | `/find-memorial` | Redirects to `/` | directory query |
| Privacy Policy | `/privacy-policy` | **Serves the platform's** | `privacy-policy` page |
| Terms of Use | `/terms-of-use` | **Serves the platform's** | `terms-of-use` page |
| Custom pages | `/{slug}` | **Theirs** | page builder |

Four bounce to their homepage. Two serve *our* legal text on their domain.

The clearest evidence this is a real gap: **reseller #1 has a page called `about-us`.** They
wanted "About", `reservedSlugs()` refused, and they worked around it.

---

## What blocks it

1. **`Page::reservedSlugs()`** lists `about`, `pricing`, `contact`, `privacy-policy`,
   `terms-of-use`, `find-memorial` — so no reseller can own those slugs.
2. **`ResolveResellerByHost::PLATFORM_ONLY_PATHS`** blanket-redirects four of them on every
   reseller host. It was the right fix for "our marketing on their domain"; it is now the
   thing standing in the way of their own equivalents.
3. **`Page::getBySlug()`** is hardcoded to `whereNull('reseller_id')`. Every visitor
   controller resolves through it, so it can only ever find the platform's page.
4. Two scoping bugs that only surface once tenants have these pages:
   - `MemorialDirectoryController` hardcodes `whereNull('reseller_id')` → a reseller
     directory would list **zero** of their own memorials.
   - `SubscriptionPlan::sellableTo()` scopes by the **viewer's** `reseller_id`, not the
     host's → a logged-out visitor on a reseller's pricing page would be shown **our** plans.
     Latent rather than live: registration on a reseller host stamps `reseller_id` on the new
     account, so the signup flow is correct today, and `/pricing` currently redirects. It
     becomes reachable the moment Phase 5 serves that page to anonymous visitors.

---

## Design

### Enable / disable

`pages.is_published` already carries this. Formalise it with a `StandardPages` catalogue
(slug → title, kind, starter content) and split the reseller Pages screen in two:

```
Standard pages              Custom pages
──────────────────          ─────────────────────────
Home           [on]         Our Services  [edit] [x]
About          [on]         Grief Support [edit] [x]
Pricing        [off]
Contact        [on]         [+ Add page]
Find Memorial  [off]
Privacy Policy [on]  *
Terms of Use   [on]  *
```

- **On** → create the row if absent (seeded from a starter) and publish.
- **Off** → unpublish. Content is kept, never destroyed, so re-enabling restores their work.
- **Disabled** → the URL redirects to their homepage, exactly as today.
- `*` Legal pages cannot be switched off (see decisions).

### Resolution

`Page::getBySlug()` gains a tenant argument — collapsing it and `getBySlugForReseller()` into
one resolver — and the visitor controllers pass `ThemeSetting::tenant()`.
`PLATFORM_ONLY_PATHS` stops being a blanket redirect and instead asks *"does this tenant have
this page enabled?"*

### Slugs

The six standard slugs stay reserved in the **free-form create form** (so nobody hand-builds a
conflicting page), but become creatable **via the toggle**, which is the only path that may
mint them.

---

## Decisions (agreed)

| Question | Decision |
|---|---|
| **Privacy / Terms** | Reseller gets their **own editable copy, seeded with the platform's current text**. Cannot be disabled — a white-label site with no privacy policy is worse than one with generic text. They own what they publish. |
| **Contact form destination** | New `contact_email` on reseller settings, **defaulting to the owner account's email** so it works the moment the page is switched on, and can be pointed at a shared inbox. |
| **Find Memorial** | **Build it**, scoped to that reseller's memorials. Also fixes the hardcoded `whereNull('reseller_id')`. |

---

## Phases

### Phase 1 — Foundation
1. `App\Support\StandardPages` — the catalogue: slug, title, kind (`content` / `layout` /
   `dynamic`), whether it is disableable, starter content.
2. Tenant-aware `Page::getBySlug($slug, ?int $resellerId)`; keep the per-tenant cache keys.
3. Allow the six slugs for reseller rows created through the toggle; keep them out of the
   free-form create form.

### Phase 2 — The toggle UI
4. Reseller Pages screen: Standard section with switches, Custom section unchanged.
5. `Reseller\PageController::toggleStandard()` — creates-and-publishes or unpublishes,
   seeding from `StandardPages` on first enable.
6. Legal pages render the switch as locked-on with an explanatory note.

### Phase 3 — Routing
7. Replace `PLATFORM_ONLY_PATHS` with a per-tenant enablement check.
8. Disabled standard page on a reseller host → redirect to their `/`.
9. Platform host behaviour unchanged throughout.

### Phase 4 — The simple pages
10. `About`, `Privacy Policy`, `Terms of Use` resolve for the active tenant.
11. Seed the two legal pages from the platform's current text on first enable.

### Phase 5 — Pricing *(contains a real bug fix)*
12. Scope plans by the **host's** tenant rather than the viewer's — new
    `sellableForTenant()`; keep `sellableTo()` for the logged-in dashboard case.
13. Reseller pricing page lists their own plans in their own currency.

### Phase 6 — Find Memorial *(contains a real bug fix)*
14. Tenant-scope `MemorialDirectoryController`: their memorials on their host, platform-only
    on ours.

### Phase 7 — Contact
15. `resellers.contact_email` migration, defaulting to the owner's email.
16. Field on Reseller → Settings.
17. `ContactController` routes submissions to the tenant's address; falls back to the
    platform's only on the platform's own host.

### Phase 8 — Wiring and tests
18. Menu destination picker offers their **enabled** standard pages — this is what finally
    makes an About page reachable from their nav.
19. Tests: toggle on/off, disabled → redirect, legal pages cannot be disabled, no
    cross-tenant plan leak, directory scoping, contact routing, platform host unaffected.

---

## Risks

- **Phase 5 must land with the pricing page, not after it.** `sellableTo()` keys off the
  viewer, so the moment `/pricing` stops redirecting on a reseller host, an anonymous visitor
  there is shown the platform's plans. The redirect is what hides it today.
- **`reservedSlugs()` is load-bearing.** It also protects genuine app paths (`login`,
  `dashboard`, `api`). Only the six page slugs may be relaxed, and only for the toggle path.
- **Reseller #1's `about-us` page.** Once `about` is available they will likely want to move.
  Not automatic — a redirect from the old slug is a follow-up, not part of this.
- **Caching.** `Page` caches per slug per tenant for an hour; every toggle must clear the
  right key or a page will appear not to switch.
