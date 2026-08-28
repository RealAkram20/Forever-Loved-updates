<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Our own marketing pages wear our own brand.
 *
 * ThemeSetting::tenant() falls back to the signed-in user's reseller so a funeral home's staff
 * keep their colours and logo across the dashboard, memorial editing and their notifications.
 * That is right for the screens they work in, and it stays.
 *
 * It is wrong here. These are the pages where we make our case — the home page, About, Pricing,
 * Contact, the directory — and a reseller's staff are also the people we are selling to. Served
 * through that fallback, our copy and our prices arrived in *their* logo, their colours and
 * their name in the title bar, which tells the reader they are looking at their own company's
 * offer when they are looking at ours. A B2C visitor who happens to work for a client should
 * not have to work out whose page they are on.
 *
 * Only applied where no reseller was resolved from the request, so it can never reach a
 * reseller's own site: on their host, or under the /r/{slug} fallback, the tenant is bound and
 * this steps aside. It suppresses one fallback and changes nothing else — every route it
 * guards already reads its *content* from siteTenant(), and the header and footer already gate
 * their navigation on isResellerSite().
 */
class UsePlatformBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        // A bound tenant means the request resolved to somebody's site. Theirs, then, and
        // nothing here applies.
        if (! app()->bound(Reseller::class)) {
            ThemeSetting::usePlatformBranding();
        }

        return $next($request);
    }
}
