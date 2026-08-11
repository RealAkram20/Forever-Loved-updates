<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes *every* page served on a reseller's host theirs, not just the two routes that declare a
 * domain.
 *
 * ResolveReseller and ResolveResellerByCustomDomain are route middleware, so they only run for
 * the memorial page and the front page. Every other route — /login, /dashboard, /memorials,
 * /pricing — is registered without a domain constraint and therefore matches any Host, and ran
 * with no tenant at all. On acme.foreverloved.com that meant the platform's logo on the login
 * screen a reseller's clients use, and the platform's pricing page on the reseller's own domain.
 *
 * Two jobs, both keyed off the Host header:
 *
 *  1. Bind the reseller, so branding resolves everywhere on their host.
 *  2. Send the platform's own marketing pages back to the reseller's front page, because those
 *     pages are about *us*. /find-memorial is the sharpest case: the platform directory
 *     deliberately excludes reseller memorials, so on a reseller's domain it listed every
 *     memorial except the ones their visitor came looking for.
 *
 * Registered on the web group rather than per-route: the whole point is the routes that never
 * declared a domain. The app's own host short-circuits before any query runs.
 */
class ResolveResellerByHost
{
    /**
     * These four used to be redirected unconditionally, because the only About and Pricing
     * pages that existed were the platform's and serving them here would advertise us on a
     * reseller's domain. A reseller can now have their own, so the question changed from
     * "is this one of our pages?" to "has this tenant switched theirs on?" — see
     * StandardPages::isEnabledFor(). Off, or never enabled, still redirects to their front
     * page exactly as before.
     *
     * privacy-policy and terms-of-use were never in this list and still are not: they fall
     * back to ours when a tenant has none, since serving no legal pages at all is worse than
     * serving generic ones.
     *
     * Everything else a reseller's clients need — auth, the dashboard, memorial management,
     * the create-memorial flow — is untouched and always passes through.
     */
    private const TENANT_OPTIONAL_PATHS = [
        'about',
        'pricing',
        'contact',
        'find-memorial',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // The common case, and the one that must cost nothing: our own host. Both the bare
        // and www. forms count — routes/web.php excludes the same pair from the reseller
        // custom-domain pattern, and the two have to agree or a request would be routed as
        // the platform's while being themed as somebody's tenant.
        if (in_array($host, \App\Support\ResellerDomain::platformHosts(parse_url((string) config('app.url'), PHP_URL_HOST)), true)) {
            // Clear any tenant a *previous* request left bound. Under PHP-FPM the container is
            // rebuilt per request so this is a no-op, but under a long-running worker (Octane)
            // or in tests the bindings persist — and a leaked tenant would serve one reseller's
            // branding, memorials and search results on the platform's own host, or on another
            // reseller's. Cheap insurance against a whole class of cross-tenant bleed.
            //
            // Safe for the /r/{slug} fallback, which is served on this host: route middleware
            // runs after the web group, so ResolveReseller binds again afterwards.
            app()->forgetInstance(Reseller::class);
            app()->forgetInstance(ThemeSetting::REQUEST_TENANT_FLAG);

            return $next($request);
        }

        $reseller = $this->resolve($host);

        if (! $reseller) {
            // Custom domains are stored bare, but people type www anyway — and the proxy
            // routes www.<domain> here on purpose. One canonical host, one 301 carrying
            // the full path and query, on every route rather than only the front page.
            if (str_starts_with($host, 'www.') && $this->resolve(substr($host, 4)) !== null) {
                return redirect()->to(
                    $request->getScheme().'://'.substr($host, 4).$request->getRequestUri(),
                    301
                );
            }

            return $next($request);
        }

        app()->instance(Reseller::class, $reseller);
        ThemeSetting::markResolvedFromRequest();

        $path = trim($request->path(), '/');

        if (in_array($path, self::TENANT_OPTIONAL_PATHS, true)
            && ! \App\Support\StandardPages::isEnabledFor($path, $reseller->id)) {
            // Their front page, on their host — not the platform equivalent, which would hand
            // the visitor off to us.
            //
            // Built from the request rather than redirect('/'), which routes through the URL
            // generator: AppServiceProvider calls URL::forceRootUrl(config('app.url')) to make
            // subdirectory installs work, so every generated path is rooted at the platform's
            // own address. redirect('/') on acme.example.com therefore sent the visitor to
            // example.com — precisely the hand-off this branch exists to prevent.
            return redirect()->to($request->getSchemeAndHttpHost().'/');
        }

        return $next($request);
    }

    /**
     * A reseller for this host, or null. Tries the {slug}.{base} pattern first since it needs
     * no wildcard-domain lookup, then a verified custom domain.
     *
     * Unverified custom domains resolve to nothing, matching ResolveResellerByCustomDomain:
     * anyone can point a hostname at us, and serving a tenant for one that has not proven DNS
     * ownership would let them impersonate a reseller.
     */
    private function resolve(string $host): ?Reseller
    {
        $base = strtolower((string) config('reseller.domain'));

        if ($base !== '' && str_ends_with($host, '.'.$base)) {
            $slug = substr($host, 0, -strlen('.'.$base));

            // Only a single label: a.b.foreverloved.com is not a reseller address.
            if ($slug !== '' && ! str_contains($slug, '.')) {
                return Reseller::where('slug', $slug)->first();
            }

            return null;
        }

        return Reseller::where('custom_domain', $host)
            ->where('custom_domain_status', Reseller::DOMAIN_VERIFIED)
            ->first();
    }
}
