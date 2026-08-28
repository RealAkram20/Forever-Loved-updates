<?php

namespace App\Themes;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Letting a reseller look at a theme before it becomes their live site.
 *
 * Applying a theme changes the public site immediately, for everyone, with no way back except
 * applying the old one and hoping nothing was seeded in between. That is a lot to ask of
 * somebody choosing between two designs, and the reason most of them will not choose at all.
 *
 * WHY NOT `?theme=`. A query parameter would be four lines and is the wrong answer twice
 * over. It lets anyone re-skin somebody else's public site with a link — a defacement they
 * cannot revoke — and it hands a cache key to any proxy in front of us, so one visitor's
 * ?theme= response is served to the next hundred who asked for the plain URL. The preview has
 * to live somewhere a stranger's URL cannot reach: the previewer's own session.
 *
 * WHY A SIGNED HANDOFF. The session that has to carry it is on the *public site's* host, and
 * the person setting it is on the dashboard, which is a different one. Cookies are host-only
 * here (SESSION_DOMAIN is null), so a flag set on the dashboard is invisible on the subdomain
 * and doubly so on a custom domain. So the dashboard mints a short-lived signed URL whose
 * only job is to land on the tenant's host and set the flag there. The signature is the
 * authorisation — the tenant host has no session for this person yet, which is the whole
 * problem being solved — so it is deliberately short-lived and carries nothing but a theme id
 * that is re-checked against the reseller on arrival.
 *
 * Nothing here writes to the reseller's record. A preview that could persist would be an
 * apply with extra steps.
 */
class ThemePreview
{
    /**
     * Scoped by reseller id in the payload rather than in the key. The check that matters is
     * not "is there a preview" but "is this preview for the site I am currently serving" —
     * one staff member's session must never re-skin another tenant's site.
     */
    public const SESSION_KEY = 'theme_preview';

    /**
     * How long a preview lasts once entered.
     *
     * Long enough to click through every page of a site and think about it; short enough that
     * a forgotten tab does not still show the wrong design tomorrow. It refreshes on entry,
     * not on every request — a preview should end on its own, not follow somebody around.
     */
    public const TTL_MINUTES = 30;

    /**
     * How long the handoff link is good for.
     *
     * It is a bearer credential for the length of its life: anyone holding it can start a
     * preview in their own browser. Five minutes is a redirect, not a link worth passing on,
     * and the worst it buys is looking at a different design of a public page.
     */
    public const LINK_TTL_MINUTES = 5;

    /**
     * The signed link that starts a preview, addressed at the site being previewed.
     *
     * Two shapes for the same reason ResellerAuthUrls has two: on a real reseller host the
     * shared route resolves on that host, while the /r/{slug} fallback keeps the tenant in
     * the path. The signature is *relative* in both cases — signed against path and query
     * only — because the link is minted on the platform's host and spent on the tenant's, and
     * an absolute signature would be computed over the wrong one and never validate.
     */
    public static function linkFor(Reseller $reseller, Theme $theme): string
    {
        $expiry = now()->addMinutes(self::LINK_TTL_MINUTES);

        if ($reseller->usingFallbackAddress()) {
            return url(URL::temporarySignedRoute(
                'reseller.theme.preview.enter',
                $expiry,
                ['reseller' => $reseller->slug, 'theme' => $theme->id],
                absolute: false,
            ));
        }

        return rtrim($reseller->publicBaseUrl(), '/').URL::temporarySignedRoute(
            'theme.preview.enter',
            $expiry,
            ['theme' => $theme->id],
            absolute: false,
        );
    }

    /** Begin a preview on this host. Called only after the signature has been checked. */
    public static function start(Request $request, Reseller $reseller, Theme $theme): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'reseller_id' => $reseller->id,
            'theme_id' => $theme->id,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ]);
    }

    public static function stop(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * The theme being previewed on this reseller's site, or null.
     *
     * Every reason to say no is checked here rather than at the call sites: no session, a
     * preview belonging to a different tenant, an expired one, or a theme that has since been
     * deleted or withdrawn from the reseller's reach. An expired or foreign preview is
     * cleared on the way out, so a stale session does not keep costing a lookup.
     */
    public static function active(Reseller $reseller): ?Theme
    {
        $session = session();
        $state = $session->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return null;
        }

        if (($state['reseller_id'] ?? null) !== $reseller->id) {
            // Someone previewing their own site who then visits another reseller's. Their
            // preview is not wrong, it is simply not about this site — so leave it alone.
            return null;
        }

        if (($state['expires_at'] ?? 0) < now()->getTimestamp()) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        // Re-read rather than trusting the session's copy: a theme can be unpublished or
        // deleted while somebody is looking at it, and selectableFor() is the same
        // authorisation boundary the apply action uses.
        $theme = Theme::selectableFor($reseller->id)->firstWhere('id', (int) ($state['theme_id'] ?? 0));

        if (! $theme) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        return $theme;
    }

    /**
     * The template this request should render with, honouring a preview when one is running.
     *
     * **Also primes the palette**, which is the reason this is one call rather than two: a
     * preview that swapped the blades but kept the applied theme's colours would show
     * Dignified in somebody else's brand and answer the wrong question entirely. The
     * reseller's own hand-set values still win over the previewed theme's, exactly as they
     * would if it were applied for real — so what they are looking at is what they would get.
     */
    public static function resolveTemplate(Reseller $reseller): string
    {
        $preview = self::active($reseller);

        if (! $preview) {
            return $reseller->templateSlug();
        }

        ThemeSetting::useThemeTokens($reseller->id, $preview->tokenValues());

        return $preview->templateSlug();
    }

    /**
     * Keep a previewed page out of every cache between here and the browser.
     *
     * Without this a CDN or reverse proxy in front of a reseller's site can store one staff
     * member's preview under the plain public URL and serve it to real visitors — the exact
     * cache-poisoning the query-parameter design was rejected for, reintroduced by accident.
     */
    public static function markUncacheable(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
    }

    /**
     * The bar that says "you are looking at a preview", welded onto the response rather than
     * into a layout.
     *
     * A template that forgot to include it would look exactly like a site that had already
     * been changed — the one mistake this whole feature exists to prevent — and templates are
     * written by people who have never read this file. Injecting it before </body> means no
     * template can drop it, and no template has to know about it.
     *
     * Only ever reached when a preview is active, and only for HTML.
     *
     * Applying is a link back to the dashboard rather than a button here, and deliberately so:
     * this page is on the tenant's host, the apply action is on the platform's, and a form
     * posted across them would carry a CSRF token minted against the wrong session. Sending
     * them to the page that owns the decision is both simpler and where the rest of the
     * choice — what it costs, what it seeds, what it overrides — is already explained.
     */
    public static function injectBar(Response $response, Theme $theme, string $stopUrl, string $dashboardUrl): void
    {
        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, '</body>')) {
            return;
        }

        $name = e($theme->name);
        $stop = e($stopUrl);
        $dashboard = e($dashboardUrl);

        // Its own <style> rather than utility classes: this markup never passes through the
        // CSS build, so a Tailwind class here would be generated only by coincidence and the
        // bar would render unstyled the first time somebody's template did not happen to use
        // the same one. Everything is prefixed so it cannot collide with a template either.
        //
        // The padding on <body> is the detail worth keeping: a fixed bar with nothing behind
        // it permanently covers the last inch of every page, so the one part of the site a
        // reseller cannot inspect while previewing is their own footer.
        //
        // No entrance animation, on purpose. The bar is on every page of the site while a
        // preview runs, so it would play on every click — an animation seen dozens of times
        // in a sitting is one that only ever costs time. The press feedback stays, because
        // that answers the click rather than decorating it.
        $bar = <<<HTML
        <style>
        body{padding-bottom:5.5rem}
        .tpv-bar{position:fixed;left:0;right:0;bottom:0;z-index:2147483647;display:flex;flex-wrap:wrap;gap:.5rem 1rem;align-items:center;justify-content:center;padding:.85rem 1rem;background:#161616;color:#fff;font:500 14px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 -1px 0 rgba(255,255,255,.12),0 -8px 24px rgba(0,0,0,.35)}
        .tpv-bar strong{font-weight:700}
        .tpv-bar a{transition:background-color .2s ease,color .2s ease,transform .16s ease-out}
        .tpv-cta{border-radius:.5rem;padding:.45rem 1rem;background:#fff;color:#161616;font-weight:600;text-decoration:none}
        .tpv-stop{color:rgba(255,255,255,.75);text-decoration:underline;text-underline-offset:3px}
        .tpv-bar a:active{transform:scale(.97)}
        .tpv-bar a:focus-visible{outline:2px solid #fff;outline-offset:2px}
        @media (hover:hover) and (pointer:fine){
        .tpv-cta:hover{background:#e8e8e8}
        .tpv-stop:hover{color:#fff}
        }
        @media (prefers-reduced-motion:reduce){.tpv-bar a{transition:none}}
        </style>
        <div class="tpv-bar" role="status">
            <span>Previewing <strong>{$name}</strong> &mdash; only you can see this, and your site has not changed.</span>
            <a class="tpv-cta" href="{$dashboard}">Apply it to my site</a>
            <a class="tpv-stop" href="{$stop}">Stop preview</a>
        </div>
        HTML;

        $response->setContent(str_replace('</body>', $bar.'</body>', $content));
    }
}
