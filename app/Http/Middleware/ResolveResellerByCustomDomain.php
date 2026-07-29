<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sibling to ResolveReseller, for a reseller's own verified custom domain instead of
 * the {slug}.foreverloved.com subdomain — matched via the catch-all Route::domain('{domain}')
 * group in routes/web.php, which is registered after the subdomain pattern so it only ever
 * sees genuinely different hostnames. Only a verified domain resolves; anything else 404s,
 * since an unverified/failed domain hasn't proven DNS ownership yet.
 *
 * Deliberately does NOT re-check tier.feature_domain_routing. Setting a domain up requires
 * the entitlement (Reseller\SettingsController), but once verified it keeps serving even if
 * the reseller is later downgraded — pulling a live domain would dark a published memorial
 * page for the family visiting it, which is too harsh a consequence for a billing change.
 * Downgrades are handled by talking to the reseller, not by 404ing their mourners.
 */
class ResolveResellerByCustomDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = Reseller::where('custom_domain', $request->getHost())
            ->where('custom_domain_status', Reseller::DOMAIN_VERIFIED)
            ->first();

        if (! $reseller) {
            abort(404);
        }

        app()->instance(Reseller::class, $reseller);
        // Their own domain, so their own site — see ThemeSetting::isResellerSite().
        ThemeSetting::markResolvedFromRequest();

        return $next($request);
    }
}
