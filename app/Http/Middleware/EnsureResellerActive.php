<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards reseller-staff access. Unlike ResolveReseller (which resolves a subdomain
 * param for the public memorial pages), this keys off the authenticated user's own
 * reseller_id and blocks dashboard/management access when their reseller account is
 * suspended. Their clients' memorials and subdomain pages are unaffected.
 *
 * Also binds the resolved Reseller into the container, same as ResolveReseller does
 * for the public subdomain — so BrandingHelper picks up their own logo/color wherever
 * this runs, not just on the public memorial pages their clients' visitors see.
 *
 * A no-op for anyone without the reseller role, so it's safe to apply on shared routes
 * (like /dashboard) that both reseller staff and everyone else can reach.
 */
class EnsureResellerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('reseller')) {
            return $next($request);
        }

        $reseller = $request->user()->reseller;

        if (! $reseller || ! $reseller->isActive()) {
            abort(403, 'Your reseller account is currently suspended.');
        }

        app()->instance(Reseller::class, $reseller);

        return $next($request);
    }
}
