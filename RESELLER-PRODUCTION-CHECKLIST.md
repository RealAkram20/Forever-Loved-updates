# Reseller program — production checklist

The reseller feature routes on the **Host header**. That makes it more sensitive to
deployment configuration than the rest of this app: it can be fully correct in code and
still completely non-functional if DNS, TLS or `APP_URL` are wrong. Work through this
before going live.

Anything marked **BLOCKER** means the reseller feature does not work at all until it is done.

---

## 1. Application URL — BLOCKER

```env
APP_URL=https://yourdomain.com
```

**It must be a bare domain, on HTTPS, with no path.** The current value is
`http://localhost/Forever`, which is a subdirectory install — subdomain routing
**cannot work** through a subdirectory, because `acme.yourdomain.com/Forever/jane-doe`
is not a URL any reseller would ever hand to a family.

`routes/web.php` derives the "is this our own host?" exclusion from `APP_URL`. Get this
wrong and the catch-all `Route::domain('{domain}')` group can shadow first-party routes
like `/login` and `/dashboard`.

## 2. Reseller base domain — BLOCKER

```env
RESELLER_APP_DOMAIN=yourdomain.com
```

Currently **unset**, so it falls back to `foreverloved.com` (see `config/reseller.php`).
Every reseller subdomain is minted under this value, and it is baked into route
registration at boot — so after changing it:

```bash
php artisan config:clear && php artisan route:clear
```

Normally the same bare domain as `APP_URL`.

## 3. DNS — BLOCKER

| Record | Name | Points at |
|---|---|---|
| A / AAAA | `yourdomain.com` | server IP |
| A / AAAA | `*.yourdomain.com` | server IP |

The **wildcard is required**. Every reseller gets `{slug}.yourdomain.com` the moment an
admin creates them — there is no per-reseller DNS step, by design.

## 4. TLS — BLOCKER

A wildcard certificate covering **both** `yourdomain.com` and `*.yourdomain.com`.

```bash
certbot certonly --dns-<provider> -d yourdomain.com -d '*.yourdomain.com'
```

Wildcard certs require a **DNS-01** challenge; HTTP-01 cannot issue them. If your DNS
provider has no certbot plugin, Caddy or Cloudflare handles this automatically and is
the lower-effort path.

Without this, every reseller subdomain throws a certificate warning — on a memorial page,
to a grieving family. Treat it as non-negotiable.

## 5. Web server virtual host

The vhost must accept the wildcard, not just the apex:

```apache
ServerName yourdomain.com
ServerAlias *.yourdomain.com
```

Document root must be `public/`, as usual.

## 6. Mail — BLOCKER for the client-invite flow

```env
MAIL_MAILER=smtp   # currently: log
```

`MAIL_MAILER=log` writes mail to `storage/logs/` and sends nothing. Configure real SMTP
(there is an admin UI at **Settings → SMTP / Email**) and send a test before launch.

> **Known gap:** the reseller "create a memorial for a client" screen states *"We'll invite
> the client by email"*, but no invite is currently sent, and the client account is created
> with a null password. Until that is implemented, clients created this way **cannot log
> in** and will not know an account exists. Either implement the invite or remove the
> promise from the UI before launch — shipping a stated promise the software does not keep
> is worse than shipping without the feature.

## 7. Queue worker

`QUEUE_CONNECTION=database`, so a worker must be running or every queued job silently
never executes:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

Run it under supervisor/systemd so it restarts on failure and on deploy.

## 8. Migrations and one-off commands

```bash
php artisan migrate --force

# Records sizes for profile photos uploaded before size tracking existed.
# Without it, reported storage undercounts every older memorial.
php artisan memorials:backfill-photo-sizes --dry-run   # inspect first
php artisan memorials:backfill-photo-sizes
```

## 9. Do NOT seed demo data

`database/seeders/DemoDataSeeder.php` exists. Confirm `DatabaseSeeder` does not invoke it
on production, and never run `db:seed` against the live database without reading exactly
what it does first.

## 10. Admin settings to set on day one

Under **Resellers → Settings**:

- **Custom domains** — leave off until §11 is solved; resellers then see an honest
  "not available yet" note instead of instructions that cannot work.
- **CNAME target** — the host resellers point their domain at. Leave blank until hosting
  is final; they will see "check back soon" rather than a wrong instruction.
- **Default tier** — so new resellers inherit pricing without a second, forgettable step.

Under **Resellers → Pricing**: create at least one tier before onboarding anyone. A
reseller with no tier gets features denied and quotas unmetered.

## 11. Custom domains — extra TLS work

A reseller's own domain (`memorials.theirbusiness.com`) needs a certificate **for that
domain**, which your wildcard does not cover. Ownership verification via TXT record is
automatic and needs nothing here; TLS issuance does not.

- **Caddy** — `on_demand_tls` issues per-domain certs automatically. Easiest option.
- **Cloudflare for SaaS** — designed exactly for this.
- **Plain Nginx/Apache** — needs a Let's Encrypt client wired in per domain. Manual.

Until one of these exists, keep custom domains **disabled**. Subdomains work fine without
any of it.

---

## Smoke test before announcing

1. Create a reseller in admin; confirm `{slug}.yourdomain.com` resolves over **HTTPS**.
2. Log in as the owner via **Login as owner**; confirm the reseller dashboard loads.
3. Create a memorial for a client; open it on the reseller's subdomain.
4. Confirm that memorial is **404 on a different reseller's subdomain** (tenant isolation —
   covered by `tests/Feature/ResellerDomainRoutingTest.php`, but verify on real DNS).
5. Suspend the reseller; confirm their dashboard is blocked but the public memorial still
   serves.
6. Record a payment; confirm the renewal date advances.
