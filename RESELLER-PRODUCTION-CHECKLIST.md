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

The app now detects this rather than pretending otherwise. `Reseller::subdomainRoutingAvailable()`
compares `APP_URL` against `RESELLER_APP_DOMAIN`, and when they cannot work together:

- reseller addresses fall back to a path-based `/r/{slug}/{memorial}` route, so reseller pages
  are actually reachable in development instead of only existing in production;
- the reseller dashboard, their Settings and Appearance pages, and the admin roster all label
  that address as temporary and name the real host it will become.

Once this and §2 are correct, the fallback stops being used automatically — there is nothing
to switch off. **After changing either value, run `php artisan config:clear`.**

`routes/web.php` derives the "is this our own host?" exclusion from `APP_URL`. Get this
wrong and the catch-all `Route::domain('{domain}')` group can shadow first-party routes
like `/login` and `/dashboard`.

## 2. Reseller base domain — BLOCKER

```env
RESELLER_APP_DOMAIN=yourdomain.com
```

Now set explicitly in `.env` rather than relying on `config/reseller.php`'s fallback, so it is
visible what resellers are being sold. Every reseller subdomain is minted under this value, and
it is baked into route registration at boot — so after changing it:

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

> Production (alwaysforeverloved.com on Hostinger + Coolify): see the
> [wildcard runbook](#12-production-runbook-wildcard-subdomains-on-hostinger--coolify) below
> for the exact records and proxy configuration.

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

Invites **are** sent now, from all three account-creation paths (new reseller owner, new client,
and "create a memorial for a client"), and each success message says plainly whether the email
actually went out. That is exactly why real SMTP matters here: with `MAIL_MAILER=log` the UI
correctly reports that nobody was emailed, and those accounts have no way to learn they exist.

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

# Attributes memorials a reseller's own clients created themselves. Until Memorial's
# creating hook existed, only the reseller's "create for a client" screen stamped
# reseller_id — so client-created memorials were invisible to the reseller and, more
# importantly, uncounted against the tier allowance and storage cap they are billed on.
# Reports any reseller the correction pushes into billable overage.
php artisan memorials:backfill-reseller-attribution --dry-run   # inspect first
php artisan memorials:backfill-reseller-attribution

# The reseller intake screen used to collect one combined name and leave first_name and
# last_name null. Accessors parse full_name on read, so most names still render — but a
# single-token name ("Prince") parses to an empty last name, and the edit form requires
# one, so the invited client could not save that memorial at all.
# Intake collects the parts now; this stores them for the rows created before it did.
php artisan memorials:backfill-name-split --dry-run   # inspect first
php artisan memorials:backfill-name-split
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

## 12. Production runbook: wildcard subdomains on Hostinger + Coolify

The concrete version of §3 + §4 for the live deployment (`alwaysforeverloved.com`,
DNS at Hostinger, app deployed by Coolify behind its Traefik proxy). As of 2026-08-12
the wildcard DNS record does **not** exist — every reseller subdomain is NXDOMAIN —
and the `letsencrypt-dns` certificate resolver referenced by `docker-compose.yaml`
is not defined on the proxy. Work through these in order; each step is one-time and
covers all current and future resellers.

### 12.1 Wildcard DNS record at Hostinger

hPanel → **Domains → alwaysforeverloved.com → DNS / Nameservers** → add:

| Type | Name | Points to | TTL |
|---|---|---|---|
| A | `*` | `169.58.157.254` (must equal the apex A record) | 3600 |

Verify from any machine (allow TTL for propagation):

```bash
dig +short aplus.alwaysforeverloved.com          # expect 169.58.157.254
dig +short anything-at-all.alwaysforeverloved.com # expect 169.58.157.254
dig +short alwaysforeverloved.com                 # must match the two above
```

### 12.2 Confirm the Coolify proxy is Traefik v3

```bash
docker exec coolify-proxy traefik version
```

The wildcard router labels in `docker-compose.yaml` use bare-regex
``HostRegexp(`^[a-z0-9-]+\.alwaysforeverloved\.com$`)`` syntax, which is **Traefik v3
only** — on v2 the rule is invalid and the router silently never loads. If the proxy
is v2, upgrade it from Coolify (**Servers → your server → Proxy**) first.

While there, confirm the bundled lego build supports the `hostinger` DNS provider
(present in recent Traefik 3.x; check the deployed version's ACME provider docs for
the exact credential variable — expected `HOSTINGER_API_KEY`, minted at
hPanel → **Account → API**).

### 12.3 Define the `letsencrypt-dns` resolver on the proxy

Coolify → **Servers → your server → Proxy → Configuration** (this edits
`/data/coolify/proxy/docker-compose.yml`). Add to the Traefik service — keeping every
existing flag and the existing HTTP-01 resolver untouched (the apex keeps its cert):

```yaml
    environment:
      - HOSTINGER_API_KEY=<token from hPanel API page>
    command:
      # ...existing flags stay...
      - '--certificatesresolvers.letsencrypt-dns.acme.email=realakram20@gmail.com'
      - '--certificatesresolvers.letsencrypt-dns.acme.storage=/traefik/acme-dns.json'
      - '--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.provider=hostinger'
      - '--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.delaybeforecheck=60s'
      - '--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.resolvers=1.1.1.1:53,8.8.8.8:53'
```

The resolver name `letsencrypt-dns` must match the
`tls.certresolver=letsencrypt-dns` label in this repo's `docker-compose.yaml` exactly.
Restart the proxy from Coolify after saving.

### 12.4 Redeploy the app

Redeploy from Coolify so the wildcard router labels (added 2026-08-11) are applied to
the running container. Confirm the app's environment there has
`APP_URL=https://alwaysforeverloved.com` (bare, no path) and `RESELLER_APP_DOMAIN`
empty or `alwaysforeverloved.com`.

### 12.5 Verify end to end

```bash
# Router answers for the subdomain (not the proxy's 404 default backend)
curl -sI https://aplus.alwaysforeverloved.com/ | head -5

# Certificate is the Let's Encrypt wildcard, not "TRAEFIK DEFAULT CERT"
openssl s_client -connect alwaysforeverloved.com:443 \
  -servername aplus.alwaysforeverloved.com </dev/null 2>/dev/null \
  | openssl x509 -noout -issuer -ext subjectAltName -dates
# expect SANs: alwaysforeverloved.com, *.alwaysforeverloved.com

# HTTP redirects to HTTPS
curl -sI http://aplus.alwaysforeverloved.com/ | grep -i location

# Watch issuance if the cert doesn't appear within a minute or two
docker logs coolify-proxy 2>&1 | grep -i acme | tail -20
```

Then from the app server: `php artisan reseller:doctor aplus` — every probe should
PASS. Finish with the smoke test below.

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
