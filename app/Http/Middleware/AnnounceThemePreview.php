<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Support\SiteUrl;
use App\Themes\ThemePreview;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Says, on every previewed page, that it is a preview — and keeps it out of every cache.
 *
 * Runs on the way *out* rather than on the way in, because the tenant is not bound yet on the
 * way in for the /r/{slug} fallback: there the binding happens in a route middleware, inside
 * $next(). By the time the response comes back, all three resolution paths have run.
 *
 * The two jobs are one middleware because they are the same requirement seen from two sides.
 * A previewed page must not be mistaken for the live site — by the person looking at it, which
 * is what the bar is for, or by a cache between us and the next visitor, which is what
 * no-store is for. Getting the second wrong would poison a reseller's public site with a
 * design they never applied: the precise failure the query-parameter approach was rejected
 * for, reintroduced through the back door.
 */
class AnnounceThemePreview
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Their public site only. A reseller's staff previewing on our host would be seeing
        // the dashboard, where a bar welded over the page helps nobody — and siteTenant() is
        // already the platform's answer to "whose site is this", so preview borrows it rather
        // than inventing a second one.
        $reseller = ThemeSetting::siteTenant();

        if (! $reseller) {
            return $response;
        }

        $theme = ThemePreview::active($reseller);

        if (! $theme) {
            return $response;
        }

        ThemePreview::markUncacheable($response);

        // A redirect has no body to weld anything onto, and a JSON endpoint would be
        // corrupted by it — the search box on a themed header talks to one.
        if ($response->isRedirection() || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        ThemePreview::injectBar(
            $response,
            $theme,
            SiteUrl::to('/theme-preview/stop'),
            // Absolute, and the platform's: the dashboard lives on our host, and this link is
            // being rendered on theirs.
            route('reseller.theme'),
        );

        return $response;
    }
}
