<?php

namespace App\Http\Middleware;

use App\Helpers\ThemeSetting;
use App\Models\Reseller;
use App\Support\SuspendedSite;
use App\Themes\ActiveTheme;
use App\Themes\ThemePreview;
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
        // Their site, their template. Applied where the tenant is bound rather than in a
        // separate middleware, because those two facts are the same fact: this request is
        // being served as theirs. A middleware in the web group would miss this route, which
        // is the development fallback and binds later than the group runs.
        // Preview, when their own staff has one running on this site, otherwise what they
        // have applied. resolveTemplate() also swaps the palette to match, so a preview
        // answers "what would our site look like" rather than half of it.
        app(ActiveTheme::class)->use(ThemePreview::resolveTemplate($reseller));

        // Suspension closes the site, not the memorials. See App\Support\SuspendedSite for
        // which addresses still answer and why 503 rather than 404.
        if (SuspendedSite::locks($reseller, $request)) {
            return SuspendedSite::response($reseller);
        }

        return $next($request);
    }
}
