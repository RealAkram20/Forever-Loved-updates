<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {reseller} subdomain segment into a Reseller and binds it into the
 * container so PublicMemorialController and BrandingHelper can pick it up without
 * re-querying. Deliberately does NOT 404 on a suspended reseller — their public
 * memorial pages must keep working for the memorial owner and visitors even while
 * the reseller's own staff dashboard access is blocked (see EnsureResellerActive).
 */
class ResolveReseller
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('reseller');

        $reseller = Reseller::where('slug', $slug)->first();

        if (! $reseller) {
            abort(404);
        }

        app()->instance(Reseller::class, $reseller);
        // Resolved from the URL, so this is their own public site — the nav drops the platform's
        // marketing links here. See ThemeSetting::isResellerSite().
        ThemeSetting::markResolvedFromRequest();

        return $next($request);
    }
}
