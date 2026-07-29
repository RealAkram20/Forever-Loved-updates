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
     * Platform pages that are about the platform. Kept deliberately short — anything a
     * reseller's clients legitimately need on their host must keep working: auth, the
     * dashboard, memorial management, and the create-memorial flow (which already scopes
     * plans per reseller). privacy-policy and terms-of-use stay too: the platform really is
     * the data processor, and serving no legal pages at all is worse than serving ours.
     */
    private const PLATFORM_ONLY_PATHS = [
        'about',
        'pricing',
        'contact',
        'find-memorial',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        // The common case, and the one that must cost nothing: our own host.
        if ($host === strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'))) {
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
            return $next($request);
        }

        app()->instance(Reseller::class, $reseller);
        ThemeSetting::markResolvedFromRequest();

        if (in_array(trim($request->path(), '/'), self::PLATFORM_ONLY_PATHS, true)) {
            // Their front page, on their host — not the platform equivalent, which would hand
            // the visitor off to us.
            return redirect('/');
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
