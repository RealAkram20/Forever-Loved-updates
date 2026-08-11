# Reports & exports — implementation plan

> **Status: built.** All six phases are done. `php artisan test` — **211 passed**, the whole
> suite green including `ExampleTest`, which failed on a clean checkout before this work
> (see "What changed during implementation" at the bottom).
>
> 13 reports (7 admin, 5 reseller, 1 per-memorial), three export formats, 25 new tests
> across `tests/Feature/ReportsTest.php` and `tests/Feature/ResellerReportsTest.php`.

Scope agreed: an admin and a reseller Reports area, viewable on screen with date filters,
downloadable as **PDF, Excel (.xlsx) and CSV**, plus a per-memorial report a reseller can
hand to a family.

Two constraints from the environment shape everything below:

- **`vendor/` is committed to git** (10,080 tracked files) and Hostinger deploys are a file
  upload — there is no `composer install` on the server. Any dependency we add ships in the
  repo. Budget: ~13 MB.
- **PHP 8.3 has `zip`, `gd`, `dom`, `mbstring`, `intl`.** Everything the export libraries
  need is already present, so no server-side prerequisites to document.

---

## Why this is one feature and not two

Admin reports and reseller reports differ in exactly two ways: which rows they may see, and
whose logo is on the PDF. Everything else — date filtering, column definitions, pagination,
summary tiles, PDF/XLSX/CSV rendering, filenames — is identical.

So the design is **one pipeline, many small report classes**. A report class knows its
columns and its query. It knows nothing about HTML, PDF, Excel, or who is asking. Adding the
fourteenth report is then a ~60-line class and one registry line, not another controller.

The alternative — a controller method per report per format — is 14 × 4 = 56 endpoints, and
every one of them is a place to forget a `where('reseller_id', …)`. That is the bug class
this feature must not have.

---

## Phase 0 — Close the data gaps first

Four small migrations. Doing these first means the report classes are written once against
the final schema instead of being rewritten in Phase 3.

1. **`users.last_login_at`** (nullable timestamp, indexed). Set it in
   `AuthenticatedSessionController::store()` and the passwordless/Google login paths.
   Without this, "registered but never returned" — the most useful churn signal we have — is
   simply not computable.
2. **`payment_orders.paid_at`** (nullable timestamp). Set by
   `PaymentResultProcessor` when an order completes. Backfill existing rows from
   `updated_at` where `status = 'completed'`, and record in the migration comment that
   backfilled values are approximate.
3. **`payment_orders` indexes** — `(reseller_id, status)` and `(created_at)`. The revenue
   reports group by both and the table currently has neither.
4. **`memorial_shares`** already has `(memorial_id, shared_at)`; **`memorial_views`** already
   has `(memorial_id, viewed_at)`. Nothing to do — noted so nobody adds duplicates.

Deliberately **not** added: refund tracking. There is no refund concept anywhere in the app,
and inventing a column the payment flow never writes would put a permanently-zero "Refunds"
line on every revenue report. Revenue is reported as gross, and the reports say so.

**Files:** `database/migrations/*_add_last_login_at_to_users_table.php`,
`*_add_paid_at_to_payment_orders_table.php`, `*_add_reporting_indexes_to_payment_orders_table.php`,
`app/Http/Controllers/Auth/{AuthenticatedSessionController,GoogleLoginController,PasswordlessLoginController}.php`,
`app/Services/PaymentResultProcessor.php`.

---

## Phase 1 — The engine, proven end-to-end with CSV

CSV needs no dependency, so Phase 1 ships a complete working vertical slice —
catalogue → filtered table → download — before a single byte of `vendor/` is touched.

### The contract

```php
interface Report
{
    public function key(): string;              // 'revenue' — URL segment, registry key
    public function title(): string;
    public function description(): string;
    public function columns(): array;           // [ReportColumn, …] label, accessor, type, align
    public function summary(ReportFilters $f): array;  // [ReportStat, …] the tiles
    public function rows(ReportFilters $f): LazyCollection;
    public function availableTo(User $user): bool;
}
```

`rows()` returns a **`LazyCollection` over a chunked query**, never a materialised array. A
reseller with 50,000 view rows must export without exhausting memory on shared hosting.

### Tenant scoping is structural, not per-report

Reseller report classes extend an abstract `TenantScopedReport` whose constructor takes the
`Reseller` resolved from `$request->user()->reseller`. The tenant id is **never** readable
from the request in a report class — there is no code path that could accept one. This
mirrors the pattern `ResellerTenantIsolationTest` already enforces on the existing
controllers.

### Files

```
app/Reports/Contracts/Report.php
app/Reports/ReportColumn.php          label, key, type (text|number|money|date|bool), align, width
app/Reports/ReportStat.php            label, value, hint, tone
app/Reports/ReportFilters.php         from, to, preset (7d|30d|90d|ytd|custom), extras[]
app/Reports/ReportRegistry.php        audience => [key => class]; resolve() 404s on miss
app/Reports/TenantScopedReport.php    abstract base; holds the Reseller, applies the scope
app/Reports/Concerns/HasDateWindow.php
app/Services/Reports/ReportRenderer.php     Report + Filters -> ReportResult
app/Services/Reports/Export/CsvExporter.php
app/Http/Controllers/Admin/ReportController.php
resources/views/pages/reports/index.blade.php
resources/views/pages/reports/show.blade.php
```

### Routes

```php
// inside the existing settings group, role:admin|super-admin
Route::get('/reports',  [Admin\ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{report}', [Admin\ReportController::class, 'show'])->name('reports.show')
    ->where('report', '[a-z0-9\-]+');
Route::get('/reports/{report}/download/{format}', [Admin\ReportController::class, 'download'])
    ->where(['report' => '[a-z0-9\-]+', 'format' => 'pdf|xlsx|csv'])->name('reports.download');
```

`{report}` resolves through the registry **keyed by audience** — an admin-only key requested
on the reseller route 404s rather than leaking its existence. `{format}` is whitelisted at
the route level, so no exporter is ever selected from unvalidated input.

### CSV specifics

Streamed via `response()->streamDownload()`. Cells beginning `=`, `+`, `-` or `@` are
prefixed with a single quote — **CSV formula injection matters here specifically because the
audience is resellers who will open these in Excel.**

Phase 1 ships with **one** report — Admin Revenue — as the proof. Filters, tiles, table,
pagination and CSV download all work before the catalogue grows.

---

## Phase 2 — Excel and PDF

### Dependencies

- **`openspout/openspout`** for `.xlsx`. Streams row-by-row, ~1 MB. Deliberately *not*
  `maatwebsite/excel`, which pulls in PhpSpreadsheet (~40 MB, holds the whole sheet in
  memory — the wrong trade on shared hosting for what is a flat table).
- **`barryvdh/laravel-dompdf`** for PDF. Works headless, no Node or Chrome on the server.
  ~12 MB, mostly bundled fonts.

Both get committed to `vendor/`. One commit, isolated, so the diff is reviewable.

### XLSX

- One sheet per report. Row 1–6 are the header block (title, range, generated at/by,
  filters, scope), then a blank row, then the column headers **frozen**, then data.
- Real types, not strings: money as numeric with a currency format, dates as dates. A
  reseller's accountant must be able to `SUM()` a column without cleaning it first.
- Auto-filter on the header row.

### PDF

- Landscape A4 for tables, portrait for the per-memorial report.
- **Row cap ~2,000**, with an explicit printed line: *"Showing the first 2,000 of 8,431 rows
  — download the Excel version for the complete set."* Silent truncation in a document
  someone hands to a client is the failure mode this feature must not have.
- Images resolve through `public_path()`, not URLs; `isRemoteEnabled` stays **off**.
- Header/footer on every page, page numbers, generated-at in the footer.

### White-label branding

The rule, consistent with the existing white-label work: **an admin PDF carries platform
branding via `BrandingHelper`; a reseller PDF carries the reseller's own logo, colours and
business name and no platform branding whatsoever.** Resellers hand these to funeral-home
clients — a Forever logo on that page breaks the product promise.

Filenames are sanitised and scoped: `forever-revenue-2026-07-01_2026-07-30.xlsx` for admin,
`ashford-funeral-services-client-memorials-2026-07.xlsx` for a reseller.

**Files:** `app/Services/Reports/Export/{XlsxExporter,PdfExporter}.php`,
`resources/views/pages/reports/pdf.blade.php`, `config/dompdf.php`, `composer.json`.

---

## Phase 3 — Admin catalogue

Seven report classes against the contract from Phase 1. Column lists as agreed:

| Report | Headline tiles | Notes |
|---|---|---|
| **Revenue** | gross per currency, completed count, AOV, failed count, conversion rate, direct vs reseller split | Group-by month/plan/gateway/method/reseller |
| **Subscriptions** | active, overdue, expiring 7d/30d, new, renewal rate, revenue-at-risk | Reuses the existing `UserSubscription` scopes |
| **Users** | total, new, growth vs previous period, role split, % with a memorial, % Google sign-in | Needs Phase 0 item 1 |
| **Memorials** | total, created, status split, public/private, % with photo, % with bio, storage consumed | Storage from `media.size` |
| **Engagement** | views, unique visitors, tributes by type, shares by channel, pending moderation | Reads `memorial_views` only — see below |
| **Reseller roster** | total, active/suspended, tier mix, over-quota count, >80% storage count, unverified domains | The upsell sheet |
| **Reseller billing** | collected per currency, outstanding, overdue count/value, overage vs base, renewals due 30/60/90 | **super-admin only**, matching `resellers.payments.store` |

Two correctness rules the classes must follow, both discovered during the scan:

- **Revenue is grouped by currency, never summed across it.** Every `payment_orders` and
  `reseller_payments` row carries its own `currency`.
- **Engagement reads `memorial_views`, not `memorials.visitor_count`.** That column is a
  denormalised counter that will drift. Where the two disagree by more than a threshold, the
  report shows a data-health note rather than silently picking one.

**Files:** `app/Reports/Admin/*.php` (7), registry entries, `MenuHelper::getAdminMenuGroups()`.

---

## Phase 4 — Reseller catalogue

Five report classes extending `TenantScopedReport`.

| Report | Purpose | Gating |
|---|---|---|
| **Account & quota statement** | tier, profiles used/included/remaining, quota %, storage %, billing period, days to renewal, amount due, plus their full `reseller_payments` history | Ungated — their own accounting record |
| **Client memorials** | every memorial they host, with completion, storage, views, tributes and **last activity** | Ungated |
| **Clients** | client list with memorials owned, engagement, plan and expiry; flags clients with zero memorials | Ungated |
| **Revenue** | their own Pesapal sales | Shown only when `pesapal_enabled`; otherwise an explanatory empty state, not a zeroed table |
| **Engagement** | same shape as admin engagement, scoped to them | **`feature_business_analytics`** |

The gating rule follows the precedent already set: Clients and Payments pages are ungated
because they are the reseller's own operational records, while Analytics is a paid
capability. Engagement is the same data as the Analytics page, so gating them differently
would be incoherent. **No new tier column is needed** — no migration.

Locked reports are hidden from the nav entirely (the pattern `getResellerMenuGroups()`
already uses for Analytics) and return the pitch page, not a 403, if reached directly.

**Files:** `app/Reports/Reseller/*.php` (5), `app/Http/Controllers/Reseller/ReportController.php`,
routes inside the existing `prefix('reseller')->middleware(['role:reseller', EnsureResellerActive::class])`
group, `MenuHelper::getResellerMenuGroups()`.

---

## Phase 5 — The per-memorial family report

The commercially important one: a single branded page a reseller prints or emails to a
family. Not a table dump.

Contents: name, dates and photo of the deceased; period covered; **people who visited**
(unique `visitor_hash`) and total visits; the daily bar chart already built on the analytics
page; busiest day; first and most recent visit; **tributes broken out by type** — candles,
flowers, notes, photos; the tribute messages themselves with author and date; shares by
channel; story chapters published; gallery photo/video counts; contributing collaborators;
and a footer with the reseller's logo, name and contact details.

Three rules:

- Tribute messages respect `is_approved` and sit behind an **include/exclude toggle** —
  they are the emotional payload of the document and not every family wants them reprinted.
- Visitors are only ever counted. No names, no IPs, no locations — `memorial_views` stores a
  hash and nothing else, deliberately. The report says *"247 people visited"* and never
  implies the family can find out who.
- Reachable from the memorial row in the reseller's Client memorials report, and from the
  memorial detail page. Owner and collaborators can pull their own; reseller staff can pull
  it for memorials their reseller hosts; nobody else.

**Files:** `app/Reports/Reseller/MemorialReport.php`,
`resources/views/pages/reports/memorial-pdf.blade.php`, one route, one policy check.

---

## Phase 6 — Nav, tests, polish

Nav: a **Reports** entry (`charts` icon) in the *Overview* group of both
`getAdminMenuGroups()` and `getResellerMenuGroups()`.

`tests/Feature/ReportsTest.php`:

1. Each role reaches exactly the catalogue it should; a plain admin gets no reseller-billing
   report; a `user` gets none of it.
2. **Tenant isolation** — reseller A's export contains zero rows belonging to reseller B,
   asserted on the actual downloaded bytes, for all three formats.
3. Every format returns the right content-type, a non-empty body and a sane filename.
4. Date filters actually narrow the row set.
5. A locked-tier engagement report returns the pitch, not data, and is absent from the nav.
6. CSV formula-injection prefixing works on a memorial whose name starts with `=`.
7. The PDF row cap prints its truncation notice.

Also: `php artisan test` must stay green. `ExampleTest` fails on a clean checkout for an
unrelated reason (documented in `RESELLER-APPEARANCE-PLAN.md`) — that is the known baseline,
not a regression from this work.

---

## Order of work

Phase 0 → 1 → 2 → 3 → 4 → 5 → 6. Phases 1 and 2 are the only ones with real design risk;
3, 4 and 5 are catalogue expansion against a proven contract. Phase 1 is independently
shippable — a working Reports area with CSV export and no new dependencies — if the ~13 MB
`vendor/` growth in Phase 2 turns out to be unacceptable.

## What changed during implementation

Recorded because the plan above is what was intended, and these are the places reality
differed.

1. **Phases 1 and 2 were merged.** Installing `openspout` and `laravel-dompdf` up front
   meant the catalogue and report views were written once with all three download buttons
   rather than built for CSV and revisited. The CSV-only fallback described in Phase 1 was
   never needed — the dependencies went in cleanly.

2. **The xlsx export has no frozen header row or auto-filter.** OpenSpout supports neither;
   `Writer::setCreator()` also throws for XLSX documents. Document metadata is set through
   `Options(properties: new Properties(...))` instead, which additionally let the file carry
   the *reseller's* name in File → Properties rather than ours. Column widths are set.

3. **`phpunit.xml` now pins `APP_URL` to `http://localhost`.** The dev `.env` points it at a
   subdirectory, so the test client resolved `$this->get('/reports')` to `/Forever/reports`
   and **every routed request 404'd**. No existing feature test hit an HTTP route, so this
   had never surfaced. Fixing it exposed the real reason `ExampleTest` failed — it hits `/`,
   which reads `system_settings`, without `RefreshDatabase`. Both are fixed; the suite is
   green for the first time.

4. **The per-memorial report sits outside the registry.** The `Report` contract describes a
   table with columns; that document is a portrait page with a photograph, a chart and
   reprinted messages. It has its own builder, controller and two views.

5. **`MemorialPolicy::report()` was added.** The existing `view()` ability lets any visitor
   read a public memorial, which is far too wide for a document that reprints tributes and
   counts visitors. The new ability is owner / editors / hosting reseller / admin.

6. **`TenantScopedReport` now throws on an unresolved tenant.** `Reseller` is concrete and
   newable, so the container hands out a *blank* instance when nothing is bound — whose null
   id would scope every query to `reseller_id is null`, i.e. the platform's own records,
   served to a reseller. It fails loudly instead. This was found by a test, not by review.

7. **Two scoping bugs were caught by the reseller tests**, both worth noting as the class of
   thing this design exists to prevent: an unqualified `reseller_id` that became an ambiguous
   -column error once the best-seller stat joined `subscription_plans`, and several
   `$reseller->memorials()` calls returning a `HasMany` where a `Builder` was required.
   `TenantScopedReport::memorialQuery()` now provides the qualified builder centrally.

## What this plan deliberately excludes

- **Scheduled/emailed reports.** The queue and scheduler health are already surfaced as
  dashboard warnings, meaning cron is not reliably configured on every install. Emailing a
  monthly PDF to every reseller on infrastructure we know is flaky would fail silently. Add
  it later, on purpose.
- **Saved report presets and custom column pickers.** Real demand unknown; the date-preset
  filter covers the common cases.
- **Charts inside exports beyond the existing daily-views bars.** Dompdf renders SVG poorly;
  anything richer would need server-side image generation.
